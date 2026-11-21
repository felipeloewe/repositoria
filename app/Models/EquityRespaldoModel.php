<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Vínculo persona/ficha Equity → documento (ambito=equity). */
class EquityRespaldoModel extends Model
{
    protected $table            = 'equity_respaldos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'ficha_id', 'documento_id',
        'tipo_respaldo', 'descripcion', 'creado_por_id', 'created_at',
    ];
}
