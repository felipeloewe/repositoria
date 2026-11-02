<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * User Alias (unique) + Cargo on usuarios. RUT already exists (nullable).
 * Idempotent for partial applies.
 */
class AddUsuariosAliasCargo extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('usuarios', 'alias', [
            'type'       => 'VARCHAR',
            'constraint' => 64,
            'null'       => true,
            'after'      => 'nombre',
        ]);
        $this->addColumnIfMissing('usuarios', 'cargo', [
            'type'       => 'VARCHAR',
            'constraint' => 191,
            'null'       => true,
            'after'      => 'alias',
        ]);

        $this->addUniqueIndexIfMissing('usuarios', 'usuarios_alias_unique', 'alias');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('usuarios', 'usuarios_alias_unique');
        $this->dropColumnIfExists('usuarios', 'cargo');
        $this->dropColumnIfExists('usuarios', 'alias');
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

    private function addUniqueIndexIfMissing(string $table, string $indexName, string $column): void
    {
        $this->db->resetDataCache();
        if (! $this->db->fieldExists($column, $table)) {
            return;
        }

        $fullIndex = $this->db->DBPrefix . $indexName;
        if ($this->indexExists($table, $fullIndex) || $this->indexExists($table, $indexName)) {
            return;
        }

        $tableName = $this->db->prefixTable($table);
        $this->db->query(
            'CREATE UNIQUE INDEX ' . $this->db->escapeIdentifiers($fullIndex)
            . ' ON ' . $this->db->escapeIdentifiers($tableName)
            . ' (' . $this->db->escapeIdentifiers($column) . ')'
        );
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $fullIndex = $this->db->DBPrefix . $indexName;
        if (! $this->indexExists($table, $fullIndex) && ! $this->indexExists($table, $indexName)) {
            return;
        }

        $tableName = $this->db->prefixTable($table);
        $name      = $this->indexExists($table, $fullIndex) ? $fullIndex : $indexName;
        $this->db->query(
            'DROP INDEX ' . $this->db->escapeIdentifiers($name)
            . ' ON ' . $this->db->escapeIdentifiers($tableName)
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
