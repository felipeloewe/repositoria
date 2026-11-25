<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pares CLP paralelos a componentes de cuota (amortización, interés, seguros…).
 * Si el crédito es en $, el desarrollo y los campos canónicos van en $; UF queda como equivalencia.
 * Additive — no wipe.
 */
class AddPasivoComponentesClp extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'amortizacion_clp'              => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'interes_clp'                   => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'comision_clp'                  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'seg_incendio_clp'              => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'seg_desgravamen_clp'           => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'seg_adicionales_clp'           => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'seg_otros_complementarios_clp' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
        ];

        foreach ($cols as $name => $def) {
            if (! $this->db->fieldExists($name, 'equity_pasivos')) {
                $this->forge->addColumn('equity_pasivos', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }
        foreach ([
            'amortizacion_clp', 'interes_clp', 'comision_clp',
            'seg_incendio_clp', 'seg_desgravamen_clp', 'seg_adicionales_clp',
            'seg_otros_complementarios_clp',
        ] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
