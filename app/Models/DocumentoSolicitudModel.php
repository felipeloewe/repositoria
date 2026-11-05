<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DocumentoSolicitudModel extends Model
{
    protected $table            = 'documento_solicitudes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'documento_id', 'tipo', 'estado',
        'solicitante_usuario_id', 'solicitante_membresia_id',
        'sucursal_id', 'departamento_id', 'nota_solicitud',
        'validador_usuario_id', 'validado_at', 'nota_validacion',
        'respaldo_id', 'created_at', 'updated_at',
    ];
}
