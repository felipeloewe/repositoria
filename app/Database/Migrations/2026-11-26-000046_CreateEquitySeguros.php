<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menú Seguros (pólizas): flujo propio, fuera de Activos/Pasivos y de reportes.
 */
class CreateEquitySeguros extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('equity_seguros')) {
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
            'tipo_seguro' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'null'       => true,
            ],
            'compania' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'numero_poliza' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'materia' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Bien / materia asegurada',
            ],
            'monto_asegurado_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'prima_mensual_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'prima_anual_clp' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,2',
                'null'       => true,
            ],
            'vigencia_desde' => [
                'type' => 'DATE',
                'null' => true,
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
        $this->forge->createTable('equity_seguros', true);
    }

    public function down(): void
    {
        if ($this->db->tableExists('equity_seguros')) {
            $this->forge->dropTable('equity_seguros', true);
        }
    }
}
