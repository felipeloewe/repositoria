<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 1 foundations + Documento ACL stubs (Fase 2 schema only).
 * Hierarchy: Empresa → Departamentos → Sucursales.
 * Actividades / Temas de Interés = independent tags on Departamento.
 */
class CreateRepositoriaSchema extends Migration
{
    public function up(): void
    {
        // empresas
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 191],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 191],
            'rut'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'activa'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('rut');
        $this->forge->createTable('empresas', true);

        // departamentos
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 191],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['empresa_id', 'nombre']);
        $this->forge->addKey('empresa_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('departamentos', true);

        // sucursales
        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'codigo'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'activa'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['departamento_id', 'nombre']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('departamento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sucursales', true);

        // actividades (tag on departamento)
        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['departamento_id', 'nombre']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('departamento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('actividades', true);

        // temas_interes (independent tag on departamento)
        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['departamento_id', 'nombre']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('departamento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('temas_interes', true);

        // usuarios
        $this->forge->addField([
            'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 191],
            'nombre'        => ['type' => 'VARCHAR', 'constraint' => 191],
            'rut'           => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'activo'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('rut');
        $this->forge->createTable('usuarios', true);

        // membresias — one (usuario_id, empresa_id) → rol + optional sucursal
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'usuario_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'rol'         => ['type' => 'VARCHAR', 'constraint' => 40],
            'sucursal_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'activa'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['usuario_id', 'empresa_id']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('usuario_id');
        $this->forge->addKey('sucursal_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sucursal_id', 'sucursales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('membresias', true);

        // --- Fase 2 stubs (schema only; UI/CRUD in follow-up) ---

        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sucursal_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'nombre'          => ['type' => 'VARCHAR', 'constraint' => 191],
            'codigo'          => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('departamento_id');
        $this->forge->addKey('sucursal_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sucursal_id', 'sucursales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('documento_secciones', true);

        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'departamento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'sucursal_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'seccion_id'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'titulo'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'nombre_archivo'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ruta_relativa'   => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'mime'            => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'tamano_bytes'    => ['type' => 'BIGINT', 'null' => true],
            'subido_por_id'   => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('departamento_id');
        $this->forge->addKey('sucursal_id');
        $this->forge->addKey('seccion_id');
        $this->forge->addKey('subido_por_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('departamento_id', 'departamentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sucursal_id', 'sucursales', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('seccion_id', 'documento_secciones', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('subido_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('documentos', true);

        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'usuario_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['documento_id', 'usuario_id']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('usuario_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('documento_autorizaciones', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('documento_autorizaciones', true);
        $this->forge->dropTable('documentos', true);
        $this->forge->dropTable('documento_secciones', true);
        $this->forge->dropTable('membresias', true);
        $this->forge->dropTable('usuarios', true);
        $this->forge->dropTable('temas_interes', true);
        $this->forge->dropTable('actividades', true);
        $this->forge->dropTable('sucursales', true);
        $this->forge->dropTable('departamentos', true);
        $this->forge->dropTable('empresas', true);
    }
}
