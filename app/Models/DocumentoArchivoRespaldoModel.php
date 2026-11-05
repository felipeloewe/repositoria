<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Respaldos ZIP mensuales de documentos archivados. */
class DocumentoArchivoRespaldoModel extends Model
{
    protected $table            = 'documento_archivo_respaldos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'periodo', 'ruta_zip', 'tamano_bytes',
        'docs_count', 'creado_por_id', 'created_at',
        'descargado_at', 'descargado_por_id',
    ];
}
