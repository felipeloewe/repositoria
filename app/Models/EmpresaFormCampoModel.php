<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Campo de plantilla de formulario de empresa. */
class EmpresaFormCampoModel extends Model
{
    protected $table            = 'empresa_form_campos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'plantilla_id', 'clave', 'etiqueta', 'tipo', 'obligatorio', 'orden', 'created_at',
    ];
}
