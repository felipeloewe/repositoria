<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Instancia rellenada de un formulario de empresa. */
class EmpresaFormInstanciaModel extends Model
{
    protected $table            = 'empresa_form_instancias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'plantilla_id', 'titulo', 'estado', 'respuestas_json',
        'documento_id', 'origen', 'filled_by_usuario_id', 'share_id', 'completed_at',
    ];
}
