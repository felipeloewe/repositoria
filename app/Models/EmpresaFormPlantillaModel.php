<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Plantilla de formulario bajo menú Formulario (empresa / ambito general). */
class EmpresaFormPlantillaModel extends Model
{
    protected $table            = 'empresa_form_plantillas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'clave_sistema', 'nombre', 'descripcion', 'activo', 'creado_por_id',
    ];
}
