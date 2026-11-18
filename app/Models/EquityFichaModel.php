<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Ficha financiera de una persona Equity. */
class EquityFichaModel extends Model
{
    protected $table            = 'equity_fichas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'fecha_corte', 'moneda',
        'renta_liquida', 'ingreso_fijo', 'ingreso_honorarios', 'ingreso_arriendos',
        'ingreso_variable', 'ingreso_comisiones', 'ingreso_pensiones', 'ingreso_bonos',
        'ingreso_otros', 'ingreso_mensual_total',
        'activos_total', 'pasivos_total', 'patrimonio_neto',
        'notas', 'creado_por_id',
    ];
}
