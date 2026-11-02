<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Control de cambios para documentos de tipo borrador. */
class DocumentoCambioModel extends Model
{
    protected $table            = 'documento_cambios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'documento_id', 'actor_id', 'resumen', 'detalle', 'created_at',
    ];
}
