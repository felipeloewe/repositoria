<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EquityBienRaizModel extends Model
{
    protected $table            = 'equity_bienes_raices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'descripcion', 'direccion', 'tipo_inmueble',
        'garantia', 'asegurado', 'valor_clp', 'valor_uf',
        'fecha_tasacion', 'rol', 'avaluo_fiscal', 'contribuciones', 'notas', 'creado_por_id',
        'arrendatario', 'canon_mensual_clp', 'canon_mensual_uf', 'mes_canon', 'medio_pago',
    ];
}

