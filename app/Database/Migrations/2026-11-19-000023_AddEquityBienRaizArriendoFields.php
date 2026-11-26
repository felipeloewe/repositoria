<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Arrendatario y cánon mensual en bienes raíces.
 * Additive — no wipe.
 */
class AddEquityBienRaizArriendoFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_bienes_raices')) {
            return;
        }

        $cols = [
            'arrendatario'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'canon_mensual_clp'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'canon_mensual_uf'   => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'mes_canon'          => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
            'medio_pago'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        ];

        foreach ($cols as $name => $def) {
            if (! $this->db->fieldExists($name, 'equity_bienes_raices')) {
                $this->forge->addColumn('equity_bienes_raices', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_bienes_raices')) {
            return;
        }
        foreach (['arrendatario', 'canon_mensual_clp', 'canon_mensual_uf', 'mes_canon', 'medio_pago'] as $col) {
            if ($this->db->fieldExists($col, 'equity_bienes_raices')) {
                $this->forge->dropColumn('equity_bienes_raices', $col);
            }
        }
    }
}
