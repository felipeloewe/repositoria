<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Cuenta corriente / bancaria de antecedentes. */
class EquityPersonaCuentaModel extends Model
{
    protected $table            = 'equity_persona_cuentas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'banco_id', 'empresa_id', 'persona_id', 'tipo', 'moneda',
        'ultimos_digitos', 'en_google_wallet', 'en_gpay', 'en_link', 'notas',
    ];
}
