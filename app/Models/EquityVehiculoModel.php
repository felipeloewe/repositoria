<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityVehiculoModel extends Model
{
    protected $table            = 'equity_vehiculos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'tipo', 'marca', 'modelo', 'anio',
        'patente', 'valor_clp', 'asegurado', 'notas', 'creado_por_id',
    ];
}
