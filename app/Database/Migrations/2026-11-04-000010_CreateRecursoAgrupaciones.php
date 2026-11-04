<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Libraries\RecursoAgrupacionCatalogo;
use CodeIgniter\Database\Migration;

/**
 * Administración → Recursos: agrupaciones / servicios / entidades + checkboxes por empresa.
 */
class CreateRecursoAgrupaciones extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('recurso_agrupaciones')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'clave_sistema' => ['type' => 'VARCHAR', 'constraint' => 64],
                'nombre'        => ['type' => 'VARCHAR', 'constraint' => 191],
                'orden'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('clave_sistema');
            $this->forge->createTable('recurso_agrupaciones', true);
        }

        if (! $this->db->tableExists('recurso_servicios')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'agrupacion_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'clave_sistema' => ['type' => 'VARCHAR', 'constraint' => 64],
                'nombre'        => ['type' => 'VARCHAR', 'constraint' => 191],
                'orden'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('clave_sistema');
            $this->forge->addKey('agrupacion_id');
            $this->forge->addForeignKey('agrupacion_id', 'recurso_agrupaciones', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('recurso_servicios', true);
        }

        if (! $this->db->tableExists('recurso_entidades')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'servicio_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
                'clave_sistema' => ['type' => 'VARCHAR', 'constraint' => 64],
                'nombre'        => ['type' => 'VARCHAR', 'constraint' => 191],
                'orden'         => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('clave_sistema');
            $this->forge->addKey('servicio_id');
            $this->forge->addForeignKey('servicio_id', 'recurso_servicios', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('recurso_entidades', true);
        }

        if (! $this->db->tableExists('empresa_recurso_agrupacion')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'agrupacion_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'marcado'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['empresa_id', 'agrupacion_id']);
            $this->forge->addKey('agrupacion_id');
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('agrupacion_id', 'recurso_agrupaciones', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('empresa_recurso_agrupacion', true);
        }

        if (! $this->db->tableExists('empresa_recurso_entidad')) {
            $this->forge->addField([
                'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'entidad_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'marcado'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['empresa_id', 'entidad_id']);
            $this->forge->addKey('entidad_id');
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('entidad_id', 'recurso_entidades', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('empresa_recurso_entidad', true);
        }

        RecursoAgrupacionCatalogo::ensureForAllEmpresas($this->db);
    }

    public function down(): void
    {
        $this->forge->dropTable('empresa_recurso_entidad', true);
        $this->forge->dropTable('empresa_recurso_agrupacion', true);
        $this->forge->dropTable('recurso_entidades', true);
        $this->forge->dropTable('recurso_servicios', true);
        $this->forge->dropTable('recurso_agrupaciones', true);
    }
}
