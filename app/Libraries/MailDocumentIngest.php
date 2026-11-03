<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\CorreoDocumentoModel;
use App\Models\CorreoPreferenciaModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoSeccionModel;
use App\Models\MembresiaModel;

/**
 * Shared path: bytes from imap-mail → Documento + DocumentStorage.
 * Used by Correo UI and inbound webhook.
 */
final class MailDocumentIngest
{
    public const KIND_ATTACHMENT  = 'attachment';
    public const KIND_CERTIFICATE = 'certificate';

    /**
     * @param array<string, mixed> $memb Active membership row (usuario_id, empresa_id, rol, …)
     * @param array{bytes: string, filename: string, mime: string, titulo?: ?string} $file
     *
     * @return array{documento_id: string, created: bool, source_key: string}
     */
    public function saveToSection(
        array $memb,
        string $seccionId,
        string $threadId,
        string $kind,
        array $file,
        ?string $attachmentId = null,
        ?string $tituloOverride = null
    ): array {
        helper('id');

        $sourceKey = self::sourceKey($threadId, $kind, $attachmentId);
        $existing  = model(CorreoDocumentoModel::class)->findBySourceKey($sourceKey);
        if ($existing !== null) {
            return [
                'documento_id' => (string) $existing['documento_id'],
                'created'      => false,
                'source_key'   => $sourceKey,
            ];
        }

        $seccion = model(DocumentoSeccionModel::class)
            ->where('id', $seccionId)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($seccion === null) {
            throw new \RuntimeException('Sección no válida.');
        }
        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $seccion['sucursal_id'],
            'departamento_id' => $seccion['departamento_id'],
        ])) {
            throw new \RuntimeException('Sin permiso para subir en esta sección.');
        }

        $bytes = $file['bytes'];
        (new PlanEntitlement())->assertCanUpload((string) $memb['empresa_id'], strlen($bytes));

        $docId  = new_id();
        $titulo = $tituloOverride
            ?: ($file['titulo'] ?? null)
            ?: ($kind === self::KIND_CERTIFICATE
                ? ('Certificado de hilo · ' . $threadId)
                : ('Correo · ' . $file['filename']));

        $docs = model(DocumentoModel::class);
        $docs->insert([
            'id'              => $docId,
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $seccion['departamento_id'],
            'sucursal_id'     => $seccion['sucursal_id'],
            'seccion_id'      => $seccion['id'],
            'titulo'          => $titulo,
            'nombre_archivo'  => $file['filename'],
            'ruta_relativa'   => 'pending',
            'mime'            => $file['mime'],
            'tamano_bytes'    => strlen($bytes),
            'subido_por_id'   => $memb['usuario_id'],
        ]);

        try {
            $saved = DocumentStorage::save((string) $memb['empresa_id'], $docId, $file['filename'], $bytes);
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);

            $linkId = new_id();
            model(CorreoDocumentoModel::class)->insert([
                'id'            => $linkId,
                'empresa_id'    => $memb['empresa_id'],
                'documento_id'  => $docId,
                'usuario_id'    => $memb['usuario_id'],
                'thread_id'     => $threadId,
                'attachment_id' => $attachmentId,
                'kind'          => $kind,
                'source_key'    => $sourceKey,
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);

            throw $e;
        }

        return [
            'documento_id' => $docId,
            'created'      => true,
            'source_key'   => $sourceKey,
        ];
    }

    public static function sourceKey(string $threadId, string $kind, ?string $attachmentId = null): string
    {
        if ($kind === self::KIND_CERTIFICATE) {
            return $threadId . ':certificate';
        }

        $att = $attachmentId !== null && $attachmentId !== '' ? $attachmentId : 'unknown';

        return $threadId . ':att:' . $att;
    }

    /**
     * Resolve membership + sección for webhook ingest.
     *
     * @return array{memb: array<string, mixed>, seccion_id: string}|null
     */
    public function resolveTarget(string $usuarioId, ?string $suggestedSeccionId): ?array
    {
        $membresias = model(MembresiaModel::class)->forUsuario($usuarioId);
        if ($membresias === []) {
            return null;
        }

        if ($suggestedSeccionId !== null && $suggestedSeccionId !== '') {
            $seccion = model(DocumentoSeccionModel::class)->find($suggestedSeccionId);
            if (is_array($seccion)) {
                foreach ($membresias as $m) {
                    if (($m['empresa_id'] ?? null) !== ($seccion['empresa_id'] ?? null)) {
                        continue;
                    }
                    $full = model(MembresiaModel::class)->findActive((string) $m['id']);
                    if ($full === null) {
                        continue;
                    }
                    if (DocumentAcl::canUpload($full, [
                        'sucursal_id'     => $seccion['sucursal_id'],
                        'departamento_id' => $seccion['departamento_id'],
                    ])) {
                        return ['memb' => $full, 'seccion_id' => (string) $seccion['id']];
                    }
                }
            }
        }

        $prefs = model(CorreoPreferenciaModel::class);
        foreach ($membresias as $m) {
            $pref = $prefs->findFor($usuarioId, (string) $m['empresa_id']);
            if ($pref === null) {
                continue;
            }
            $seccion = model(DocumentoSeccionModel::class)
                ->where('id', $pref['seccion_id'])
                ->where('empresa_id', $m['empresa_id'])
                ->first();
            if ($seccion === null) {
                continue;
            }
            $full = model(MembresiaModel::class)->findActive((string) $m['id']);
            if ($full === null) {
                continue;
            }
            if (DocumentAcl::canUpload($full, [
                'sucursal_id'     => $seccion['sucursal_id'],
                'departamento_id' => $seccion['departamento_id'],
            ])) {
                return ['memb' => $full, 'seccion_id' => (string) $seccion['id']];
            }
        }

        return null;
    }
}
