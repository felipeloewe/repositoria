<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityActivoValorModel extends Model
{
    protected $table            = 'equity_activo_valores';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'activo_tipo', 'activo_id', 'campo',
        'fecha_cierre', 'valor_uf', 'valor_clp', 'cambiado_por_id', 'nota',
    ];
}
