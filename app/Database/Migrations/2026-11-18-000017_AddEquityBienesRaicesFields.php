<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Campos booklet: Bienes Raíces, Vehículos, Sociedades. Additive. */
class AddEquityBienesRaicesFields extends Migration
{
    public function up(): void
    {
        $this->addCols('equity_bienes_raices', [
            'tipo_inmueble' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'direccion'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'garantia'      => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'asegurado'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
        ]);

        $this->addCols('equity_vehiculos', [
            'tipo_vehiculo' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'garantia'      => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
        ]);

        $this->addCols('equity_sociedades', [
            'rut_sociedad'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'giro_rubro'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'emp_relacionada'   => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'cumple_articulo_84'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);

        $this->addCols('equity_otros_activos', [
            'tipo_activo' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->dropCols('equity_bienes_raices', ['tipo_inmueble', 'direccion', 'garantia', 'asegurado']);
        $this->dropCols('equity_vehiculos', ['tipo_vehiculo', 'garantia']);
        $this->dropCols('equity_sociedades', ['rut_sociedad', 'giro_rubro', 'emp_relacionada', 'cumple_articulo_84']);
        $this->dropCols('equity_otros_activos', ['tipo_activo']);
    }


    /** @param array<string, array<string, mixed>> $fields */
    private function addCols(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        foreach ($fields as $name => $def) {
            if (! $this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $def]);
            }
        }
    }

    /** @param list<string> $cols */
    private function dropCols(string $table, array $cols): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        foreach ($cols as $col) {
            if ($this->db->fieldExists($col, $table)) {
                $this->forge->dropColumn($table, $col);
            }
        }
    }
}
