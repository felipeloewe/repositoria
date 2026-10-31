<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Fase 4 — fiscalización + canales seguros (+ auditoría). */
class CreateFase4Flujos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'sucursal_id'         => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'tipo'                => ['type' => 'VARCHAR', 'constraint' => 32],
            'titulo'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'motivo'              => ['type' => 'TEXT', 'null' => true],
            'estado'              => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'BORRADOR'],
            'solicitado_por_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'revisado_por_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'revisado_at'         => ['type' => 'DATETIME', 'null' => true],
            'comentario_revision' => ['type' => 'TEXT', 'null' => true],
            'enviado_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'estado']);
        $this->forge->addKey('solicitado_por_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('sucursal_id', 'sucursales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('solicitado_por_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('revisado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('fiscalizacion_solicitudes', true);

        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'solicitud_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['solicitud_id', 'documento_id']);
        $this->forge->addKey('documento_id');
        $this->forge->addForeignKey('solicitud_id', 'fiscalizacion_solicitudes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('fiscalizacion_documentos', true);

        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'solicitud_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'actor_id'     => ['type' => 'VARCHAR', 'constraint' => 36],
            'accion'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'detalle'      => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'created_at']);
        $this->forge->addKey('solicitud_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('solicitud_id', 'fiscalizacion_solicitudes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('fiscalizacion_auditoria', true);

        $this->forge->addField([
            'id'               => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
            'titulo'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'descripcion'      => ['type' => 'TEXT', 'null' => true],
            'destinatario_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'creado_por_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
            'estado'           => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVO'],
            'expira_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'estado']);
        $this->forge->addKey('destinatario_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('destinatario_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('canal_paquetes', true);

        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'paquete_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['paquete_id', 'documento_id']);
        $this->forge->addKey('documento_id');
        $this->forge->addForeignKey('paquete_id', 'canal_paquetes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('canal_paquete_documentos', true);

        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'paquete_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'actor_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'accion'     => ['type' => 'VARCHAR', 'constraint' => 64],
            'detalle'    => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'created_at']);
        $this->forge->addKey('paquete_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('paquete_id', 'canal_paquetes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('canal_paquete_auditoria', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('canal_paquete_auditoria', true);
        $this->forge->dropTable('canal_paquete_documentos', true);
        $this->forge->dropTable('canal_paquetes', true);
        $this->forge->dropTable('fiscalizacion_auditoria', true);
        $this->forge->dropTable('fiscalizacion_documentos', true);
        $this->forge->dropTable('fiscalizacion_solicitudes', true);
    }
}
