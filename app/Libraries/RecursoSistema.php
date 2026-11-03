<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaRecursoSistemaModel;
use App\Models\SocioFormPlantillaModel;

/**
 * Recursos genéricos de sistema (plantillas / futuros documentos) con on/off por empresa.
 * Default ON: ausencia de fila en empresa_recurso_sistema = activo.
 */
final class RecursoSistema
{
    public const TIPO_FORM_PLANTILLA = 'form_plantilla';
    public const TIPO_DOCUMENTO      = 'documento';

    /** Primera plantilla genérica — Estado de Situación y Solicitud de productos. */
    public const CLAVE_ESTADO_SITUACION = 'estado_situacion_solicitud_productos';

    /**
     * Catálogo conocido (UI Master). Futuros docs genéricos se agregan aquí.
     *
     * @return list<array{tipo: string, clave: string, nombre: string, descripcion: string}>
     */
    public static function catalogo(): array
    {
        return [
            [
                'tipo'        => self::TIPO_FORM_PLANTILLA,
                'clave'       => self::CLAVE_ESTADO_SITUACION,
                'nombre'      => 'Estado de Situación y Solicitud de productos',
                'descripcion' => 'Plantilla genérica de formulario (PDF AcroForm). Activa por defecto en todas las empresas.',
            ],
        ];
    }

    public static function isActivo(string $empresaId, string $tipo, string $clave): bool
    {
        $row = model(EmpresaRecursoSistemaModel::class)
            ->where('empresa_id', $empresaId)
            ->where('recurso_tipo', $tipo)
            ->where('recurso_clave', $clave)
            ->first();

        if (! is_array($row)) {
            return true; // default ON
        }

        return (int) ($row['activo'] ?? 1) === 1;
    }

    /**
     * Activa o desactiva un recurso para la empresa (upsert).
     */
    public static function setActivo(string $empresaId, string $tipo, string $clave, bool $activo): void
    {
        helper('id');
        $model = model(EmpresaRecursoSistemaModel::class);
        $exist = $model
            ->where('empresa_id', $empresaId)
            ->where('recurso_tipo', $tipo)
            ->where('recurso_clave', $clave)
            ->first();

        $now = date('Y-m-d H:i:s');
        if (is_array($exist)) {
            $model->update($exist['id'], [
                'activo'     => $activo ? 1 : 0,
                'updated_at' => $now,
            ]);

            return;
        }

        $model->insert([
            'id'            => new_id(),
            'empresa_id'    => $empresaId,
            'recurso_tipo'  => $tipo,
            'recurso_clave' => $clave,
            'activo'        => $activo ? 1 : 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    /**
     * @return list<array{tipo: string, clave: string, nombre: string, descripcion: string, activo: bool}>
     */
    public static function listForEmpresa(string $empresaId): array
    {
        $out = [];
        foreach (self::catalogo() as $item) {
            $out[] = [
                'tipo'        => $item['tipo'],
                'clave'       => $item['clave'],
                'nombre'      => $item['nombre'],
                'descripcion' => $item['descripcion'],
                'activo'      => self::isActivo($empresaId, $item['tipo'], $item['clave']),
            ];
        }

        return $out;
    }

    public static function isGenerica(array $plantilla): bool
    {
        $clave = trim((string) ($plantilla['clave_sistema'] ?? ''));

        return $clave !== '';
    }

    /**
     * Idempotent seed of the system plantilla + campos from packaged data file.
     */
    public static function ensureEstadoSituacionPlantilla(?\CodeIgniter\Database\BaseConnection $db = null): string
    {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        $existing = $db->table('socio_form_plantillas')
            ->where('clave_sistema', self::CLAVE_ESTADO_SITUACION)
            ->get()
            ->getRowArray();

        if (is_array($existing)) {
            return (string) $existing['id'];
        }

        $plantillaId = new_id();
        $db->table('socio_form_plantillas')->insert([
            'id'            => $plantillaId,
            'empresa_id'    => null,
            'clave_sistema' => self::CLAVE_ESTADO_SITUACION,
            'nombre'        => 'Estado de Situación y Solicitud de productos',
            'descripcion'   => 'Plantilla genérica del sistema (fuente PDF editable). Disponible para todas las empresas; el Master puede desactivarla por empresa.',
            'activo'        => 1,
            'creado_por_id' => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $camposPath = APPPATH . 'Database/Seeds/data/estado_situacion_solicitud_productos_campos.php';
        /** @var list<array{clave: string, etiqueta: string, tipo: string, obligatorio: int, orden: int}> $campos */
        $campos = is_file($camposPath) ? require $camposPath : [];
        if ($campos !== []) {
            $batch = [];
            foreach ($campos as $c) {
                $batch[] = [
                    'id'           => new_id(),
                    'plantilla_id' => $plantillaId,
                    'clave'        => $c['clave'],
                    'etiqueta'     => $c['etiqueta'],
                    'tipo'         => $c['tipo'],
                    'obligatorio'  => (int) ($c['obligatorio'] ?? 0),
                    'orden'        => (int) ($c['orden'] ?? 0),
                    'created_at'   => $now,
                ];
            }
            // insert in chunks for large field sets
            foreach (array_chunk($batch, 50) as $chunk) {
                $db->table('socio_form_campos')->insertBatch($chunk);
            }
        }

        return $plantillaId;
    }

    /**
     * Plantillas genéricas activas para una empresa (globally activo + per-empresa ON).
     *
     * @return list<array<string, mixed>>
     */
    public static function plantillasGenericasActivas(string $empresaId): array
    {
        $rows = model(SocioFormPlantillaModel::class)
            ->where('clave_sistema !=', '')
            ->where('activo', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $out = [];
        foreach ($rows as $row) {
            $clave = (string) ($row['clave_sistema'] ?? '');
            if ($clave === '') {
                continue;
            }
            if (! self::isActivo($empresaId, self::TIPO_FORM_PLANTILLA, $clave)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
