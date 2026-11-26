<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Antecedentes bancarios por persona: bancos → cuentas corrientes + tarjetas.
 * Additive — no wipe.
 */
class CreateEquityAntecedentesBancarios extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_persona_bancos')) {
            $this->forge->addField([
                'id' => [
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
                'banco_nombre' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                ],
                'notas' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
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
            $this->forge->addKey(['persona_id', 'banco_nombre'], false, false, 'equity_bancos_persona_nombre');
            $this->forge->addKey(['empresa_id', 'persona_id'], false, false, 'equity_bancos_empresa_persona');
            $this->forge->createTable('equity_persona_bancos', true);
        }

        if (! $this->db->tableExists('equity_persona_cuentas')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'banco_id' => [
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
                'tipo' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'corriente',
                ],
                'moneda' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 8,
                    'default'    => 'CLP',
                ],
                'numero_cuenta' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'en_google_wallet' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'en_gpay' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'en_link' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'notas' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
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
            $this->forge->addKey(['banco_id'], false, false, 'equity_cuentas_banco');
            $this->forge->addKey(['persona_id'], false, false, 'equity_cuentas_persona');
            $this->forge->createTable('equity_persona_cuentas', true);
        }

        if (! $this->db->tableExists('equity_persona_tarjetas')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'banco_id' => [
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
                'marca' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                ],
                'tipo_producto' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'monto_aprobado_clp' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'monto_aprobado_usd' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '18,2',
                    'null'       => true,
                ],
                'fecha_vencimiento' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 7,
                    'null'       => true,
                ],
                'ultimos_4' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 4,
                    'null'       => true,
                ],
                'en_google_wallet' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'en_gpay' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'en_link' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'notas' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
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
            $this->forge->addKey(['banco_id'], false, false, 'equity_tarjetas_banco');
            $this->forge->addKey(['persona_id'], false, false, 'equity_tarjetas_persona');
            $this->forge->createTable('equity_persona_tarjetas', true);
        }
    }

    public function down(): void
    {
        foreach (['equity_persona_tarjetas', 'equity_persona_cuentas', 'equity_persona_bancos'] as $t) {
            if ($this->db->tableExists($t)) {
                $this->forge->dropTable($t, true);
            }
        }
    }
}
