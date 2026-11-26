<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bitácora cifrada: claves internet (cuentas) + PIN cajero (tarjetas).
 * PIN de bóveda en usuarios para desbloquear. Additive — no wipe.
 */
class CreateEquitySecretosBancarios extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('usuarios')) {
            $cols = [
                'vault_pin_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'vault_salt' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'vault_wrapped_dek' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'vault_pin_set_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ];
            foreach ($cols as $name => $def) {
                if (! $this->db->fieldExists($name, 'usuarios')) {
                    $this->forge->addColumn('usuarios', [$name => $def]);
                }
            }
        }

        if (! $this->db->tableExists('equity_cuenta_claves')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'cuenta_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'empresa_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'persona_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'fecha_inicio' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'vigencia_hasta' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'estado' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'vigente',
                ],
                'secreto_cipher' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'creado_por_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['cuenta_id'], false, false, 'equity_cuenta_claves_cuenta');
            $this->forge->addKey(['persona_id'], false, false, 'equity_cuenta_claves_persona');
            $this->forge->createTable('equity_cuenta_claves', true);
        }

        if (! $this->db->tableExists('equity_tarjeta_pines')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'tarjeta_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'empresa_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'persona_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'fecha_inicio' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'vigencia_hasta' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'estado' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'vigente',
                ],
                'secreto_cipher' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'creado_por_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tarjeta_id'], false, false, 'equity_tarjeta_pines_tarjeta');
            $this->forge->addKey(['persona_id'], false, false, 'equity_tarjeta_pines_persona');
            $this->forge->createTable('equity_tarjeta_pines', true);
        }
    }

    public function down(): void
    {
        foreach (['equity_tarjeta_pines', 'equity_cuenta_claves'] as $t) {
            if ($this->db->tableExists($t)) {
                $this->forge->dropTable($t, true);
            }
        }
        if ($this->db->tableExists('usuarios')) {
            foreach (['vault_pin_set_at', 'vault_wrapped_dek', 'vault_salt', 'vault_pin_hash'] as $col) {
                if ($this->db->fieldExists($col, 'usuarios')) {
                    $this->forge->dropColumn('usuarios', $col);
                }
            }
        }
    }
}
