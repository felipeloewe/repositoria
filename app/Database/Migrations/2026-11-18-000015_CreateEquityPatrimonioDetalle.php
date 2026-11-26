<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Detalle patrimonial Equity (activos / pasivos) por persona.
 * Additive — no wipe.
 */
class CreateEquityPatrimonioDetalle extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_bienes_raices')) {
            $this->forge->addField([
                'id'              => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'      => ['type' => 'VARCHAR', 'constraint' => 36],
                'descripcion'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'valor_clp'       => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'valor_uf'        => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
                'fecha_tasacion'  => ['type' => 'DATE', 'null' => true],
                'rol'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'avaluo_fiscal'   => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'contribuciones'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'notas'           => ['type' => 'TEXT', 'null' => true],
                'creado_por_id'   => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_bienes_raices', true);
        }

        if (! $this->db->tableExists('equity_vehiculos')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'tipo'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'marca'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'modelo'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'anio'          => ['type' => 'INT', 'null' => true],
                'patente'       => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'valor_clp'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'asegurado'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'notas'         => ['type' => 'TEXT', 'null' => true],
                'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_vehiculos', true);
        }

        if (! $this->db->tableExists('equity_sociedades')) {
            $this->forge->addField([
                'id'               => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
                'nombre_sociedad'  => ['type' => 'VARCHAR', 'constraint' => 255],
                'tipo'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'Directo'],
                'porcentaje'       => ['type' => 'DECIMAL', 'constraint' => '8,4', 'null' => true],
                'valor_clp'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'valor_uf'         => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
                'pais_detalle'     => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'notas'            => ['type' => 'TEXT', 'null' => true],
                'creado_por_id'    => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_sociedades', true);
        }

        if (! $this->db->tableExists('equity_otros_activos')) {
            $this->forge->addField([
                'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
                'tipo'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'institucion'   => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'saldo_clp'     => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'notas'         => ['type' => 'TEXT', 'null' => true],
                'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_otros_activos', true);
        }

        if (! $this->db->tableExists('equity_pasivos')) {
            $this->forge->addField([
                'id'               => ['type' => 'VARCHAR', 'constraint' => 36],
                'empresa_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
                'persona_id'       => ['type' => 'VARCHAR', 'constraint' => 36],
                'categoria'        => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'otro'],
                'banco_descripcion'=> ['type' => 'VARCHAR', 'constraint' => 255],
                'saldo_clp'        => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'saldo_uf'         => ['type' => 'DECIMAL', 'constraint' => '18,4', 'null' => true],
                'cuotas_totales'   => ['type' => 'INT', 'null' => true],
                'cuotas_pagadas'   => ['type' => 'INT', 'null' => true],
                'cuota_mensual'    => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
                'asociacion'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'tipo'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'notas'            => ['type' => 'TEXT', 'null' => true],
                'creado_por_id'    => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['empresa_id', 'persona_id']);
            $this->forge->addKey(['empresa_id', 'categoria']);
            $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('persona_id', 'equity_personas', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('equity_pasivos', true);
        }
    }

    public function down(): void
    {
        foreach (['equity_pasivos', 'equity_otros_activos', 'equity_sociedades', 'equity_vehiculos', 'equity_bienes_raices'] as $t) {
            if ($this->db->tableExists($t)) {
                $this->forge->dropTable($t, true);
            }
        }
    }
}
