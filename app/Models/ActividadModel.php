<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ActividadModel extends Model
{
    protected $table            = 'actividades';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['id', 'empresa_id', 'departamento_id', 'nombre'];
}
