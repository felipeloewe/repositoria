<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Contrato / contraparte de una fuente de ingreso Equity. */
class EquityIngresoContratoModel extends Model
{
    protected $table            = 'equity_ingresos_contratos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'fuente', 'subtipo',
        'contraparte_rut', 'contraparte_nombre', 'fecha_inicio_contrato', 'fecha_vencimiento',
        'periodicidad', 'monto_contrato_clp', 'monto_contrato_uf', 'estado',
        'contrato_archivo_nombre', 'contrato_ruta_relativa', 'contrato_mime', 'contrato_tamano_bytes',
        'notas', 'creado_por_id',
    ];
}
