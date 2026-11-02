<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Versiones de documentos oficiales. */
class DocumentoVersionModel extends Model
{
    protected $table            = 'documento_versiones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'documento_id', 'version_num',
        'nombre_archivo', 'ruta_relativa', 'mime', 'tamano_bytes',
        'notas', 'creado_por_id', 'created_at',
    ];
}
