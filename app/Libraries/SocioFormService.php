<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DepartamentoModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use App\Models\EmpresaModel;
use App\Models\SocioFormCampoModel;
use App\Models\SocioFormInstanciaModel;
use App\Models\SocioFormPlantillaModel;
use App\Models\SocioFormShareModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

/**
 * Completar instancias de formularios de socios → PDF → documento ambito=socio.
 */
final class SocioFormService
{
    public const TIPOS = ['text', 'date', 'file'];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_ANULADO    = 'anulado';

    public const ORIGEN_SOCIO   = 'socio';
    public const ORIGEN_PUBLICO = 'publico';

    public static function slugifyClave(string $etiqueta): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $etiqueta) ?: $etiqueta;
        $s = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $s));
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'campo';
        }

        return substr($s, 0, 64);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function shareIsUsable(array $share): array
    {
        if (! empty($share['revoked_at'])) {
            return ['ok' => false, 'error' => 'Este enlace fue revocado.'];
        }
        if (! empty($share['expira_at']) && strtotime((string) $share['expira_at']) < time()) {
            return ['ok' => false, 'error' => 'Este enlace expiró.'];
        }
        $max = (int) ($share['max_usos'] ?? 1);
        $usos = (int) ($share['usos'] ?? 0);
        if ($max > 0 && $usos >= $max) {
            return ['ok' => false, 'error' => 'Este enlace ya fue utilizado el máximo de veces.'];
        }

        return ['ok' => true];
    }

    /**
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files keyed by campo clave
     * @return array{respuestas: array<string, mixed>, rows: list<array{label: string, value: string}>}
     */
    public function collectRespuestas(
        string $empresaId,
        string $actorUsuarioId,
        array $campos,
        array $post,
        array $files
    ): array {
        $respuestas = [];
        $rows       = [];

        foreach ($campos as $campo) {
            $clave     = (string) $campo['clave'];
            $etiqueta  = (string) $campo['etiqueta'];
            $tipo      = (string) $campo['tipo'];
            $required  = (int) ($campo['obligatorio'] ?? 0) === 1;

            if ($tipo === 'file') {
                $file = $files[$clave] ?? null;
                if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
                    if ($required) {
                        throw new RuntimeException('El archivo «' . $etiqueta . '» es obligatorio.');
                    }
                    $rows[] = ['label' => $etiqueta, 'value' => '(sin archivo)'];
                    continue;
                }
                if ($file->getSize() > 15 * 1024 * 1024) {
                    throw new RuntimeException('El archivo «' . $etiqueta . '» supera 15 MB.');
                }
                $docId = $this->storeUploadedAsSocioDoc(
                    $empresaId,
                    $actorUsuarioId,
                    $etiqueta . ' — ' . $file->getClientName(),
                    $file
                );
                $respuestas[$clave] = [
                    'nombre'       => $file->getClientName(),
                    'documento_id' => $docId,
                    'mime'         => $file->getMimeType() ?: 'application/octet-stream',
                    'tamano'       => $file->getSize(),
                ];
                $rows[] = ['label' => $etiqueta, 'value' => $file->getClientName() . ' (adjunto socio)'];
                continue;
            }

            $raw = trim((string) ($post[$clave] ?? ''));
            if ($raw === '' && $required) {
                throw new RuntimeException('El campo «' . $etiqueta . '» es obligatorio.');
            }
            if ($tipo === 'date' && $raw !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                throw new RuntimeException('Fecha inválida en «' . $etiqueta . '».');
            }
            $respuestas[$clave] = $raw;
            $rows[]             = ['label' => $etiqueta, 'value' => $raw === '' ? '—' : $raw];
        }

        return ['respuestas' => $respuestas, 'rows' => $rows];
    }

    /**
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files
     * @return array{instancia: array<string, mixed>, documento_id: string}
     */
    public function completeFill(
        array $plantilla,
        array $campos,
        string $titulo,
        string $origen,
        ?string $filledByUsuarioId,
        string $actorUsuarioId,
        ?string $shareId,
        array $post,
        array $files
    ): array {
        helper('id');

        $empresaId = (string) $plantilla['empresa_id'];
        $collected = $this->collectRespuestas($empresaId, $actorUsuarioId, $campos, $post, $files);

        $empresa = model(EmpresaModel::class)->find($empresaId);
        $empName = is_array($empresa) ? (string) ($empresa['nombre'] ?? '') : '';

        $pdfBinary = SimplePdf::fromKeyValues(
            $titulo,
            $collected['rows'],
            'Empresa: ' . $empName . ' · Plantilla: ' . (string) $plantilla['nombre'],
            'Generado ' . date('Y-m-d H:i') . ' · origen ' . $origen . ' · Repositoria Socios'
        );

        $docId = $this->storePdfAsSocioDoc($empresaId, $actorUsuarioId, $titulo, $pdfBinary);

        $instanciaId = new_id();
        $now         = date('Y-m-d H:i:s');
        model(SocioFormInstanciaModel::class)->insert([
            'id'                   => $instanciaId,
            'empresa_id'           => $empresaId,
            'plantilla_id'         => $plantilla['id'],
            'titulo'               => $titulo,
            'estado'               => self::ESTADO_COMPLETADO,
            'respuestas_json'      => json_encode($collected['respuestas'], JSON_UNESCAPED_UNICODE),
            'documento_id'         => $docId,
            'origen'               => $origen,
            'filled_by_usuario_id' => $filledByUsuarioId,
            'share_id'             => $shareId,
            'completed_at'         => $now,
        ]);

        if ($shareId !== null) {
            $share = model(SocioFormShareModel::class)->find($shareId);
            if (is_array($share)) {
                model(SocioFormShareModel::class)->update($shareId, [
                    'usos' => (int) ($share['usos'] ?? 0) + 1,
                ]);
            }
        }

        $instancia = model(SocioFormInstanciaModel::class)->find($instanciaId);
        if (! is_array($instancia)) {
            throw new RuntimeException('No se pudo crear la instancia.');
        }

        return ['instancia' => $instancia, 'documento_id' => $docId];
    }

    public function ensureTipoFormulario(string $empresaId): array
    {
        $tipos = model(DocumentoTipoModel::class);
        $exist = $tipos
            ->where('empresa_id', $empresaId)
            ->where('codigo', 'FORM_SOCIO')
            ->first();
        if (is_array($exist)) {
            return $exist;
        }

        helper('id');
        $id = new_id();
        $tipos->insert([
            'id'          => $id,
            'empresa_id'  => $empresaId,
            'nombre'      => 'Formulario socio',
            'codigo'      => 'FORM_SOCIO',
            'modalidad'   => DocumentLifecycle::MODALIDAD_BORRADOR,
            'descripcion' => 'PDF generado desde Formularios de Socios',
            'activo'      => 1,
        ]);
        $row = $tipos->find($id);
        if (! is_array($row)) {
            throw new RuntimeException('No se pudo crear tipo FORM_SOCIO.');
        }

        return $row;
    }

    private function storePdfAsSocioDoc(string $empresaId, string $usuarioId, string $titulo, string $binary): string
    {
        helper('id');
        $tipo  = $this->ensureTipoFormulario($empresaId);
        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            throw new RuntimeException('La empresa necesita al menos un departamento.');
        }

        try {
            (new PlanEntitlement())->assertCanUpload($empresaId, strlen($binary));
        } catch (RuntimeException $e) {
            throw $e;
        }

        $docId      = new_id();
        $filename   = 'formulario-socio-' . date('Ymd-His') . '.pdf';
        $docs       = model(DocumentoModel::class);
        $docs->insert([
            'id'                    => $docId,
            'empresa_id'            => $empresaId,
            'departamento_id'       => $depto['id'],
            'sucursal_id'           => null,
            'seccion_id'            => null,
            'ambito'                => DocumentAcl::AMBITO_SOCIO,
            'tipo_id'               => $tipo['id'],
            'estado'                => DocumentLifecycle::EN_ELABORACION,
            'version_actual'        => 1,
            'cadena_id'             => $docId,
            'cabecera_partes'       => null,
            'cabecera_asunto'       => $titulo,
            'cabecera_inicio'       => null,
            'cabecera_termino'      => null,
            'cabecera_autorizantes' => null,
            'fecha_termino'         => null,
            'titulo'                => $titulo,
            'nombre_archivo'        => $filename,
            'ruta_relativa'         => 'pending',
            'mime'                  => 'application/pdf',
            'tamano_bytes'          => strlen($binary),
            'subido_por_id'         => $usuarioId,
        ]);

        try {
            $saved = DocumentStorage::save($empresaId, $docId, $filename, $binary, DocumentAcl::AMBITO_SOCIO);
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            throw new RuntimeException('No se pudo guardar el PDF: ' . $e->getMessage(), 0, $e);
        }

        $doc = $docs->find($docId);
        if (is_array($doc)) {
            $this->registrarVersionInicial($empresaId, $usuarioId, $doc);
            model(DocumentoCambioModel::class)->insert([
                'id'           => new_id(),
                'empresa_id'   => $empresaId,
                'documento_id' => $docId,
                'actor_id'     => $usuarioId,
                'resumen'      => 'Formulario socio completado (PDF)',
                'detalle'      => $titulo,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return $docId;
    }

    private function storeUploadedAsSocioDoc(
        string $empresaId,
        string $usuarioId,
        string $titulo,
        UploadedFile $file
    ): string {
        helper('id');
        $tipo  = $this->ensureTipoFormulario($empresaId);
        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            throw new RuntimeException('La empresa necesita al menos un departamento.');
        }

        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        (new PlanEntitlement())->assertCanUpload($empresaId, strlen($binary));

        $docId    = new_id();
        $filename = $file->getClientName();
        $docs     = model(DocumentoModel::class);
        $docs->insert([
            'id'              => $docId,
            'empresa_id'      => $empresaId,
            'departamento_id' => $depto['id'],
            'sucursal_id'     => null,
            'seccion_id'      => null,
            'ambito'          => DocumentAcl::AMBITO_SOCIO,
            'tipo_id'         => $tipo['id'],
            'estado'          => DocumentLifecycle::EN_ELABORACION,
            'version_actual'  => 1,
            'cadena_id'       => $docId,
            'cabecera_asunto' => $titulo,
            'titulo'          => $titulo,
            'nombre_archivo'  => $filename,
            'ruta_relativa'   => 'pending',
            'mime'            => $file->getMimeType() ?: 'application/octet-stream',
            'tamano_bytes'    => strlen($binary),
            'subido_por_id'   => $usuarioId,
        ]);

        try {
            $saved = DocumentStorage::save($empresaId, $docId, $filename, $binary, DocumentAcl::AMBITO_SOCIO);
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            throw new RuntimeException('No se pudo guardar el adjunto.', 0, $e);
        }

        $doc = $docs->find($docId);
        if (is_array($doc)) {
            $this->registrarVersionInicial($empresaId, $usuarioId, $doc);
        }

        return $docId;
    }

    private function registrarVersionInicial(string $empresaId, string $usuarioId, array $doc): void
    {
        helper('id');
        $exists = model(DocumentoVersionModel::class)
            ->where('documento_id', $doc['id'])
            ->where('version_num', 1)
            ->first();
        if ($exists !== null) {
            return;
        }

        model(DocumentoVersionModel::class)->insert([
            'id'             => new_id(),
            'empresa_id'     => $empresaId,
            'documento_id'   => $doc['id'],
            'version_num'    => 1,
            'nombre_archivo' => $doc['nombre_archivo'],
            'ruta_relativa'  => $doc['ruta_relativa'],
            'mime'           => $doc['mime'],
            'tamano_bytes'   => $doc['tamano_bytes'],
            'notas'          => 'Versión inicial (formulario socio)',
            'creado_por_id'  => $usuarioId,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function camposOrdenados(string $plantillaId): array
    {
        return model(SocioFormCampoModel::class)
            ->where('plantilla_id', $plantillaId)
            ->orderBy('orden', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public static function plantillaOfEmpresa(string $id, string $empresaId): ?array
    {
        $row = model(SocioFormPlantillaModel::class)->find($id);
        if (! is_array($row) || (string) $row['empresa_id'] !== $empresaId) {
            return null;
        }

        return $row;
    }
}
