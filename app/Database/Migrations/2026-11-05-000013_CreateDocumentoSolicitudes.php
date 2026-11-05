<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Solicitudes de archivo/baja + marca de descarga de respaldos ZIP.
 */
class CreateDocumentoSolicitudes extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('documento_solicitudes')) {
            $this->forge->addField([
                'id'                      => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'              => ['type' => 'VARCHAR', 'constraint' => 36],
                'documento_id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'tipo'                    => ['type' => 'VARCHAR', 'constraint' => 16],
                'estado'                  => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'PENDIENTE'],
                'solicitante_usuario_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
                'solicitante_membresia_id'=> ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'sucursal_id'             => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'departamento_id'         => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'nota_solicitud'          => ['type' => 'TEXT', 'null' => true],
                'validador_usuario_id'    => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'validado_at'             => ['type' => 'DATETIME', 'null' => true],
                'nota_validacion'         => ['type' => 'TEXT', 'null' => true],
                'respaldo_id'             => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'              => ['type' => 'DATETIME', 'null' => true],
                'updated_at'              => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('empresa_id');
            $this->forge->addKey('documento_id');
            $this->forge->addKey(['empresa_id', 'tipo', 'estado']);
            $this->forge->createTable('documento_solicitudes', true);
        }

        if ($this->db->tableExists('documento_archivo_respaldos')) {
            if (! $this->db->fieldExists('descargado_at', 'documento_archivo_respaldos')) {
                $this->forge->addColumn('documento_archivo_respaldos', [
                    'descargado_at' => [
                        'type' => 'DATETIME',
                        'null' => true,
                        'after' => 'created_at',
                    ],
                ]);
            }
            if (! $this->db->fieldExists('descargado_por_id', 'documento_archivo_respaldos')) {
                $this->forge->addColumn('documento_archivo_respaldos', [
                    'descargado_por_id' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 36,
                        'null'       => true,
                        'after'      => 'descargado_at',
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('documento_archivo_respaldos')) {
            if ($this->db->fieldExists('descargado_por_id', 'documento_archivo_respaldos')) {
                $this->forge->dropColumn('documento_archivo_respaldos', 'descargado_por_id');
            }
            if ($this->db->fieldExists('descargado_at', 'documento_archivo_respaldos')) {
                $this->forge->dropColumn('documento_archivo_respaldos', 'descargado_at');
            }
        }
        $this->forge->dropTable('documento_solicitudes', true);
    }
}
