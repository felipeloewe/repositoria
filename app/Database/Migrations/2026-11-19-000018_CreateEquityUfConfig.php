<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Valores UF al cierre de mes + columnas UF faltantes en activos.
 * Additive — no wipe.
 */
class CreateEquityUfConfig extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_uf_valores')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'fecha_cierre'  => ['type' => 'DATE'],
                'valor_clp'     => ['type' => 'DECIMAL', 'constraint' => '18,2'],
                'notas'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey(['empresa_id', 'fecha_cierre']);
            $this->forge->addKey(['empresa_id', 'fecha_cierre']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_uf_valores', true);
        }

        if ($this->db->tableExists('equity_vehiculos') && ! $this->db->fieldExists('valor_uf', 'equity_vehiculos')) {
            $this->forge->addColumn('equity_vehiculos', [
                'valor_uf' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true, 'after' => 'valor_clp'],
            ]);
        }

        if ($this->db->tableExists('equity_otros_activos') && ! $this->db->fieldExists('saldo_uf', 'equity_otros_activos')) {
            $this->forge->addColumn('equity_otros_activos', [
                'saldo_uf' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true, 'after' => 'saldo_clp'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_otros_activos') && $this->db->fieldExists('saldo_uf', 'equity_otros_activos')) {
            $this->forge->dropColumn('equity_otros_activos', 'saldo_uf');
        }
        if ($this->db->tableExists('equity_vehiculos') && $this->db->fieldExists('valor_uf', 'equity_vehiculos')) {
            $this->forge->dropColumn('equity_vehiculos', 'valor_uf');
        }
        if ($this->db->tableExists('equity_uf_valores')) {
            $this->forge->dropTable('equity_uf_valores', true);
        }
    }
}
