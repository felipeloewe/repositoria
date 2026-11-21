<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contratos / contrapartes de ingresos por fuente.
 * Additive — no wipe.
 */
class CreateEquityIngresosContratos extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('equity_ingresos_contratos')) {
            return;
        }

        $this->forge->addField([
            'id'                      => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'persona_id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'fuente'                  => ['type' => 'VARCHAR', 'constraint' => 32],
            'subtipo'                 => ['type' => 'VARCHAR', 'constraint' => 32],
            'contraparte_rut'         => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'contraparte_nombre'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'fecha_inicio_contrato'   => ['type' => 'DATE', 'null' => true],
            'periodicidad'            => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'mensual'],
            'monto_contrato_clp'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'monto_contrato_uf'       => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'estado'                  => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'vigente'],
            'contrato_archivo_nombre' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'contrato_ruta_relativa'  => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'contrato_mime'           => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'contrato_tamano_bytes'   => ['type' => 'INT', 'null' => true],
            'notas'                   => ['type' => 'TEXT', 'null' => true],
            'creado_por_id'           => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'persona_id', 'fuente']);
        $this->forge->addKey(['empresa_id', 'estado']);
        // Sin FK: en prod empresas/personas pueden diferir en collation y romper el CREATE.
        $this->forge->createTable('equity_ingresos_contratos', true);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_ingresos_contratos')) {
            $this->forge->dropTable('equity_ingresos_contratos', true);
        }
    }
}
