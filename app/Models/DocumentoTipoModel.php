<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Mantenedor de tipos de documento (borrador | oficial). */
class DocumentoTipoModel extends Model
{
    protected $table            = 'documento_tipos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'nombre', 'codigo', 'modalidad', 'descripcion', 'activo',
    ];
}
