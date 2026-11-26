<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Tarjeta de crédito en antecedentes bancarios. */
class EquityPersonaTarjetaModel extends Model
{
    protected $table            = 'equity_persona_tarjetas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'banco_id', 'empresa_id', 'persona_id', 'marca', 'tipo_producto',
        'monto_aprobado_clp', 'monto_aprobado_usd', 'fecha_vencimiento', 'ultimos_4',
        'en_google_wallet', 'en_gpay', 'notas',
    ];
}
