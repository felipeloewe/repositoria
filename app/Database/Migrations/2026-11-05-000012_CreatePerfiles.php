<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Administración → Perfiles: estereotipos + checkboxes + calificativo; asignación a membresías.
 */
class CreatePerfiles extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('perfiles')) {
            $this->forge->addField([
                'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
                'estereotipo'  => ['type' => 'VARCHAR', 'constraint' => 32],
                'nombre'       => ['type' => 'VARCHAR', 'constraint' => 191],
                'calificativo' => ['type' => 'VARCHAR', 'constraint' => 96, 'null' => true],
                'es_plantilla' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'activo'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('empresa_id');
            $this->forge->addKey(['empresa_id', 'estereotipo']);
            $this->forge->createTable('perfiles', true);
        }

        if (! $this->db->tableExists('perfil_permisos')) {
            $this->forge->addField([
                'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
                'perfil_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
                'clave'      => ['type' => 'VARCHAR', 'constraint' => 64],
                'habilitado' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['perfil_id', 'clave']);
            $this->forge->addKey('perfil_id');
            $this->forge->createTable('perfil_permisos', true);
        }

        if ($this->db->tableExists('membresias') && ! $this->db->fieldExists('perfil_id', 'membresias')) {
            $this->forge->addColumn('membresias', [
                'perfil_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                    'after'      => 'es_socio',
                ],
            ]);
            $this->db->query('ALTER TABLE membresias ADD INDEX idx_membresias_perfil_id (perfil_id)');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('membresias') && $this->db->fieldExists('perfil_id', 'membresias')) {
            $this->forge->dropColumn('membresias', 'perfil_id');
        }
        $this->forge->dropTable('perfil_permisos', true);
        $this->forge->dropTable('perfiles', true);
    }
}
