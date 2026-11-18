<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Persona Equity (antecedentes personales / laborales) por empresa. */
class EquityPersonaModel extends Model
{
    protected $table            = 'equity_personas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'tipo', 'nombre', 'apellido', 'apellido_materno', 'rut', 'email', 'cargo',
        'usuario_id', 'activo', 'notas', 'creado_por_id',
        // personales
        'fecha_nacimiento', 'sexo', 'nacionalidad', 'nacionalidad_detalle', 'grupo_familiar_n',
        'estado_civil', 'regimen_patrimonial', 'lugar_habita',
        'antiguedad_domicilio_meses', 'antiguedad_domicilio_anos',
        'nivel_estudios', 'profesion', 'universidad_instituto',
        'dir_calle', 'dir_numero', 'dir_otros', 'dir_comuna', 'dir_ciudad', 'dir_region',
        'telefono_particular', 'telefono_celular',
        // laborales
        'tipo_actividad', 'tipo_contrato', 'tipo_renta',
        'fecha_ingreso_mes', 'fecha_ingreso_anio',
        'antiguedad_cargo_meses', 'antiguedad_cargo_anos',
        'rut_empleador', 'nombre_empleador', 'giro_empleador', 'renta_liquida_persona',
        'dir_com_calle', 'dir_com_numero', 'dir_com_otros',
        'dir_com_comuna', 'dir_com_ciudad', 'dir_com_region',
        'tel_comercial', 'tel_anexo',
        'segunda_actividad', 'segunda_actividad_mes', 'segunda_actividad_anio', 'renta_liquida_2',
        'empleo_ant_cargo', 'empleo_ant_empleador',
        'empleo_ant_ingreso_mes', 'empleo_ant_ingreso_anio',
        'empleo_ant_salida_mes', 'empleo_ant_salida_anio',
        'empleo_ant_renta',
        'complementa_renta',
    ];
}


