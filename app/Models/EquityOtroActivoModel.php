<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityOtroActivoModel extends Model
{
    protected $table            = 'equity_otros_activos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'tipo', 'tipo_activo', 'institucion', 'saldo_clp', 'saldo_uf',
        'notas', 'creado_por_id',
    ];
}
