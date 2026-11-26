<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Antecedentes personales + laborales en equity_personas.
 * Additive — no wipe.
 */
class AddEquityAntecedentesFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_personas')) {
            return;
        }

        $fields = [
            // Personales
            'apellido_materno'           => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'fecha_nacimiento'           => ['type' => 'DATE', 'null' => true],
            'sexo'                       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'nacionalidad'               => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'nacionalidad_detalle'       => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'grupo_familiar_n'           => ['type' => 'INT', 'null' => true],
            'estado_civil'               => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'regimen_patrimonial'        => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
            'lugar_habita'               => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'antiguedad_domicilio_meses' => ['type' => 'INT', 'null' => true],
            'antiguedad_domicilio_anos'  => ['type' => 'INT', 'null' => true],
            'nivel_estudios'             => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'profesion'                  => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'universidad_instituto'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dir_calle'                  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dir_numero'                 => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'dir_otros'                  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_comuna'                 => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_ciudad'                 => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_region'                 => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'telefono_particular'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'telefono_celular'           => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            // Laborales
            'tipo_actividad'             => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'tipo_contrato'              => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'tipo_renta'                 => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'fecha_ingreso_mes'          => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
            'fecha_ingreso_anio'         => ['type' => 'SMALLINT', 'null' => true],
            'antiguedad_cargo_meses'     => ['type' => 'INT', 'null' => true],
            'antiguedad_cargo_anos'      => ['type' => 'INT', 'null' => true],
            'rut_empleador'              => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'nombre_empleador'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'giro_empleador'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'renta_liquida_persona'      => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            'dir_com_calle'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'dir_com_numero'             => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'dir_com_otros'              => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_com_comuna'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_com_ciudad'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'dir_com_region'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'tel_comercial'              => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'tel_anexo'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'segunda_actividad'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'segunda_actividad_mes'      => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
            'segunda_actividad_anio'     => ['type' => 'SMALLINT', 'null' => true],
            'renta_liquida_2'            => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            // Empleo anterior
            'empleo_ant_cargo'           => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'empleo_ant_empleador'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'empleo_ant_ingreso_mes'     => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
            'empleo_ant_ingreso_anio'    => ['type' => 'SMALLINT', 'null' => true],
            'empleo_ant_salida_mes'      => ['type' => 'TINYINT', 'constraint' => 2, 'null' => true],
            'empleo_ant_salida_anio'     => ['type' => 'SMALLINT', 'null' => true],
            'empleo_ant_renta'           => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => true],
            // Cónyuge / conviviente (grupo familiar)
            'complementa_renta'          => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
        ];



        foreach ($fields as $name => $def) {
            if (! $this->db->fieldExists($name, 'equity_personas')) {
                $this->forge->addColumn('equity_personas', [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_personas')) {
            return;
        }

        foreach ([
            'apellido_materno', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'nacionalidad_detalle',
            'grupo_familiar_n', 'estado_civil', 'regimen_patrimonial', 'lugar_habita',
            'antiguedad_domicilio_meses', 'antiguedad_domicilio_anos', 'nivel_estudios',
            'profesion', 'universidad_instituto', 'dir_calle', 'dir_numero', 'dir_otros',
            'dir_comuna', 'dir_ciudad', 'dir_region', 'telefono_particular', 'telefono_celular',
            'tipo_actividad', 'tipo_contrato', 'tipo_renta', 'fecha_ingreso_mes', 'fecha_ingreso_anio',
            'antiguedad_cargo_meses', 'antiguedad_cargo_anos', 'rut_empleador', 'nombre_empleador',
            'giro_empleador', 'renta_liquida_persona', 'dir_com_calle', 'dir_com_numero', 'dir_com_otros',
            'dir_com_comuna', 'dir_com_ciudad', 'dir_com_region', 'tel_comercial', 'tel_anexo',
            'segunda_actividad', 'segunda_actividad_mes', 'segunda_actividad_anio', 'renta_liquida_2',
            'empleo_ant_cargo', 'empleo_ant_empleador', 'empleo_ant_ingreso_mes', 'empleo_ant_ingreso_anio',
            'empleo_ant_salida_mes', 'empleo_ant_salida_anio', 'empleo_ant_renta',
            'complementa_renta',
        ] as $col) {


            if ($this->db->fieldExists($col, 'equity_personas')) {
                $this->forge->dropColumn('equity_personas', $col);
            }
        }
    }
}
