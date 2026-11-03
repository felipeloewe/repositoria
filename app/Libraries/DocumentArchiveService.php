<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DocumentoModel;
use App\Models\DocumentoVersionModel;
use App\Models\DocumentoArchivoRespaldoModel;
use RuntimeException;
use ZipArchive;

/**
 * Archivar blobs bajo archivados/ y respaldos ZIP mensuales.
 */
final class DocumentArchiveService
{
    /**
     * Marca doc como ARCHIVADO, mueve blobs (actual + versiones) a archivados/.
     *
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $doc
     * @return array<string, mixed> Doc actualizado
     */
    public function archivar(array $memb, array $doc, ?string $nota = null): array
    {
        helper('id');
        $id        = (string) $doc['id'];
        $empresaId = (string) $doc['empresa_id'];
        $now       = date('Y-m-d H:i:s');

        $nuevaRuta = DocumentStorage::moveToArchivados(
            (string) ($doc['ruta_relativa'] ?? ''),
            $empresaId,
            $id
        );

        $versiones = model(DocumentoVersionModel::class)->where('documento_id', $id)->findAll();
        foreach ($versiones as $v) {
            $vr = DocumentStorage::moveToArchivados(
                (string) ($v['ruta_relativa'] ?? ''),
                $empresaId,
                $id
            );
            model(DocumentoVersionModel::class)->update($v['id'], ['ruta_relativa' => $vr]);
        }

        model(DocumentoModel::class)->update($id, [
            'estado'           => DocumentLifecycle::ARCHIVADO,
            'ruta_relativa'    => $nuevaRuta,
            'archivado_at'     => $doc['archivado_at'] ?? $now,
            'archivado_por_id' => $memb['usuario_id'],
            'notas_ciclo'      => $nota ?? ($doc['notas_ciclo'] ?? null),
        ]);

        return model(DocumentoModel::class)->find($id) ?? $doc;
    }

    /**
     * Genera ZIP de docs archivados del periodo YYYY-MM con blob en disco;
     * luego elimina esos blobs y marca blob_eliminado_at.
     *
     * @param array<string, mixed> $memb
     * @return array{respaldo: array<string, mixed>, docs: int}
     */
    public function generarRespaldoMensual(array $memb, string $periodo): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new RuntimeException('Periodo inválido (use YYYY-MM).');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZipArchive no está disponible en el servidor.');
        }

        helper('id');
        $empresaId = (string) $memb['empresa_id'];
        $inicio    = $periodo . '-01 00:00:00';
        $fin       = date('Y-m-d H:i:s', strtotime($periodo . '-01 +1 month'));

        $docs = model(DocumentoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('estado', DocumentLifecycle::ARCHIVADO)
            ->where('archivado_at >=', $inicio)
            ->where('archivado_at <', $fin)
            ->where('blob_eliminado_at', null)
            ->groupStart()
                ->where('ambito', DocumentAcl::AMBITO_GENERAL)
                ->orWhere('ambito', null)
            ->groupEnd()
            ->findAll();

        if ($docs === []) {
            throw new RuntimeException('No hay documentos archivados pendientes de respaldo en ' . $periodo . '.');
        }

        $stamp   = date('Ymd-His');
        $zipRel  = $empresaId . '/respaldos/archivados-' . $periodo . '-' . $stamp . '.zip';
        $zipFull = DocumentStorage::absolutePath($zipRel);

        $zip = new ZipArchive();
        if ($zip->open($zipFull, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP de respaldo.');
        }

        $manifest = [];
        $added    = 0;

        foreach ($docs as $doc) {
            $docId   = (string) $doc['id'];
            $entryBase = $docId . '/';
            $manifest[] = [
                'id'             => $docId,
                'titulo'         => $doc['titulo'],
                'tipo_id'        => $doc['tipo_id'],
                'estado'         => $doc['estado'],
                'archivado_at'   => $doc['archivado_at'],
                'ruta_relativa'  => $doc['ruta_relativa'],
                'nombre_archivo' => $doc['nombre_archivo'],
                'vence_at'       => $doc['vence_at'] ?? null,
                'cabecera_asunto'=> $doc['cabecera_asunto'] ?? null,
            ];

            $ruta = (string) ($doc['ruta_relativa'] ?? '');
            if ($ruta !== '' && $ruta !== 'pending') {
                try {
                    $full = DocumentStorage::absolutePath($ruta);
                    if (is_file($full)) {
                        $zip->addFile($full, $entryBase . 'actual/' . basename($full));
                        $added++;
                    }
                } catch (RuntimeException) {
                    // skip missing
                }
            }

            $versiones = model(DocumentoVersionModel::class)->where('documento_id', $docId)->findAll();
            foreach ($versiones as $v) {
                $vr = (string) ($v['ruta_relativa'] ?? '');
                if ($vr === '' || $vr === 'pending') {
                    continue;
                }
                try {
                    $vf = DocumentStorage::absolutePath($vr);
                    if (is_file($vf)) {
                        $zip->addFile($vf, $entryBase . 'v' . (int) $v['version_num'] . '/' . basename($vf));
                        $added++;
                    }
                } catch (RuntimeException) {
                    // skip
                }
            }
        }

        $zip->addFromString(
            'manifiesto.json',
            json_encode(['periodo' => $periodo, 'empresa_id' => $empresaId, 'documentos' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
        $zip->close();

        if (! is_file($zipFull)) {
            throw new RuntimeException('ZIP no encontrado tras crearlo.');
        }
        $tamano = (int) filesize($zipFull);

        $now = date('Y-m-d H:i:s');
        foreach ($docs as $doc) {
            $docId = (string) $doc['id'];
            // Borrar blob actual
            DocumentStorage::delete((string) ($doc['ruta_relativa'] ?? ''));
            $versiones = model(DocumentoVersionModel::class)->where('documento_id', $docId)->findAll();
            foreach ($versiones as $v) {
                DocumentStorage::delete((string) ($v['ruta_relativa'] ?? ''));
            }
            // Limpiar carpeta archivados/{docId}
            $archDir = DocumentStorage::absolutePath($empresaId . '/archivados/' . $docId);
            if (is_dir($archDir)) {
                $this->rmTree($archDir);
            }
            model(DocumentoModel::class)->update($docId, ['blob_eliminado_at' => $now]);
        }

        $respaldoId = new_id();
        model(DocumentoArchivoRespaldoModel::class)->insert([
            'id'            => $respaldoId,
            'empresa_id'    => $empresaId,
            'periodo'       => $periodo,
            'ruta_zip'      => $zipRel,
            'tamano_bytes'  => $tamano,
            'docs_count'    => count($docs),
            'creado_por_id' => $memb['usuario_id'],
            'created_at'    => $now,
        ]);

        $respaldo = model(DocumentoArchivoRespaldoModel::class)->find($respaldoId);
        if ($respaldo === null) {
            throw new RuntimeException('No se pudo registrar el respaldo.');
        }

        return ['respaldo' => $respaldo, 'docs' => count($docs), 'archivos_en_zip' => $added];
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmTree($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
