<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fecha de registro del dividendo hipotecario (periodo al que corresponden
 * los valores del recibo actual). Permite distinguir oct/nov/dic y más adelante
 * normalizar a un mes de corte y proyectar vencimientos (Egresos).
 * Additive — no wipe; no altera saldos ni fechas existentes.
 */
class AddFechaRegistroHipotecario extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }
        if ($this->db->fieldExists('fecha_registro', 'equity_pasivos')) {
            return;
        }

        $this->forge->addColumn('equity_pasivos', [
            'fecha_registro' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'dividendo_n',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_pasivos')
            && $this->db->fieldExists('fecha_registro', 'equity_pasivos')
        ) {
            $this->forge->dropColumn('equity_pasivos', 'fecha_registro');
        }
    }
}
