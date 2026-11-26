<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hilo histórico de contratos de ingreso: al vencer uno y crear el siguiente,
 * se guarda de dónde venía (reemplaza_contrato_id).
 * Additive — no wipe.
 */
class AddContratoReemplazaId extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_ingresos_contratos')) {
            return;
        }
        if ($this->db->fieldExists('reemplaza_contrato_id', 'equity_ingresos_contratos')) {
            return;
        }

        $this->forge->addColumn('equity_ingresos_contratos', [
            'reemplaza_contrato_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'estado',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_ingresos_contratos')
            && $this->db->fieldExists('reemplaza_contrato_id', 'equity_ingresos_contratos')
        ) {
            $this->forge->dropColumn('equity_ingresos_contratos', 'reemplaza_contrato_id');
        }
    }
}
