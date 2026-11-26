<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fecha de vencimiento en contratos de ingreso (cualquier fuente/subtipo).
 * Additive — no wipe.
 */
class AddFechaVencimientoIngresoContrato extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_ingresos_contratos')) {
            return;
        }
        if ($this->db->fieldExists('fecha_vencimiento', 'equity_ingresos_contratos')) {
            return;
        }

        $this->forge->addColumn('equity_ingresos_contratos', [
            'fecha_vencimiento' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'fecha_inicio_contrato',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_ingresos_contratos')
            && $this->db->fieldExists('fecha_vencimiento', 'equity_ingresos_contratos')
        ) {
            $this->forge->dropColumn('equity_ingresos_contratos', 'fecha_vencimiento');
        }
    }
}
