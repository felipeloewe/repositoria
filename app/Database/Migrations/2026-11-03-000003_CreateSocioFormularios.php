<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Formularios de socios: plantillas, campos, instancias y shares públicos.
 * Additive — no wipe.
 */
class CreateSocioFormularios extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('socio_form_plantillas')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'nombre'        => ['type' => 'VARCHAR', 'constraint' => 255],
                'descripcion'   => ['type' => 'TEXT', 'null' => true],
                'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'activo']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('socio_form_plantillas', true);
        }

        if (! $this->db->tableExists('socio_form_campos')) {
            $this->forge->addField([
                'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
                'plantilla_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'clave'        => ['type' => 'VARCHAR', 'constraint' => 64],
                'etiqueta'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'tipo'         => ['type' => 'VARCHAR', 'constraint' => 16],
                'obligatorio'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'orden'        => ['type' => 'INT', 'default' => 0],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['plantilla_id', 'clave']);
            $this->forge->addKey(['plantilla_id', 'orden']);
            $this->forge->addForeignKey('plantilla_id', 'socio_form_plantillas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('socio_form_campos', true);
        }

        if (! $this->db->tableExists('socio_form_shares')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'plantilla_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
                'token'         => ['type' => 'VARCHAR', 'constraint' => 64],
                'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
                'expira_at'     => ['type' => 'DATETIME', 'null' => true],
                'max_usos'      => ['type' => 'INT', 'default' => 1],
                'usos'          => ['type' => 'INT', 'default' => 0],
                'revoked_at'    => ['type' => 'DATETIME', 'null' => true],
                'created_by_id' => ['type' => 'VARCHAR', 'constraint' => 36],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('token');
            $this->forge->addKey(['empresa_id', 'plantilla_id']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('plantilla_id', 'socio_form_plantillas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('created_by_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
            $this->forge->createTable('socio_form_shares', true);
        }

        if (! $this->db->tableExists('socio_form_instancias')) {
            $this->forge->addField([
                'id'                   => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'           => ['type' => 'VARCHAR', 'constraint' => 36],
                'plantilla_id'         => ['type' => 'VARCHAR', 'constraint' => 36],
                'titulo'               => ['type' => 'VARCHAR', 'constraint' => 255],
                'estado'               => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pendiente'],
                'respuestas_json'      => ['type' => 'TEXT', 'null' => true],
                'documento_id'         => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'origen'               => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'socio'],
                'filled_by_usuario_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'share_id'             => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'completed_at'         => ['type' => 'DATETIME', 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'estado']);
            $this->forge->addKey('plantilla_id');
            $this->forge->addKey('documento_id');
            $this->forge->addKey('share_id');
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('plantilla_id', 'socio_form_plantillas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('filled_by_usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('share_id', 'socio_form_shares', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('socio_form_instancias', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('socio_form_instancias', true);
        $this->forge->dropTable('socio_form_shares', true);
        $this->forge->dropTable('socio_form_campos', true);
        $this->forge->dropTable('socio_form_plantillas', true);
    }
}
