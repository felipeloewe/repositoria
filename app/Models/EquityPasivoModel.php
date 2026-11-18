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
    ];
}
