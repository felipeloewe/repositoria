<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Cuota / línea de desarrollo editable de un pasivo. */
class EquityPasivoCuotaModel extends Model
{
    protected $table            = 'equity_pasivo_cuotas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'pasivo_id', 'empresa_id', 'persona_id', 'fecha', 'dividendo_n', 'moneda',
        'cuota_uf', 'cuota_clp', 'amortizacion_uf', 'amortizacion_clp',
        'interes_uf', 'interes_clp', 'saldo_uf', 'saldo_clp', 'fuente',
    ];
}
