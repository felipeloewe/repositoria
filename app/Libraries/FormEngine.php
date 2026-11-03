<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DepartamentoModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use App\Models\EmpresaModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Motor compartido de formularios (socios y empresa): respuestas → PDF → documento.
 * El ámbito y los modelos de instancia/share los aporta el caller vía FormScope.
 */
final class FormEngine
{
    public const TIPOS = ['text', 'date', 'file'];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_ANULADO    = 'anulado';

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
        $max  = (int) ($share['max_usos'] ?? 1);
        $usos = (int) ($share['usos'] ?? 0);
        if ($max > 0 && $usos >= $max) {
            return ['ok' => false, 'error' => 'Este enlace ya fue utilizado el máximo de veces.'];
        }

        return ['ok' => true];
    }

    public static function mintPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out      = '';
        $len      = strlen($alphabet);
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, $len - 1)];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files
     * @param callable(string,string,string,UploadedFile):string $storeUpload fn(empresaId, actorId, titulo, file): docId
     * @return array{respuestas: array<string, mixed>, rows: list<array{label: string, value: string}>}
     */
    public function collectRespuestas(
        string $empresaId,
        string $actorUsuarioId,
        array $campos,
        array $post,
        array $files,
        callable $storeUpload,
        string $fileValueSuffix = 'adjunto'
    ): array {
        $respuestas = [];
        $rows       = [];

        foreach ($campos as $campo) {
            $clave    = (string) $campo['clave'];
            $etiqueta = (string) $campo['etiqueta'];
            $tipo     = (string) $campo['tipo'];
            $required = (int) ($campo['obligatorio'] ?? 0) === 1;

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
                $docId = $storeUpload(
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
                $rows[] = ['label' => $etiqueta, 'value' => $file->getClientName() . ' (' . $fileValueSuffix . ')'];
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
     * Guarda PDF (o adjunto) como documento en el ámbito indicado.
     *
     * @return string documento id
     */
    public function storeBinaryAsDoc(
        string $empresaId,
        string $usuarioId,
        string $titulo,
        string $binary,
        string $filename,
        string $mime,
        string $ambito,
        string $tipoCodigo,
        string $tipoNombre,
        string $tipoDescripcion,
        string $cambioResumen,
        string $versionNotas
    ): string {
        helper('id');
        $tipo  = $this->ensureTipo($empresaId, $tipoCodigo, $tipoNombre, $tipoDescripcion);
        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            throw new RuntimeException('La empresa necesita al menos un departamento.');
        }

        (new PlanEntitlement())->assertCanUpload($empresaId, strlen($binary));

        $docId = new_id();
        $docs  = model(DocumentoModel::class);
        $docs->insert([
            'id'                    => $docId,
            'empresa_id'            => $empresaId,
            'departamento_id'       => $depto['id'],
            'sucursal_id'           => null,
            'seccion_id'            => null,
            'ambito'                => $ambito,
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
            'mime'                  => $mime,
            'tamano_bytes'          => strlen($binary),
            'subido_por_id'         => $usuarioId,
        ]);

        try {
            $saved = DocumentStorage::save($empresaId, $docId, $filename, $binary, $ambito);
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            throw new RuntimeException('No se pudo guardar el archivo: ' . $e->getMessage(), 0, $e);
        }

        $doc = $docs->find($docId);
        if (is_array($doc)) {
            $this->registrarVersionInicial($empresaId, $usuarioId, $doc, $versionNotas);
            if ($cambioResumen !== '') {
                model(DocumentoCambioModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $empresaId,
                    'documento_id' => $docId,
                    'actor_id'     => $usuarioId,
                    'resumen'      => $cambioResumen,
                    'detalle'      => $titulo,
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $docId;
    }

    /**
     * Completa un fill: respuestas → PDF → instancia.
     *
     * @param list<array<string, mixed>> $campos
     * @param array<string, mixed> $post
     * @param array<string, UploadedFile|null> $files
     * @param class-string<Model> $instanciaModelClass
     * @param class-string<Model>|null $shareModelClass
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
        array $files,
        ?string $empresaIdOverride,
        string $ambito,
        string $tipoCodigo,
        string $tipoNombre,
        string $tipoDescripcion,
        string $pdfFooter,
        string $fileValueSuffix,
        string $filenamePrefix,
        string $cambioResumen,
        string $versionNotas,
        string $instanciaModelClass,
        ?string $shareModelClass = null
    ): array {
        helper('id');

        $empresaId = trim((string) ($empresaIdOverride ?? ''));
        if ($empresaId === '') {
            $empresaId = trim((string) ($plantilla['empresa_id'] ?? ''));
        }
        if ($empresaId === '') {
            throw new RuntimeException('No se pudo resolver la empresa para el formulario.');
        }

        $storeUpload = function (
            string $empId,
            string $actorId,
            string $t,
            UploadedFile $file
        ) use ($ambito, $tipoCodigo, $tipoNombre, $tipoDescripcion, $versionNotas): string {
            $binary = file_get_contents($file->getTempName());
            if ($binary === false) {
                throw new RuntimeException('No se pudo leer el archivo subido.');
            }

            return $this->storeBinaryAsDoc(
                $empId,
                $actorId,
                $t,
                $binary,
                $file->getClientName(),
                $file->getMimeType() ?: 'application/octet-stream',
                $ambito,
                $tipoCodigo,
                $tipoNombre,
                $tipoDescripcion,
                '',
                $versionNotas
            );
        };

        $collected = $this->collectRespuestas(
            $empresaId,
            $actorUsuarioId,
            $campos,
            $post,
            $files,
            $storeUpload,
            $fileValueSuffix
        );

        $empresa = model(EmpresaModel::class)->find($empresaId);
        $empName = is_array($empresa) ? (string) ($empresa['nombre'] ?? '') : '';

        $pdfBinary = SimplePdf::fromKeyValues(
            $titulo,
            $collected['rows'],
            'Empresa: ' . $empName . ' · Plantilla: ' . (string) $plantilla['nombre'],
            'Generado ' . date('Y-m-d H:i') . ' · origen ' . $origen . ' · ' . $pdfFooter
        );

        $docId = $this->storeBinaryAsDoc(
            $empresaId,
            $actorUsuarioId,
            $titulo,
            $pdfBinary,
            $filenamePrefix . '-' . date('Ymd-His') . '.pdf',
            'application/pdf',
            $ambito,
            $tipoCodigo,
            $tipoNombre,
            $tipoDescripcion,
            $cambioResumen,
            $versionNotas
        );

        $instanciaId = new_id();
        $now         = date('Y-m-d H:i:s');
        model($instanciaModelClass)->insert([
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

        if ($shareId !== null && $shareModelClass !== null) {
            $share = model($shareModelClass)->find($shareId);
            if (is_array($share)) {
                model($shareModelClass)->update($shareId, [
                    'usos' => (int) ($share['usos'] ?? 0) + 1,
                ]);
            }
        }

        $instancia = model($instanciaModelClass)->find($instanciaId);
        if (! is_array($instancia)) {
            throw new RuntimeException('No se pudo crear la instancia.');
        }

        return ['instancia' => $instancia, 'documento_id' => $docId];
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureTipo(
        string $empresaId,
        string $codigo,
        string $nombre,
        string $descripcion
    ): array {
        $tipos = model(DocumentoTipoModel::class);
        $exist = $tipos
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();
        if (is_array($exist)) {
            return $exist;
        }

        helper('id');
        $id = new_id();
        $tipos->insert([
            'id'          => $id,
            'empresa_id'  => $empresaId,
            'nombre'      => $nombre,
            'codigo'      => $codigo,
            'modalidad'   => DocumentLifecycle::MODALIDAD_BORRADOR,
            'descripcion' => $descripcion,
            'activo'      => 1,
        ]);
        $row = $tipos->find($id);
        if (! is_array($row)) {
            throw new RuntimeException('No se pudo crear tipo ' . $codigo . '.');
        }

        return $row;
    }

    private function registrarVersionInicial(
        string $empresaId,
        string $usuarioId,
        array $doc,
        string $notas
    ): void {
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
            'notas'          => $notas,
            'creado_por_id'  => $usuarioId,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
