<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityPasivoDesarrolloModel extends Model
{
    protected $table            = 'equity_pasivo_desarrollo';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'periodo_id', 'pasivo_id', 'empresa_id', 'persona_id', 'anio', 'mes',
        'categoria', 'banco_descripcion', 'numero_credito', 'moneda', 'dividendo_n',
        'fecha_cuota', 'cuota_uf', 'cuota_clp', 'amortizacion_uf', 'amortizacion_clp',
        'interes_uf', 'interes_clp', 'saldo_uf', 'saldo_clp', 'uf_valor_clp', 'created_at',
    ];
}
