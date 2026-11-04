<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaFormPlantillaModel;
use App\Models\EmpresaRecursoSistemaModel;
use App\Models\SocioFormPlantillaModel;

/**
 * Recursos genéricos de sistema (plantillas / futuros documentos) con on/off por empresa.
 * Default ON: ausencia de fila en empresa_recurso_sistema = activo.
 * Socios: form_plantilla · Empresa: form_plantilla_empresa (tablas y catálogo separados).
 */
final class RecursoSistema
{
    public const TIPO_FORM_PLANTILLA         = 'form_plantilla';
    public const TIPO_FORM_PLANTILLA_EMPRESA = 'form_plantilla_empresa';
    public const TIPO_DOCUMENTO              = 'documento';

    /** Primera plantilla genérica socio — Estado de Situación y Solicitud de productos. */
    public const CLAVE_ESTADO_SITUACION = 'estado_situacion_solicitud_productos';

    /** Primera plantilla genérica empresa — Solicitud interna. */
    public const CLAVE_SOLICITUD_INTERNA_EMPRESA = 'solicitud_interna_empresa';

    /** DJ identificación Beneficiarios Finales (Circular 57 Banco). */
    public const CLAVE_DJ_BENEFICIARIOS_FINALES = 'dj_beneficiarios_finales_circular_57';

    /** DJ Art. 85 Ley General de Bancos. */
    public const CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS = 'dj_art_85_ley_general_bancos';

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
                'descripcion' => 'Plantilla genérica de formulario de socios (PDF AcroForm). Activa por defecto.',
            ],
            [
                'tipo'        => self::TIPO_FORM_PLANTILLA_EMPRESA,
                'clave'       => self::CLAVE_SOLICITUD_INTERNA_EMPRESA,
                'nombre'      => 'Solicitud interna',
                'descripcion' => 'Plantilla genérica de Formulario (empresa). Activa por defecto; distinta de las genéricas de socios.',
            ],
            [
                'tipo'        => self::TIPO_FORM_PLANTILLA_EMPRESA,
                'clave'       => self::CLAVE_DJ_BENEFICIARIOS_FINALES,
                'nombre'      => 'DJ Beneficiarios Finales (Circular 57)',
                'descripcion' => 'Declaración jurada de identificación de beneficiarios finales (Circular 57). Genérica empresa; activa por defecto.',
            ],
            [
                'tipo'        => self::TIPO_FORM_PLANTILLA_EMPRESA,
                'clave'       => self::CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS,
                'nombre'      => 'DJ Art. 85 Ley General de Bancos',
                'descripcion' => 'Declaración Art. 85 Ley General de Bancos. Genérica empresa; activa por defecto.',
            ],
        ];
    }

    /** @return list<string> */
    public static function tiposConocidos(): array
    {
        return [
            self::TIPO_FORM_PLANTILLA,
            self::TIPO_FORM_PLANTILLA_EMPRESA,
            self::TIPO_DOCUMENTO,
        ];
    }

    public static function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_FORM_PLANTILLA         => 'Plantilla formulario (socios)',
            self::TIPO_FORM_PLANTILLA_EMPRESA => 'Plantilla formulario (empresa)',
            self::TIPO_DOCUMENTO              => 'Documento',
            default                           => $tipo,
        };
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
        /** @var list<array{clave: string, etiqueta: string, tipo: string, obligatorio: int, orden: int, seccion?: string}> $campos */
        $campos = is_file($camposPath) ? require $camposPath : [];
        if ($campos !== []) {
            $batch = [];
            foreach ($campos as $c) {
                $seccion = isset($c['seccion']) ? trim((string) $c['seccion']) : '';
                $batch[] = [
                    'id'           => new_id(),
                    'plantilla_id' => $plantillaId,
                    'clave'        => $c['clave'],
                    'etiqueta'     => $c['etiqueta'],
                    'tipo'         => $c['tipo'],
                    'obligatorio'  => (int) ($c['obligatorio'] ?? 0),
                    'orden'        => (int) ($c['orden'] ?? 0),
                    'seccion'      => $seccion !== '' ? $seccion : null,
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
     * Plantillas genéricas de socios activas para una empresa (globally activo + per-empresa ON).
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

    /**
     * Idempotent seed: plantilla genérica de empresa «Solicitud interna».
     */
    public static function ensureSolicitudInternaEmpresaPlantilla(?\CodeIgniter\Database\BaseConnection $db = null): string
    {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        $existing = $db->table('empresa_form_plantillas')
            ->where('clave_sistema', self::CLAVE_SOLICITUD_INTERNA_EMPRESA)
            ->get()
            ->getRowArray();

        if (is_array($existing)) {
            return (string) $existing['id'];
        }

        $plantillaId = new_id();
        $db->table('empresa_form_plantillas')->insert([
            'id'            => $plantillaId,
            'empresa_id'    => null,
            'clave_sistema' => self::CLAVE_SOLICITUD_INTERNA_EMPRESA,
            'nombre'        => 'Solicitud interna',
            'descripcion'   => 'Plantilla genérica de sistema para Formulario (empresa). Disponible para todas las empresas; el Master puede desactivarla por empresa.',
            'activo'        => 1,
            'creado_por_id' => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $campos = [
            ['clave' => 'solicitante', 'etiqueta' => 'Solicitante', 'tipo' => 'text', 'obligatorio' => 1, 'orden' => 1],
            ['clave' => 'area', 'etiqueta' => 'Área / departamento', 'tipo' => 'text', 'obligatorio' => 1, 'orden' => 2],
            ['clave' => 'fecha', 'etiqueta' => 'Fecha de solicitud', 'tipo' => 'date', 'obligatorio' => 1, 'orden' => 3],
            ['clave' => 'detalle', 'etiqueta' => 'Detalle de la solicitud', 'tipo' => 'text', 'obligatorio' => 1, 'orden' => 4],
            ['clave' => 'anexo', 'etiqueta' => 'Anexo', 'tipo' => 'file', 'obligatorio' => 0, 'orden' => 5],
        ];
        $batch = [];
        foreach ($campos as $c) {
            $batch[] = [
                'id'           => new_id(),
                'plantilla_id' => $plantillaId,
                'clave'        => $c['clave'],
                'etiqueta'     => $c['etiqueta'],
                'tipo'         => $c['tipo'],
                'obligatorio'  => (int) $c['obligatorio'],
                'orden'        => (int) $c['orden'],
                'created_at'   => $now,
            ];
        }
        $db->table('empresa_form_campos')->insertBatch($batch);

        return $plantillaId;
    }

    /**
     * Idempotent seed: DJ Beneficiarios Finales (Circular 57) — empresa.
     */
    public static function ensureDjBeneficiariosFinalesPlantilla(?\CodeIgniter\Database\BaseConnection $db = null): string
    {
        return self::ensureEmpresaPlantillaFromData(
            self::CLAVE_DJ_BENEFICIARIOS_FINALES,
            'DJ Beneficiarios Finales (Circular 57)',
            'Declaración jurada para la identificación de beneficiarios finales de personas y/o estructuras jurídicas (Circular Nº 57). Plantilla genérica de sistema; el Master puede desactivarla por empresa.',
            'dj_beneficiarios_finales_circular_57_campos.php',
            $db
        );
    }

    /**
     * Idempotent seed: DJ Art. 85 Ley General de Bancos — empresa.
     */
    public static function ensureDjArt85LeyGeneralBancosPlantilla(?\CodeIgniter\Database\BaseConnection $db = null): string
    {
        return self::ensureEmpresaPlantillaFromData(
            self::CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS,
            'DJ Art. 85 Ley General de Bancos',
            'Declaración Art. 85 Ley General de Bancos (obligaciones complementarias / márgenes de crédito). Plantilla genérica de sistema; el Master puede desactivarla por empresa.',
            'dj_art_85_ley_general_bancos_campos.php',
            $db
        );
    }

    /**
     * Shared idempotent seed for empresa generics backed by Seeds/data/*.php.
     */
    private static function ensureEmpresaPlantillaFromData(
        string $clave,
        string $nombre,
        string $descripcion,
        string $camposFile,
        ?\CodeIgniter\Database\BaseConnection $db = null
    ): string {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        $existing = $db->table('empresa_form_plantillas')
            ->where('clave_sistema', $clave)
            ->get()
            ->getRowArray();

        if (is_array($existing)) {
            return (string) $existing['id'];
        }

        $plantillaId = new_id();
        $db->table('empresa_form_plantillas')->insert([
            'id'            => $plantillaId,
            'empresa_id'    => null,
            'clave_sistema' => $clave,
            'nombre'        => $nombre,
            'descripcion'   => $descripcion,
            'activo'        => 1,
            'creado_por_id' => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $camposPath = APPPATH . 'Database/Seeds/data/' . $camposFile;
        /** @var list<array{clave: string, etiqueta: string, tipo: string, obligatorio: int, orden: int, seccion?: string}> $campos */
        $campos = is_file($camposPath) ? require $camposPath : [];
        if ($campos !== []) {
            $batch = [];
            foreach ($campos as $c) {
                $seccion = isset($c['seccion']) ? trim((string) $c['seccion']) : '';
                $batch[] = [
                    'id'           => new_id(),
                    'plantilla_id' => $plantillaId,
                    'clave'        => $c['clave'],
                    'etiqueta'     => $c['etiqueta'],
                    'tipo'         => $c['tipo'],
                    'obligatorio'  => (int) ($c['obligatorio'] ?? 0),
                    'orden'        => (int) ($c['orden'] ?? 0),
                    'seccion'      => $seccion !== '' ? $seccion : null,
                    'created_at'   => $now,
                ];
            }
            foreach (array_chunk($batch, 50) as $chunk) {
                $db->table('empresa_form_campos')->insertBatch($chunk);
            }
        }

        return $plantillaId;
    }

    /**
     * Plantillas genéricas de empresa activas (tablas empresa_form_*; tipo form_plantilla_empresa).
     *
     * @return list<array<string, mixed>>
     */
    public static function plantillasGenericasEmpresaActivas(string $empresaId): array
    {
        $rows = model(EmpresaFormPlantillaModel::class)
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
            if (! self::isActivo($empresaId, self::TIPO_FORM_PLANTILLA_EMPRESA, $clave)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
