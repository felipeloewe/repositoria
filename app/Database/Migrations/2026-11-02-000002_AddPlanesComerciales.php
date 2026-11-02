<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Planes comerciales + entitlement fields on empresas.
 *
 * Idempotent: skips ADD COLUMN / CREATE TABLE when already present.
 */
class AddPlanesComerciales extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('planes')) {
            $this->forge->addField([
                'id'                 => ['type' => 'VARCHAR', 'constraint' => 36],
                'codigo'             => ['type' => 'VARCHAR', 'constraint' => 64],
                'nombre'             => ['type' => 'VARCHAR', 'constraint' => 191],
                'descripcion'        => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
                'tipo_aplicable'     => ['type' => 'VARCHAR', 'constraint' => 16], // a|b|ambos
                'max_usuarios'       => ['type' => 'INT', 'null' => true],
                'max_storage_bytes'  => ['type' => 'BIGINT', 'null' => true],
                'es_gratuito'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'precio_mensual'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
                'activo'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('codigo');
            $this->forge->addKey('activo');
            $this->forge->createTable('planes', true);
        }

        $this->addColumnIfMissing('empresas', 'tipo', [
            'type'       => 'CHAR',
            'constraint' => 1,
            'default'    => 'b',
            'null'       => false,
        ]);
        $this->addColumnIfMissing('empresas', 'plan_id', [
            'type'       => 'VARCHAR',
            'constraint' => 36,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('empresas', 'subscription_status', [
            'type'       => 'VARCHAR',
            'constraint' => 32,
            'default'    => 'free',
            'null'       => false,
        ]);
        $this->addColumnIfMissing('empresas', 'seats_limit', [
            'type'       => 'INT',
            'null'       => true,
        ]);
        $this->addColumnIfMissing('empresas', 'storage_limit_bytes', [
            'type' => 'BIGINT',
            'null' => true,
        ]);

        $this->addIndexIfMissing('empresas', 'empresas_tipo', 'tipo');
        $this->addIndexIfMissing('empresas', 'empresas_plan_id', 'plan_id');
        $this->addIndexIfMissing('empresas', 'empresas_subscription_status', 'subscription_status');
    }

    public function down(): void
    {
        $this->dropColumnIfExists('empresas', 'storage_limit_bytes');
        $this->dropColumnIfExists('empresas', 'seats_limit');
        $this->dropColumnIfExists('empresas', 'subscription_status');
        $this->dropColumnIfExists('empresas', 'plan_id');
        $this->dropColumnIfExists('empresas', 'tipo');

        if ($this->db->tableExists('planes')) {
            $this->forge->dropTable('planes', true);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function addColumnIfMissing(string $table, string $column, array $definition): void
    {
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
