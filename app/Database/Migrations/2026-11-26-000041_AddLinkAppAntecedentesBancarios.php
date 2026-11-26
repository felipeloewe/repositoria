<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Referencia a app LINK (pasarela) en cuentas y tarjetas.
 * Additive — no wipe.
 */
class AddLinkAppAntecedentesBancarios extends Migration
{
    public function up(): void
    {
        $col = [
            'en_link' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'en_gpay',
            ],
        ];

        foreach (['equity_persona_cuentas', 'equity_persona_tarjetas'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            if (! $this->db->fieldExists('en_link', $table)) {
                $this->forge->addColumn($table, $col);
            }
        }
    }

    public function down(): void
    {
        foreach (['equity_persona_cuentas', 'equity_persona_tarjetas'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('en_link', $table)) {
                $this->forge->dropColumn($table, 'en_link');
            }
        }
    }
}
