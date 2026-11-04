<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DepartamentoModel;
use App\Models\EmpresaModel;

/**
 * Departamentos genéricos de sistema (10) con on/off por empresa.
 * Materializados como filas en `departamentos` (clave_sistema + activo).
 * Default ON al materializar; Master desactiva en Administración → Departamentos.
 *
 * Catálogo definido por producto (Gestión de Personas … I+D).
 */
final class DepartamentoCatalogo
{
    /**
     * @return list<array{clave: string, nombre: string, descripcion: string, aliases?: list<string>}>
     */
    public static function catalogo(): array
    {
        return [
            [
                'clave'       => 'gestion_personas',
                'nombre'      => 'Gestión de Personas',
                'descripcion' => 'Atracción, retención, capacitación y bienestar del talento humano, además de las relaciones laborales y legales con los empleados.',
                'aliases'     => ['Recursos Humanos', 'RRHH', 'HR'],
            ],
            [
                'clave'       => 'admin_finanzas_contabilidad',
                'nombre'      => 'Administración, Finanzas y Contabilidad',
                'descripcion' => 'Administración general, flujo de caja, presupuestos, proyecciones de inversión, impuestos, auditorías y contabilidad general.',
                'aliases'     => ['Administración', 'Finanzas', 'Contabilidad'],
            ],
            [
                'clave'       => 'ventas_desarrollo_negocios',
                'nombre'      => 'Ventas y Desarrollo de Negocios',
                'descripcion' => 'Generación de ingresos mediante prospección, negociación, cierre de contratos y gestión de canales de distribución.',
                'aliases'     => ['Comercial', 'Ventas'],
            ],
            [
                'clave'       => 'marketing_comunicaciones',
                'nombre'      => 'Marketing y Comunicaciones',
                'descripcion' => 'Posicionamiento de marca, investigación de mercado, publicidad, estrategias digitales y comunicación interna y externa.',
                'aliases'     => ['Marketing'],
            ],
            [
                'clave'       => 'operaciones_cadena_suministro',
                'nombre'      => 'Operaciones y Cadena de Suministro',
                'descripcion' => 'Producción del bien o entrega del servicio, logística, compras de materias primas, almacenamiento, inventario y distribución final.',
                'aliases'     => ['Operaciones', 'Logística', 'Supply Chain'],
            ],
            [
                'clave'       => 'legales_cumplimiento',
                'nombre'      => 'Legales y Cumplimiento',
                'descripcion' => 'Marco legal, contratos comerciales, propiedad intelectual y cumplimiento de normativas locales e internacionales.',
                'aliases'     => ['Legal', 'Compliance'],
            ],
            [
                'clave'       => 'ti_ciberseguridad',
                'nombre'      => 'Tecnologías de la Información y Ciberseguridad',
                'descripcion' => 'Infraestructura tecnológica, software (ERP, CRM), nube, mesa de ayuda y protección de datos frente a ciberataques.',
                'aliases'     => ['Tecnología', 'IT', 'Informática'],
            ],
            [
                'clave'       => 'experiencia_servicio_cliente',
                'nombre'      => 'Experiencia y Servicio al Cliente',
                'descripcion' => 'Retención postventa, fidelización, soporte al usuario y gestión de reclamos.',
                'aliases'     => ['Atención a Clientes', 'Customer Success', 'Customer Service'],
            ],
            [
                'clave'       => 'compras_abastecimiento',
                'nombre'      => 'Compras y Abastecimiento Corporativo',
                'descripcion' => 'Negociación y adquisición de bienes, servicios y herramientas corporativas (software, flotas, consultores, etc.), distinto de materia prima operativa.',
                'aliases'     => ['Compras', 'Procurement'],
            ],
            [
                'clave'       => 'investigacion_desarrollo',
                'nombre'      => 'Investigación, Desarrollo e Innovación',
                'descripcion' => 'Mejora de productos actuales o creación de nuevos servicios; innovación y procesos o ingeniería según el giro.',
                'aliases'     => ['I+D', 'R&D', 'Innovación'],
            ],
        ];
    }

    /**
     * Claves del catálogo anterior → nuevas (branch pre-merge).
     *
     * @return array<string, string>
     */
    public static function legacyClaveMap(): array
    {
        return [
            'operaciones'       => 'operaciones_cadena_suministro',
            'administracion'    => 'admin_finanzas_contabilidad',
            'finanzas'          => 'admin_finanzas_contabilidad',
            'recursos_humanos'  => 'gestion_personas',
            'comercial'         => 'ventas_desarrollo_negocios',
            'marketing'         => 'marketing_comunicaciones',
            'tecnologia'        => 'ti_ciberseguridad',
            'legal'             => 'legales_cumplimiento',
            'logistica'         => 'operaciones_cadena_suministro',
            'atencion_clientes' => 'experiencia_servicio_cliente',
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_column(self::catalogo(), 'clave');
    }

    public static function isGenerico(?string $claveSistema): bool
    {
        $clave = trim((string) $claveSistema);

        return $clave !== '' && in_array($clave, self::claves(), true);
    }

    /**
     * Remapea claves del catálogo v1 si existen filas legacy en esta empresa.
     */
    private static function remapLegacyClaves(string $empresaId, \CodeIgniter\Database\BaseConnection $db, string $now): void
    {
        $byNombre = [];
        foreach (self::catalogo() as $item) {
            $byNombre[$item['clave']] = $item['nombre'];
        }

        foreach (self::legacyClaveMap() as $old => $new) {
            if ($old === $new) {
                continue;
            }
            $row = $db->table('departamentos')
                ->where('empresa_id', $empresaId)
                ->where('clave_sistema', $old)
                ->get()
                ->getRowArray();
            if (! is_array($row)) {
                continue;
            }

            $target = $db->table('departamentos')
                ->where('empresa_id', $empresaId)
                ->where('clave_sistema', $new)
                ->get()
                ->getRowArray();
            if (is_array($target)) {
                // Ya existe el genérico nuevo: dejar la fila legacy como custom.
                $db->table('departamentos')->where('id', $row['id'])->update([
                    'clave_sistema' => null,
                    'updated_at'    => $now,
                ]);
                continue;
            }

            $db->table('departamentos')->where('id', $row['id'])->update([
                'clave_sistema' => $new,
                'nombre'        => $byNombre[$new] ?? $row['nombre'],
                'updated_at'    => $now,
            ]);
        }
    }

    /**
     * Idempotent: materializa los 10 genéricos para la empresa (activo=1 si nuevos).
     * Si ya existe un depto custom con el mismo nombre (o alias), lo adopta.
     */
    public static function ensureForEmpresa(string $empresaId, ?\CodeIgniter\Database\BaseConnection $db = null): void
    {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        self::remapLegacyClaves($empresaId, $db, $now);

        foreach (self::catalogo() as $item) {
            $clave  = $item['clave'];
            $nombre = $item['nombre'];

            $byClave = $db->table('departamentos')
                ->where('empresa_id', $empresaId)
                ->where('clave_sistema', $clave)
                ->get()
                ->getRowArray();
            if (is_array($byClave)) {
                // Mantener nombre canónico del catálogo.
                if ((string) ($byClave['nombre'] ?? '') !== $nombre) {
                    $db->table('departamentos')->where('id', $byClave['id'])->update([
                        'nombre'     => $nombre,
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }

            $nombres = array_values(array_unique(array_merge([$nombre], $item['aliases'] ?? [])));
            $adopted = null;
            foreach ($nombres as $candidato) {
                $byNombre = $db->table('departamentos')
                    ->where('empresa_id', $empresaId)
                    ->where('nombre', $candidato)
                    ->groupStart()
                        ->where('clave_sistema', null)
                        ->orWhere('clave_sistema', '')
                    ->groupEnd()
                    ->get()
                    ->getRowArray();
                if (is_array($byNombre)) {
                    $adopted = $byNombre;
                    break;
                }
            }
            if (is_array($adopted)) {
                $db->table('departamentos')->where('id', $adopted['id'])->update([
                    'nombre'        => $nombre,
                    'clave_sistema' => $clave,
                    'activo'        => (int) ($adopted['activo'] ?? 1) === 1 ? 1 : 0,
                    'updated_at'    => $now,
                ]);
                continue;
            }

            $db->table('departamentos')->insert([
                'id'            => new_id(),
                'empresa_id'    => $empresaId,
                'nombre'        => $nombre,
                'clave_sistema' => $clave,
                'activo'        => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    /** Materializa genéricos para todas las empresas (migración / repair). */
    public static function ensureForAllEmpresas(?\CodeIgniter\Database\BaseConnection $db = null): int
    {
        $db ??= db_connect();
        $empresas = $db->table('empresas')->select('id')->get()->getResultArray();
        foreach ($empresas as $e) {
            self::ensureForEmpresa((string) $e['id'], $db);
        }

        return count($empresas);
    }

    public static function setActivo(string $empresaId, string $clave, bool $activo): bool
    {
        if (! in_array($clave, self::claves(), true)) {
            return false;
        }
        self::ensureForEmpresa($empresaId);
        $row = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('clave_sistema', $clave)
            ->first();
        if (! is_array($row)) {
            return false;
        }
        model(DepartamentoModel::class)->update($row['id'], [
            'activo'     => $activo ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * UI Administración: catálogo + estado materializado.
     *
     * @return list<array{clave: string, nombre: string, descripcion: string, id: ?string, activo: bool}>
     */
    public static function listGenericosForEmpresa(string $empresaId): array
    {
        self::ensureForEmpresa($empresaId);
        $rows = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->whereIn('clave_sistema', self::claves())
            ->findAll();
        $byClave = [];
        foreach ($rows as $r) {
            $byClave[(string) $r['clave_sistema']] = $r;
        }

        $out = [];
        foreach (self::catalogo() as $item) {
            $row = $byClave[$item['clave']] ?? null;
            $out[] = [
                'clave'       => $item['clave'],
                'nombre'      => $item['nombre'],
                'descripcion' => $item['descripcion'],
                'id'          => is_array($row) ? (string) $row['id'] : null,
                'activo'      => is_array($row) ? (int) ($row['activo'] ?? 1) === 1 : true,
            ];
        }

        return $out;
    }

    /**
     * Departamentos activos (genéricos ON + custom) para selects / listados operativos.
     *
     * @return list<array<string, mixed>>
     */
    public static function listActivos(string $empresaId): array
    {
        return model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * Custom (sin clave_sistema) de la empresa.
     *
     * @return list<array<string, mixed>>
     */
    public static function listCustom(string $empresaId): array
    {
        return model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->groupStart()
                ->where('clave_sistema', null)
                ->orWhere('clave_sistema', '')
            ->groupEnd()
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }
}
