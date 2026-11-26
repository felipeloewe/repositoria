<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fuentes de ingreso: mutuos, dividendos, donaciones, indemnizaciones.
 * Additive — no wipe.
 */
class AddEquityIngresoFuenteFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_fichas')) {
            return;
        }

        $cols = [
            'ingreso_mutuos'          => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'ingreso_dividendos'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'ingreso_donaciones'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'ingreso_indemnizaciones' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
        ];

        foreach ($cols as $name => $def) {
            if (! $this->db->fieldExists($name, 'equity_fichas')) {
                $this->forge->addColumn('equity_fichas', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_fichas')) {
            return;
        }
        foreach (['ingreso_mutuos', 'ingreso_dividendos', 'ingreso_donaciones', 'ingreso_indemnizaciones'] as $col) {
            if ($this->db->fieldExists($col, 'equity_fichas')) {
                $this->forge->dropColumn('equity_fichas', $col);
            }
        }
    }
}
