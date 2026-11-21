<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campos opcionales de dividendos Itaú / mutuos hipotecarios.
 * Additive — no wipe.
 */
class AddEquityHipotecarioItauFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'seg_otros_complementarios_uf' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'cuenta_pac'                   => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'dia_cargo_pac'                => ['type' => 'INT', 'null' => true],
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
        foreach (['seg_otros_complementarios_uf', 'cuenta_pac', 'dia_cargo_pac'] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
