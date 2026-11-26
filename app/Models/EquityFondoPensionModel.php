<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityFondoPensionModel extends Model
{
    protected $table            = 'equity_fondos_pension';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'afp', 'tipo_fondo', 'tipo_cuenta',
        'numero_cuenta', 'saldo_uf', 'saldo_clp', 'notas', 'creado_por_id',
    ];
}
