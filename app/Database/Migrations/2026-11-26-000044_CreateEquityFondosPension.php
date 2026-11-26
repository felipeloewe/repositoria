<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Activos · Fondos de pensión (AFP / multifondos).
 * Referencia patrimonial: no suma a Balance, SF ni cierre hasta que se pida expresamente.
 */
class CreateEquityFondosPension extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('equity_fondos_pension')) {
            return;
        }

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
            'afp' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'tipo_fondo' => [
                'type'       => 'VARCHAR',
                'constraint' => 8,
                'null'       => true,
                'comment'    => 'Multifondo A–E',
            ],
            'tipo_cuenta' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'null'       => true,
                'comment'    => '1 obligatoria · 2 APV · 3 Cuenta 2 · 4 depósito convenido · 5 APVC · 6 otro',
            ],
            'numero_cuenta' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'saldo_uf' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,4',
                'null'       => true,
            ],
            'saldo_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'notas' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['empresa_id', 'persona_id']);
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('equity_fondos_pension', true);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_fondos_pension')) {
            $this->forge->dropTable('equity_fondos_pension', true);
        }
    }
}
