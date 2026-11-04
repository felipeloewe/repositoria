<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Actividades / Temas de Interés: departamento_id opcional
 * (relacionar o no con un departamento; tags a nivel empresa).
 */
class NullableTagDepartamento extends Migration
{
    public function up(): void
    {
        foreach (['actividades', 'temas_interes'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            // Quitar UNIQUE (departamento_id, nombre) si existe; recrear a nivel empresa.
            try {
                $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `departamento_id_nombre`');
            } catch (\Throwable $e) {
                // nombre de índice puede variar
            }
            try {
                $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $table . '_departamento_id_nombre`');
            } catch (\Throwable $e) {
                // ignore
            }

            // MySQL: hacer nullable el FK. Drop FK → modify → re-add.
            $fk = $this->findFkName($table, 'departamento_id');
            if ($fk !== null) {
                try {
                    $this->db->query('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $fk . '`');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $this->db->query(
                'ALTER TABLE `' . $table . '` MODIFY `departamento_id` VARCHAR(36) NULL'
            );

            try {
                $this->db->query(
                    'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $table . '_departamento_id_foreign`
                     FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`)
                     ON DELETE SET NULL ON UPDATE CASCADE'
                );
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $this->db->query(
                    'CREATE UNIQUE INDEX `' . $table . '_empresa_nombre` ON `' . $table . '` (`empresa_id`, `nombre`)'
                );
            } catch (\Throwable $e) {
                // already exists
            }
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL (puede haber filas sin depto).
    }

    private function findFkName(string $table, string $column): ?string
    {
        $dbName = $this->db->getDatabase();
        $rows = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$dbName, $table, $column]
        )->getResultArray();

        return isset($rows[0]['CONSTRAINT_NAME']) ? (string) $rows[0]['CONSTRAINT_NAME'] : null;
    }
}
