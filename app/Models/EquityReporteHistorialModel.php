<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityReporteHistorialModel extends Model
{
    protected $table            = 'equity_reportes_historial';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'periodo_id', 'empresa_id', 'persona_id', 'tipo', 'titulo',
        'payload_json', 'uf_valor_clp', 'generado_por_id', 'created_at',
    ];
}
