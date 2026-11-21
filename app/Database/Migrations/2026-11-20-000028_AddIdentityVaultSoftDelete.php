<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Soft-delete + scrub markers for identity vault (usuarios / empresas / personas).
 * Additive — no wipe.
 */
class AddIdentityVaultSoftDelete extends Migration
{
    public function up(): void
    {
        $cols = [
            'deactivated_at'  => ['type' => 'DATETIME', 'null' => true],
            'pii_scrubbed_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        foreach (['usuarios', 'empresas', 'equity_personas'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            foreach ($cols as $name => $def) {
                if (! $this->db->fieldExists($name, $table)) {
                    $this->forge->addColumn($table, [$name => $def]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['usuarios', 'empresas', 'equity_personas'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            foreach (['deactivated_at', 'pii_scrubbed_at'] as $col) {
                if ($this->db->fieldExists($col, $table)) {
                    $this->forge->dropColumn($table, $col);
                }
            }
        }
    }
}
