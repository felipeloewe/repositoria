<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityPeriodoCierreModel extends Model
{
    protected $table            = 'equity_periodos_cierre';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'anio', 'mes', 'fecha_cierre', 'uf_valor_clp',
        'estado', 'cerrado_por_id', 'cerrado_at', 'vb_por_id', 'vb_at', 'vb_nota', 'notas',
    ];
}
