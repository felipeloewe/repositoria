<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cierre mensual Equity: periodo mes/año con V.B. + historial de reportes congelados.
 * Additive — no wipe.
 */
class CreateEquityPeriodosCierre extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_periodos_cierre')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'empresa_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'persona_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'anio' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 4,
                ],
                'mes' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                ],
                'fecha_cierre' => [
                    'type' => 'DATE',
                ],
                'uf_valor_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'estado' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'abierto',
                ],
                'cerrado_por_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'cerrado_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'vb_por_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'vb_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'vb_nota' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
                ],
                'notas' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['empresa_id', 'anio', 'mes'], false, false, 'equity_periodos_empresa_periodo');
            $this->forge->addKey(['empresa_id', 'persona_id', 'anio', 'mes'], false, false, 'equity_periodos_persona_periodo');
            $this->forge->createTable('equity_periodos_cierre', true);
        }

        if (! $this->db->tableExists('equity_reportes_historial')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'periodo_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'empresa_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'persona_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'tipo' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                ],
                'titulo' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                    'null'       => true,
                ],
                'payload_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'uf_valor_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'generado_por_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['periodo_id', 'tipo'], false, false, 'equity_hist_periodo_tipo');
            $this->forge->addKey(['empresa_id', 'tipo'], false, false, 'equity_hist_empresa_tipo');
            $this->forge->createTable('equity_reportes_historial', true);
        }

        // Desarrollo mensual congelado por crédito (cuota / amortización / interés del mes cerrado).
        if (! $this->db->tableExists('equity_pasivo_desarrollo')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'periodo_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'pasivo_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'empresa_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'persona_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'anio' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 4,
                ],
                'mes' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                ],
                'categoria' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => true,
                ],
                'banco_descripcion' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                    'null'       => true,
                ],
                'numero_credito' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'moneda' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 8,
                    'null'       => true,
                ],
                'dividendo_n' => [
                    'type'       => 'INT',
                    'null'       => true,
                ],
                'fecha_cuota' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'cuota_uf' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,4',
                    'null'       => true,
                ],
                'cuota_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'amortizacion_uf' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,4',
                    'null'       => true,
                ],
                'amortizacion_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'interes_uf' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,4',
                    'null'       => true,
                ],
                'interes_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'saldo_uf' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,4',
                    'null'       => true,
                ],
                'saldo_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'uf_valor_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['periodo_id', 'pasivo_id'], false, false, 'equity_des_periodo_pasivo');
            $this->forge->addKey(['empresa_id', 'anio', 'mes'], false, false, 'equity_des_empresa_periodo');
            $this->forge->createTable('equity_pasivo_desarrollo', true);
        }
    }

    public function down(): void
    {
        foreach (['equity_pasivo_desarrollo', 'equity_reportes_historial', 'equity_periodos_cierre'] as $t) {
            if ($this->db->tableExists($t)) {
                $this->forge->dropTable($t, true);
            }
        }
    }
}
