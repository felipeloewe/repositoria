<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Campo de plantilla de formulario de socios. */
class SocioFormCampoModel extends Model
{
    protected $table            = 'socio_form_campos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'plantilla_id', 'clave', 'etiqueta', 'tipo', 'obligatorio', 'orden', 'seccion', 'created_at',
    ];
}
