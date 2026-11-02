<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Auditoría de transiciones de estado (oficiales). */
class DocumentoEstadoHistorialModel extends Model
{
    protected $table            = 'documento_estado_historial';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'documento_id', 'estado_desde', 'estado_hasta',
        'actor_id', 'nota', 'created_at',
    ];
}
