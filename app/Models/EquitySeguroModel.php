<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquitySeguroModel extends Model
{
    protected $table            = 'equity_seguros';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'tipo_seguro', 'compania', 'numero_poliza',
        'materia', 'monto_asegurado_clp', 'prima_mensual_clp', 'prima_anual_clp',
        'vigencia_desde', 'vigencia_hasta', 'estado', 'notas', 'creado_por_id',
    ];
}
