<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Baja lógica de bienes raíces (activo=0). Conserva IDs / bitácora.
 * Additive — no wipe.
 */
class AddActivoFlagBienesRaices extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_bienes_raices')) {
            return;
        }
        if ($this->db->fieldExists('activo', 'equity_bienes_raices')) {
            return;
        }

        $this->forge->addColumn('equity_bienes_raices', [
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
                'after'      => 'persona_id',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_bienes_raices')
            && $this->db->fieldExists('activo', 'equity_bienes_raices')
        ) {
            $this->forge->dropColumn('equity_bienes_raices', 'activo');
        }
    }
}
