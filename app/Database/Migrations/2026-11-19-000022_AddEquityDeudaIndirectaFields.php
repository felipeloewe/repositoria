<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campos de Deuda Indirecta (codeudor / fiador / avalista).
 * Additive — no wipe.
 */
class AddEquityDeudaIndirectaFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'fecha_otorgamiento' => ['type' => 'DATE', 'null' => true],
            'total_credito_uf'   => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'total_credito_clp'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'vigente_uf'         => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'vigente_clp'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'mora_30_59_uf'      => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'mora_30_59_clp'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'mora_60_89_uf'      => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'mora_60_89_clp'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'mora_90_mas_uf'     => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'mora_90_mas_clp'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
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
            'fecha_otorgamiento', 'total_credito_uf', 'total_credito_clp',
            'vigente_uf', 'vigente_clp', 'mora_30_59_uf', 'mora_30_59_clp',
            'mora_60_89_uf', 'mora_60_89_clp', 'mora_90_mas_uf', 'mora_90_mas_clp',
        ] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
