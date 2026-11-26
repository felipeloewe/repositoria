<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campos del estado de cuenta mensual AFP (cartola): periodo, cuotas, desglose.
 * Additive — no wipe. Fondos de pensión sigue fuera de reportes.
 */
class AddEquityFondosPensionCartolaFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_fondos_pension')) {
            return;
        }

        $cols = [
            'periodo_cierre' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => 'Cierre del estado de cuenta (p.ej. 2026-10-31)',
            ],
            'valor_cuota' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,4',
                'null'       => true,
            ],
            'saldo_cuotas' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,4',
                'null'       => true,
            ],
            'distribucion_pct' => [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'null'       => true,
                'comment'    => '% del saldo en este fondo',
            ],
            'indemnizacion_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'aportes_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
                'comment'    => 'Tus aportes (histórico)',
            ],
            'rentabilidad_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
                'comment'    => 'Ganancia por rentabilidad',
            ],
            'retiros_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'ahorro_total_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
                'comment'    => 'Aportes + rentabilidad (cartola)',
            ],
            'multiplicador' => [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'null'       => true,
                'comment'    => 'Veces que se multiplicaron los aportes',
            ],
        ];

        foreach ($cols as $name => $def) {
            if (! $this->db->fieldExists($name, 'equity_fondos_pension')) {
                $this->forge->addColumn('equity_fondos_pension', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_fondos_pension')) {
            return;
        }
        foreach ([
            'periodo_cierre', 'valor_cuota', 'saldo_cuotas', 'distribucion_pct',
            'indemnizacion_clp', 'aportes_clp', 'rentabilidad_clp', 'retiros_clp',
            'ahorro_total_clp', 'multiplicador',
        ] as $col) {
            if ($this->db->fieldExists($col, 'equity_fondos_pension')) {
                $this->forge->dropColumn('equity_fondos_pension', $col);
            }
        }
    }
}
