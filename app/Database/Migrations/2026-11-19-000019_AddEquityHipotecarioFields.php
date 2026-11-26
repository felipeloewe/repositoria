<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campos de crédito hipotecario (recibo de dividendo).
 * Additive — no wipe.
 */
class AddEquityHipotecarioFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'numero_credito' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'sucursal'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'moneda'         => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true, 'default' => 'UF'],
            'dividendo_n'    => ['type' => 'INT', 'null' => true],
            'plazo_pactado'  => ['type' => 'INT', 'null' => true],
            'amortizacion_uf'=> ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'interes_uf'     => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'comision_uf'    => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'seg_incendio_uf'=> ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'seg_desgravamen_uf' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'seg_adicionales_uf' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'dividendo_uf'   => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'dividendo_clp'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'fecha_vencimiento' => ['type' => 'DATE', 'null' => true],
            'direccion_garantia'=> ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'seguros_info'   => ['type' => 'TEXT', 'null' => true],
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
            'numero_credito', 'sucursal', 'moneda', 'dividendo_n', 'plazo_pactado',
            'amortizacion_uf', 'interes_uf', 'comision_uf', 'seg_incendio_uf',
            'seg_desgravamen_uf', 'seg_adicionales_uf', 'dividendo_uf', 'dividendo_clp',
            'fecha_vencimiento', 'direccion_garantia', 'seguros_info',
        ] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
