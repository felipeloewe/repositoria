<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DepartamentoModel;
use App\Models\EmpresaModel;

/**
 * Departamentos genéricos de sistema (10) con on/off por empresa.
 * Materializados como filas en `departamentos` (clave_sistema + activo).
 * Default ON al materializar; Master desactiva en Administración → Departamentos.
 */
final class DepartamentoCatalogo
{
    /**
     * @return list<array{clave: string, nombre: string, descripcion: string}>
     */
    public static function catalogo(): array
    {
        return [
            [
                'clave'       => 'operaciones',
                'nombre'      => 'Operaciones',
                'descripcion' => 'Gestión de la operación diaria y cumplimiento de procesos de negocio.',
            ],
            [
                'clave'       => 'administracion',
                'nombre'      => 'Administración',
                'descripcion' => 'Soporte administrativo, control interno y coordinación organizacional.',
            ],
            [
                'clave'       => 'finanzas',
                'nombre'      => 'Finanzas',
                'descripcion' => 'Tesorería, presupuesto, control de gastos y reportes financieros.',
            ],
            [
                'clave'       => 'recursos_humanos',
                'nombre'      => 'Recursos Humanos',
                'descripcion' => 'Personas, contratos laborales, beneficios y desarrollo organizacional.',
            ],
            [
                'clave'       => 'comercial',
                'nombre'      => 'Comercial',
                'descripcion' => 'Ventas, cartera de clientes y seguimiento de oportunidades de negocio.',
            ],
            [
                'clave'       => 'marketing',
                'nombre'      => 'Marketing',
                'descripcion' => 'Comunicación, marca, campañas y materiales de difusión.',
            ],
            [
                'clave'       => 'tecnologia',
                'nombre'      => 'Tecnología',
                'descripcion' => 'Sistemas, infraestructura digital y soporte tecnológico interno.',
            ],
            [
                'clave'       => 'legal',
                'nombre'      => 'Legal',
                'descripcion' => 'Asuntos jurídicos, contratos institucionales y cumplimiento normativo.',
            ],
            [
                'clave'       => 'logistica',
                'nombre'      => 'Logística',
                'descripcion' => 'Distribución, inventario, abastecimiento y coordinación de entregas.',
            ],
            [
                'clave'       => 'atencion_clientes',
                'nombre'      => 'Atención a Clientes',
                'descripcion' => 'Servicio al cliente, reclamos y seguimiento postventa.',
            ],
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
     * Idempotent: materializa los 10 genéricos para la empresa (activo=1 si nuevos).
     * Si ya existe un depto custom con el mismo nombre, lo adopta (asigna clave_sistema).
     */
    public static function ensureForEmpresa(string $empresaId, ?\CodeIgniter\Database\BaseConnection $db = null): void
    {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        foreach (self::catalogo() as $item) {
            $clave  = $item['clave'];
            $nombre = $item['nombre'];

            $byClave = $db->table('departamentos')
                ->where('empresa_id', $empresaId)
                ->where('clave_sistema', $clave)
                ->get()
                ->getRowArray();
            if (is_array($byClave)) {
                continue;
            }

            // Adoptar custom homónimo (sin clave) para no chocar UNIQUE (empresa_id, nombre).
            $byNombre = $db->table('departamentos')
                ->where('empresa_id', $empresaId)
                ->where('nombre', $nombre)
                ->where('clave_sistema', null)
                ->get()
                ->getRowArray();
            if (is_array($byNombre)) {
                $db->table('departamentos')->where('id', $byNombre['id'])->update([
                    'clave_sistema' => $clave,
                    'activo'        => (int) ($byNombre['activo'] ?? 1) === 1 ? 1 : 0,
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
