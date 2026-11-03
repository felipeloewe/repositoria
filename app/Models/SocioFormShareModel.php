<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Credencial pública (token + password) para rellenar un formulario de socios. */
class SocioFormShareModel extends Model
{
    protected $table            = 'socio_form_shares';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'id', 'empresa_id', 'plantilla_id', 'token', 'password_hash',
        'expira_at', 'max_usos', 'usos', 'revoked_at', 'created_by_id', 'created_at',
    ];
}
