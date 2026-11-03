<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 3 — preferencia de sección para auto-ingreso correo +
 * tabla de proveniencia idempotente mail → documento.
 */
class AddCorreoPreferenciasIngest extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'usuario_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'seccion_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['usuario_id', 'empresa_id']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('seccion_id');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('seccion_id', 'documento_secciones', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('correo_preferencias', true);

        $this->forge->addField([
            'id'               => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id'     => ['type' => 'VARCHAR', 'constraint' => 36],
            'usuario_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
            'thread_id'        => ['type' => 'VARCHAR', 'constraint' => 64],
            'attachment_id'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'kind'             => ['type' => 'VARCHAR', 'constraint' => 32],
            'source_key'       => ['type' => 'VARCHAR', 'constraint' => 191],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('source_key');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('documento_id');
        $this->forge->addKey('usuario_id');
        $this->forge->addKey('thread_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('correo_documentos', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('correo_documentos', true);
        $this->forge->dropTable('correo_preferencias', true);
    }
}
