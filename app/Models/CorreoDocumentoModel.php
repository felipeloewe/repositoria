<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Proveniencia idempotente mail → documento. */
class CorreoDocumentoModel extends Model
{
    protected $table            = 'correo_documentos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'documento_id', 'usuario_id',
        'thread_id', 'attachment_id', 'kind', 'source_key',
    ];

    public function findBySourceKey(string $sourceKey): ?array
    {
        $row = $this->where('source_key', $sourceKey)->first();

        return $row ?: null;
    }
}
