<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityUfValorModel extends Model
{
    protected $table            = 'equity_uf_valores';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'fecha_cierre', 'valor_clp', 'notas', 'creado_por_id',
    ];
}
