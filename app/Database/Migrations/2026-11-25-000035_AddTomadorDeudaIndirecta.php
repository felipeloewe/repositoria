<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tomador del crédito en Deuda Indirecta (nombre/razón social + RUT).
 * Additive — no wipe.
 */
class AddTomadorDeudaIndirecta extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_pasivos')) {
            return;
        }

        $cols = [
            'tomador_nombre' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tomador_rut'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
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
        foreach (['tomador_nombre', 'tomador_rut'] as $col) {
            if ($this->db->fieldExists($col, 'equity_pasivos')) {
                $this->forge->dropColumn('equity_pasivos', $col);
            }
        }
    }
}
