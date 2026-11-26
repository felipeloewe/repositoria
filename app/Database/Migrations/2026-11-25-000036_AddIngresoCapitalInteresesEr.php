<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Otros ingresos: Capital ER e Intereses ER en ficha consolidada.
 * Additive — no wipe.
 */
class AddIngresoCapitalInteresesEr extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_fichas')) {
            return;
        }

        $cols = [
            'ingreso_capital_er'   => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'ingreso_intereses_er' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
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
        foreach (['ingreso_capital_er', 'ingreso_intereses_er'] as $col) {
            if ($this->db->fieldExists($col, 'equity_fichas')) {
                $this->forge->dropColumn('equity_fichas', $col);
            }
        }
    }
}
