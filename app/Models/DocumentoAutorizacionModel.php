<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Fase 2 ACL stub — schema + model only. */
class DocumentoAutorizacionModel extends Model
{
    protected $table            = 'documento_autorizaciones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = ['id', 'empresa_id', 'documento_id', 'usuario_id', 'created_at'];
}
