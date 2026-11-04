<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Libraries\DepartamentoCatalogo;
use CodeIgniter\Database\Migration;

/**
 * Departamentos genéricos (10) con on/off por empresa.
 * Additive: columnas clave_sistema + activo; materializa catálogo en cada empresa.
 */
class AddDepartamentosGenericos extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('departamentos')) {
            return;
        }

        $fields = $this->db->getFieldNames('departamentos');
        if (! in_array('clave_sistema', $fields, true)) {
            $this->forge->addColumn('departamentos', [
                'clave_sistema' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'nombre',
                ],
            ]);
        }
        if (! in_array('activo', $fields, true)) {
            $this->forge->addColumn('departamentos', [
                'activo' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'after'      => 'clave_sistema',
                ],
            ]);
            $this->db->table('departamentos')->where('activo', null)->update(['activo' => 1]);
        }

        // Índice único por empresa + clave (varios NULL permitidos en MySQL).
        $idxName = 'departamentos_empresa_clave';
        try {
            $this->db->query(
                'CREATE UNIQUE INDEX `' . $idxName . '` ON `departamentos` (`empresa_id`, `clave_sistema`)'
            );
        } catch (\Throwable $e) {
            // ya existe u otro motor — ignore
        }

        DepartamentoCatalogo::ensureForAllEmpresas($this->db);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('departamentos')) {
            return;
        }
        try {
            $this->db->query('DROP INDEX `departamentos_empresa_clave` ON `departamentos`');
        } catch (\Throwable $e) {
            // ignore
        }
        $fields = $this->db->getFieldNames('departamentos');
        if (in_array('clave_sistema', $fields, true)) {
            // No borramos filas genéricas en down (pueden tener FKs); solo columnas nuevas.
            $this->db->table('departamentos')->where('clave_sistema !=', '')->update(['clave_sistema' => null]);
            $this->forge->dropColumn('departamentos', 'clave_sistema');
        }
        if (in_array('activo', $fields, true)) {
            $this->forge->dropColumn('departamentos', 'activo');
        }
    }
}
