<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Fase 2 stub — schema + model only. */
class DocumentoModel extends Model
{
    protected $table            = 'documentos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'departamento_id', 'sucursal_id', 'seccion_id',
        'titulo', 'nombre_archivo', 'ruta_relativa', 'mime', 'tamano_bytes', 'subido_por_id',
    ];
}
