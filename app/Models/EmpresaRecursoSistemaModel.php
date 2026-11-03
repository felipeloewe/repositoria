<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** On/off por empresa de recursos genéricos de sistema. */
class EmpresaRecursoSistemaModel extends Model
{
    protected $table            = 'empresa_recurso_sistema';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'recurso_tipo', 'recurso_clave', 'activo', 'created_at', 'updated_at',
    ];
}
