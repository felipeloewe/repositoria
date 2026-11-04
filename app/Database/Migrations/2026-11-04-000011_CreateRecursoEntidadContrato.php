<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Relación entidad marcada (Recursos) ↔ documento ambito=contrato.
 */
class CreateRecursoEntidadContrato extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('empresa_recurso_entidad_contrato')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'entidad_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['empresa_id', 'entidad_id', 'documento_id']);
        $this->forge->addKey('entidad_id');
        $this->forge->addKey('documento_id');
        $this->forge->addKey('empresa_id');
        $this->forge->createTable('empresa_recurso_entidad_contrato', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('empresa_recurso_entidad_contrato', true);
    }
}
