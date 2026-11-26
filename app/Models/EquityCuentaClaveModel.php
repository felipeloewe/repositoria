<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Bitácora de claves de internet (cifradas) por cuenta corriente. */
class EquityCuentaClaveModel extends Model
{
    protected $table            = 'equity_cuenta_claves';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'cuenta_id', 'empresa_id', 'persona_id',
        'fecha_inicio', 'vigencia_hasta', 'estado', 'secreto_cipher', 'creado_por_id',
    ];
}
