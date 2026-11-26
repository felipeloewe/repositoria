<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Bitácora de PIN de cajero (cifrado) por tarjeta de crédito. */
class EquityTarjetaPinModel extends Model
{
    protected $table            = 'equity_tarjeta_pines';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'tarjeta_id', 'empresa_id', 'persona_id',
        'fecha_inicio', 'vigencia_hasta', 'estado', 'secreto_cipher', 'creado_por_id',
    ];
}
