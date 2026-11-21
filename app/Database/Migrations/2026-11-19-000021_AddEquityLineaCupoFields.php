<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Montos directos / indirectos para Líneas disponibles (créditos disponibles CMF).
 * Additive — no wipe.
 */
class AddEquityLineaCupoFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'monto_directo_uf'    => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'monto_directo_clp'   => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'monto_indirecto_uf'  => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'monto_indirecto_clp' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
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
        foreach (['monto_directo_uf', 'monto_directo_clp', 'monto_indirecto_uf', 'monto_indirecto_clp'] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
