<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Fase 2 stub — schema + model only. */
class DocumentoSeccionModel extends Model
{
    protected $table            = 'documento_secciones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'departamento_id', 'sucursal_id', 'nombre', 'codigo',
    ];
}
