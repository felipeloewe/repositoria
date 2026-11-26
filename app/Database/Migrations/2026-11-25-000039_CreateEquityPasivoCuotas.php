<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Desarrollo editable de créditos: cuotas mes a mes + archivo de importación.
 * Additive — no wipe.
 */
class CreateEquityPasivoCuotas extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivo_cuotas')) {
            $this->forge->addField([
                'id' => [
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
                'fecha' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'dividendo_n' => [
                    'type' => 'INT',
                    'null' => true,
                ],
                'moneda' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 8,
                    'null'       => true,
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
                'fuente' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'manual',
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
            $this->forge->addKey(['pasivo_id', 'fecha'], false, false, 'equity_cuotas_pasivo_fecha');
            $this->forge->addKey(['pasivo_id', 'dividendo_n'], false, false, 'equity_cuotas_pasivo_div');
            $this->forge->addKey(['empresa_id', 'pasivo_id'], false, false, 'equity_cuotas_empresa_pasivo');
            $this->forge->createTable('equity_pasivo_cuotas', true);
        }

        if ($this->db->tableExists('equity_pasivos')) {
            $cols = [
                'desarrollo_archivo_nombre' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'desarrollo_archivo_ruta'   => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
                'desarrollo_archivo_mime'   => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
                'desarrollo_archivo_bytes'  => ['type' => 'INT', 'null' => true],
            ];
            foreach ($cols as $name => $def) {
                if (! $this->db->fieldExists($name, 'equity_pasivos')) {
                    $this->forge->addColumn('equity_pasivos', [$name => $def]);
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_pasivo_cuotas')) {
            $this->forge->dropTable('equity_pasivo_cuotas', true);
        }
        if ($this->db->tableExists('equity_pasivos')) {
            foreach (['desarrollo_archivo_nombre', 'desarrollo_archivo_ruta', 'desarrollo_archivo_mime', 'desarrollo_archivo_bytes'] as $c) {
                if ($this->db->fieldExists($c, 'equity_pasivos')) {
                    $this->forge->dropColumn('equity_pasivos', $c);
                }
            }
        }
    }
}
