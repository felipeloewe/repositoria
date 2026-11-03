<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Libraries\RecursoSistema;
use CodeIgniter\Database\Migration;

/**
 * Plantillas genéricas de sistema + on/off por empresa (default ON).
 * Additive — no wipe.
 */
class AddPlantillasGenericas extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('socio_form_plantillas')) {
            $this->relaxPlantillaTenantColumns();

            if (! $this->db->fieldExists('clave_sistema', 'socio_form_plantillas')) {
                $this->forge->addColumn('socio_form_plantillas', [
                    'clave_sistema' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'null'       => true,
                        'after'      => 'empresa_id',
                    ],
                ]);
                try {
                    $this->db->query('ALTER TABLE socio_form_plantillas ADD UNIQUE KEY socio_form_plantillas_clave_sistema (clave_sistema)');
                } catch (\Throwable) {
                    // ignore if exists
                }
            }
        }

        if (! $this->db->tableExists('empresa_recurso_sistema')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'recurso_tipo'  => ['type' => 'VARCHAR', 'constraint' => 32],
                'recurso_clave' => ['type' => 'VARCHAR', 'constraint' => 64],
                'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['empresa_id', 'recurso_tipo', 'recurso_clave']);
            $this->forge->addKey(['empresa_id', 'activo']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('empresa_recurso_sistema', true);
        }

        if ($this->db->tableExists('socio_form_plantillas')
            && $this->db->fieldExists('clave_sistema', 'socio_form_plantillas')
        ) {
            RecursoSistema::ensureEstadoSituacionPlantilla($this->db);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('socio_form_plantillas')
            && $this->db->fieldExists('clave_sistema', 'socio_form_plantillas')
        ) {
            $generic = $this->db->table('socio_form_plantillas')
                ->where('clave_sistema', RecursoSistema::CLAVE_ESTADO_SITUACION)
                ->get()
                ->getRowArray();
            if (is_array($generic)) {
                $this->db->table('socio_form_campos')->where('plantilla_id', $generic['id'])->delete();
                $this->db->table('socio_form_plantillas')->where('id', $generic['id'])->delete();
            }
        }

        if ($this->db->tableExists('empresa_recurso_sistema')) {
            $this->forge->dropTable('empresa_recurso_sistema', true);
        }

        if ($this->db->tableExists('socio_form_plantillas')
            && $this->db->fieldExists('clave_sistema', 'socio_form_plantillas')
        ) {
            try {
                $this->db->query('ALTER TABLE socio_form_plantillas DROP INDEX socio_form_plantillas_clave_sistema');
            } catch (\Throwable) {
                // ignore
            }
            $this->forge->dropColumn('socio_form_plantillas', 'clave_sistema');
        }

        // Restore NOT NULL only if no null tenants remain (safe for fresh down after seed wipe).
        // Skip re-tightening FKs in down to avoid data loss on mixed installs.
    }

    private function relaxPlantillaTenantColumns(): void
    {
        // Drop FKs that block NULL, then alter columns.
        $this->dropFkIfExists('socio_form_plantillas', 'socio_form_plantillas_empresa_id_foreign');
        $this->dropFkIfExists('socio_form_plantillas', 'socio_form_plantillas_creado_por_id_foreign');

        // CI may name FKs differently — discover from information_schema.
        $this->dropFksOnColumns('socio_form_plantillas', ['empresa_id', 'creado_por_id']);

        try {
            $this->db->query('ALTER TABLE socio_form_plantillas MODIFY empresa_id VARCHAR(36) NULL');
        } catch (\Throwable) {
            // ignore
        }
        try {
            $this->db->query('ALTER TABLE socio_form_plantillas MODIFY creado_por_id VARCHAR(36) NULL');
        } catch (\Throwable) {
            // ignore
        }

        try {
            $this->db->query(
                'ALTER TABLE socio_form_plantillas
                 ADD CONSTRAINT socio_form_plantillas_empresa_id_foreign
                 FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        } catch (\Throwable) {
            // ignore
        }
        try {
            $this->db->query(
                'ALTER TABLE socio_form_plantillas
                 ADD CONSTRAINT socio_form_plantillas_creado_por_id_foreign
                 FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        } catch (\Throwable) {
            // ignore
        }
    }

    private function dropFkIfExists(string $table, string $constraint): void
    {
        try {
            $this->db->query('ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $constraint);
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * @param list<string> $columns
     */
    private function dropFksOnColumns(string $table, array $columns): void
    {
        $dbName = $this->db->getDatabase();
        foreach ($columns as $col) {
            $rows = $this->db->query(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                   AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$dbName, $table, $col]
            )->getResultArray();
            foreach ($rows as $row) {
                $name = (string) ($row['CONSTRAINT_NAME'] ?? '');
                if ($name !== '') {
                    $this->dropFkIfExists($table, $name);
                }
            }
        }
    }
}
