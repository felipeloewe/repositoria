<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bitácora mensual de valores comerciales / avalúo fiscal de activos Equity.
 * Un corte por activo+campo+fecha_cierre (fin de mes). Additive — no wipe.
 */
class CreateEquityActivoValorBitacora extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('equity_activo_valores')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'persona_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
            'activo_tipo'     => ['type' => 'VARCHAR', 'constraint' => 32],
            'activo_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
            'campo'           => ['type' => 'VARCHAR', 'constraint' => 32],
            'fecha_cierre'    => ['type' => 'DATE'],
            'valor_uf'        => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
            'valor_clp'       => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'cambiado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'nota'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['activo_tipo', 'activo_id', 'campo', 'fecha_cierre'], 'uq_equity_activo_valor_corte');
        $this->forge->addKey(['empresa_id', 'persona_id']);
        $this->forge->addKey(['activo_tipo', 'activo_id', 'fecha_cierre']);
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('equity_activo_valores', true);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_activo_valores')) {
            $this->forge->dropTable('equity_activo_valores', true);
        }
    }
}
