<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Equity (equity.booklet.cl): personas (socio/gerente), fichas financieras y respaldos.
 * Pool documental ambito=equity. Additive — no wipe.
 */
class CreateEquitySchema extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_personas')) {
            $this->forge->addField([
                'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
                'tipo'            => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'socio'],
                'nombre'          => ['type' => 'VARCHAR', 'constraint' => 120],
                'apellido'        => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'rut'             => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'email'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'cargo'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'usuario_id'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'activo'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'notas'           => ['type' => 'TEXT', 'null' => true],
                'creado_por_id'   => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'activo']);
            $this->forge->addKey(['empresa_id', 'tipo']);
            $this->forge->addKey('usuario_id');
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('equity_personas', true);
        }

        if (! $this->db->tableExists('equity_fichas')) {
            $this->forge->addField([
                'id'                    => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'fecha_corte'           => ['type' => 'DATE', 'null' => true],
                'moneda'                => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'CLP'],
                'renta_liquida'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_fijo'          => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_honorarios'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_arriendos'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_variable'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_comisiones'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_pensiones'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_bonos'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_otros'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'ingreso_mensual_total' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'activos_total'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'pasivos_total'         => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'patrimonio_neto'       => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'notas'                 => ['type' => 'TEXT', 'null' => true],
                'creado_por_id'         => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'            => ['type' => 'DATETIME', 'null' => true],
                'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addKey(['persona_id', 'fecha_corte']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('equity_fichas', true);
        }

        if (! $this->db->tableExists('equity_respaldos')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'ficha_id'      => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'documento_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
                'tipo_respaldo' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'descripcion'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addKey('documento_id');
            $this->forge->addKey('ficha_id');
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('ficha_id', 'equity_fichas', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('documento_id', 'documentos', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('equity_respaldos', true);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_respaldos')) {
            $this->forge->dropTable('equity_respaldos', true);
        }
        if ($this->db->tableExists('equity_fichas')) {
            $this->forge->dropTable('equity_fichas', true);
        }
        if ($this->db->tableExists('equity_personas')) {
            $this->forge->dropTable('equity_personas', true);
        }
    }
}
