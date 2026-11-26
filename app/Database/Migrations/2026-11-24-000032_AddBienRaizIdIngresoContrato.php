<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vincula contratos de ingreso (arriendos) con el bien raíz de origen.
 * Flujo: onboarding carga el bien → sync crea/actualiza contrato → gestión sobre el contrato.
 * Additive — no wipe.
 */
class AddBienRaizIdIngresoContrato extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_ingresos_contratos')) {
            return;
        }
        if ($this->db->fieldExists('bien_raiz_id', 'equity_ingresos_contratos')) {
            return;
        }

        $this->forge->addColumn('equity_ingresos_contratos', [
            'bien_raiz_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'persona_id',
            ],
        ]);
        $this->db->query('ALTER TABLE equity_ingresos_contratos ADD INDEX idx_bien_raiz_id (bien_raiz_id)');
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_ingresos_contratos')
            && $this->db->fieldExists('bien_raiz_id', 'equity_ingresos_contratos')
        ) {
            $this->forge->dropColumn('equity_ingresos_contratos', 'bien_raiz_id');
        }
    }
}
