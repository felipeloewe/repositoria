<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityPasivoModel extends Model
{
    protected $table            = 'equity_pasivos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'categoria', 'banco_descripcion',
        'saldo_clp', 'saldo_uf', 'cuotas_totales', 'cuotas_pagadas', 'cuota_mensual',
        'asociacion', 'tipo', 'notas', 'creado_por_id',
        'numero_credito', 'sucursal', 'moneda', 'dividendo_n', 'fecha_registro', 'plazo_pactado',
        'amortizacion_uf', 'amortizacion_clp', 'interes_uf', 'interes_clp',
        'comision_uf', 'comision_clp', 'seg_incendio_uf', 'seg_incendio_clp',
        'seg_desgravamen_uf', 'seg_desgravamen_clp',
        'seg_adicionales_uf', 'seg_adicionales_clp',
        'seg_otros_complementarios_uf', 'seg_otros_complementarios_clp',
        'dividendo_uf', 'dividendo_clp', 'fecha_vencimiento', 'direccion_garantia',
        'seguros_info', 'cuenta_pac', 'dia_cargo_pac',
        'monto_directo_uf', 'monto_directo_clp', 'monto_indirecto_uf', 'monto_indirecto_clp',
        'fecha_otorgamiento', 'total_credito_uf', 'total_credito_clp',
        'vigente_uf', 'vigente_clp', 'mora_30_59_uf', 'mora_30_59_clp',
        'mora_60_89_uf', 'mora_60_89_clp', 'mora_90_mas_uf', 'mora_90_mas_clp',
        'tomador_nombre', 'tomador_rut',
        'desarrollo_archivo_nombre', 'desarrollo_archivo_ruta',
        'desarrollo_archivo_mime', 'desarrollo_archivo_bytes',
    ];
}
