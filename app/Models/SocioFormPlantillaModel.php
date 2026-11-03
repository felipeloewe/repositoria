<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Plantilla de formulario bajo Menú Socios. */
class SocioFormPlantillaModel extends Model
{
    protected $table            = 'socio_form_plantillas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'nombre', 'descripcion', 'activo', 'creado_por_id',
    ];
}
