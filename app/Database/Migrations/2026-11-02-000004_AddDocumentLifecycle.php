<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tipos de documento (borrador|oficial) + ciclo de vida, versiones y control de cambios.
 */
class AddDocumentLifecycle extends Migration
{
    public function up(): void
    {
        // documento_tipos
        $this->forge->addField([
            'id'          => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 191],
            'codigo'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'modalidad'   => ['type' => 'VARCHAR', 'constraint' => 16], // borrador|oficial
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'activo'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['empresa_id', 'codigo']);
        $this->forge->addKey('empresa_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('documento_tipos', true);

        // Extender documentos
        $this->forge->addColumn('documentos', [
            'tipo_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'seccion_id',
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                // Ciclo provisional (oficiales): EN_ELABORACION|PUBLICADO|ARCHIVADO
                // Los nombres Borrador/En revisión/… son TIPOS, no estados.
                'default'    => 'EN_ELABORACION',
                'after'      => 'tipo_id',
            ],
            'version_actual' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
                'after'      => 'estado',
            ],
            'vence_at' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'version_actual',
            ],
            'alerta_dias' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'vence_at',
            ],
            'fecha_termino' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'alerta_dias',
            ],
            'notas_ciclo' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'fecha_termino',
            ],
        ]);
        $this->db->query('ALTER TABLE documentos ADD KEY documentos_tipo_id (tipo_id)');
        $this->db->query('ALTER TABLE documentos ADD KEY documentos_estado (estado)');
        $this->db->query(
            'ALTER TABLE documentos ADD CONSTRAINT documentos_tipo_id_foreign '
            . 'FOREIGN KEY (tipo_id) REFERENCES documento_tipos(id) ON DELETE SET NULL ON UPDATE CASCADE'
        );

        // documento_versiones
        $this->forge->addField([
            'id'             => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'     => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'version_num'    => ['type' => 'INT', 'constraint' => 11],
            'nombre_archivo' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ruta_relativa'  => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'mime'           => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'tamano_bytes'   => ['type' => 'BIGINT', 'null' => true],
            'notas'          => ['type' => 'TEXT', 'null' => true],
            'creado_por_id'  => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['documento_id', 'version_num']);
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('documento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('documento_versiones', true);

        // documento_estado_historial
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'estado_desde' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'estado_hasta' => ['type' => 'VARCHAR', 'constraint' => 32],
            'actor_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'nota'         => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('documento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('documento_estado_historial', true);

        // documento_cambios (borrador)
        $this->forge->addField([
            'id'           => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'documento_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'actor_id'     => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'resumen'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'detalle'      => ['type' => 'TEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('documento_id');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('documento_cambios', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('documento_cambios', true);
        $this->forge->dropTable('documento_estado_historial', true);
        $this->forge->dropTable('documento_versiones', true);

        // Drop FK + columns from documentos
        try {
            $this->db->query('ALTER TABLE documentos DROP FOREIGN KEY documentos_tipo_id_foreign');
        } catch (\Throwable) {
            // ignore if missing
        }
        foreach (['tipo_id', 'estado', 'version_actual', 'vence_at', 'alerta_dias', 'fecha_termino', 'notas_ciclo'] as $col) {
            if ($this->db->fieldExists($col, 'documentos')) {
                $this->forge->dropColumn('documentos', $col);
            }
        }

        $this->forge->dropTable('documento_tipos', true);
    }
}
