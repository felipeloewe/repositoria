<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquitySociedadModel extends Model
{
    protected $table            = 'equity_sociedades';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'nombre_sociedad', 'tipo', 'porcentaje',
        'valor_clp', 'valor_uf', 'pais_detalle', 'notas', 'creado_por_id',
        'rut_sociedad', 'giro_rubro', 'emp_relacionada', 'cumple_articulo_84',
    ];
}
