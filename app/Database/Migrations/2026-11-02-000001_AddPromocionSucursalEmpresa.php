<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Promoción filial (b) → empresa (a): campos de estado + auditoría.
 *
 * Idempotent: skips ADD COLUMN / CREATE INDEX when already present
 * (safe if a previous partial migrate left columns without a migrations row).
 */
class AddPromocionSucursalEmpresa extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('empresas', 'promoted_from_sucursal_id', [
            'type'       => 'VARCHAR',
            'constraint' => 36,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('empresas', 'origen_empresa_id', [
            'type'       => 'VARCHAR',
            'constraint' => 36,
            'null'       => true,
        ]);
        $this->addIndexIfMissing('empresas', 'empresas_promoted_from_sucursal_id', 'promoted_from_sucursal_id');
        $this->addIndexIfMissing('empresas', 'empresas_origen_empresa_id', 'origen_empresa_id');

        $this->addColumnIfMissing('sucursales', 'estado', [
            'type'       => 'VARCHAR',
            'constraint' => 32,
            'default'    => 'ACTIVA',
            'null'       => false,
        ]);
        $this->addColumnIfMissing('sucursales', 'promoted_to_empresa_id', [
            'type'       => 'VARCHAR',
            'constraint' => 36,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('sucursales', 'promoted_at', [
            'type' => 'DATETIME',
            'null' => true,
        ]);
        $this->addIndexIfMissing('sucursales', 'sucursales_estado', 'estado');
        $this->addIndexIfMissing('sucursales', 'sucursales_promoted_to_empresa_id', 'promoted_to_empresa_id');

        $this->addColumnIfMissing('usuarios', 'es_admin_central', [
            'type'       => 'TINYINT',
            'constraint' => 1,
            'default'    => 0,
            'null'       => false,
        ]);

        if (! $this->db->tableExists('promocion_sucursal_auditoria')) {
            $this->forge->addField([
                'id'                 => ['type' => 'VARCHAR', 'constraint' => 36],
                'actor_id'           => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'origen_empresa_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
                'origen_sucursal_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'nueva_empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
                'detalle'            => ['type' => 'TEXT', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('origen_sucursal_id');
            $this->forge->addKey('nueva_empresa_id');
            $this->forge->addKey('created_at');
            $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('origen_empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('origen_sucursal_id', 'sucursales', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('nueva_empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('promocion_sucursal_auditoria', true);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('promocion_sucursal_auditoria')) {
            $this->forge->dropTable('promocion_sucursal_auditoria', true);
        }

        $this->dropColumnIfExists('usuarios', 'es_admin_central');
        $this->dropColumnIfExists('sucursales', 'estado');
        $this->dropColumnIfExists('sucursales', 'promoted_to_empresa_id');
        $this->dropColumnIfExists('sucursales', 'promoted_at');
        $this->dropColumnIfExists('empresas', 'promoted_from_sucursal_id');
        $this->dropColumnIfExists('empresas', 'origen_empresa_id');
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function addColumnIfMissing(string $table, string $column, array $definition): void
    {
        // Schema cache may be stale after prior ADD COLUMN in the same process.
        $this->db->resetDataCache();
        if ($this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->addColumn($table, [$column => $definition]);
        $this->db->resetDataCache();
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        $this->db->resetDataCache();
        if (! $this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->dropColumn($table, $column);
        $this->db->resetDataCache();
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        $this->db->resetDataCache();
        if (! $this->db->fieldExists($column, $table)) {
            return;
        }

        $fullIndex = $this->db->DBPrefix . $indexName;
        if ($this->indexExists($table, $fullIndex)) {
            return;
        }

        $tableName = $this->db->prefixTable($table);
        $this->db->query(
            'CREATE INDEX ' . $this->db->escapeIdentifiers($fullIndex)
            . ' ON ' . $this->db->escapeIdentifiers($tableName)
            . ' (' . $this->db->escapeIdentifiers($column) . ')'
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $this->db->resetDataCache();
        $indexes = $this->db->getIndexData($table);
        foreach ($indexes as $name => $meta) {
            if ($name === $indexName || (is_object($meta) && ($meta->name ?? null) === $indexName)) {
                return true;
            }
        }

        return false;
    }
}
