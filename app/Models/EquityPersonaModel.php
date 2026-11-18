<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Persona Equity (socio o gerente) por empresa. */
class EquityPersonaModel extends Model
{
    protected $table            = 'equity_personas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'tipo', 'nombre', 'apellido', 'rut', 'email', 'cargo',
        'usuario_id', 'activo', 'notas', 'creado_por_id',
    ];
}
