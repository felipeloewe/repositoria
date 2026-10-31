<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class TemaInteresModel extends Model
{
    protected $table            = 'temas_interes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['id', 'empresa_id', 'departamento_id', 'nombre'];
}
