<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'email', 'nombre', 'alias', 'cargo', 'rut',
        'password_hash', 'activo', 'es_admin_central',
        'email_verified_at', 'email_verify_token', 'email_verify_sent_at', 'must_change_password',
        'deactivated_at', 'pii_scrubbed_at',
    ];

    public function findByEmail(string $email): ?array
    {
        $row = $this->where('email', $email)->first();

        return $row ?: null;
    }

    public function findByAlias(string $alias): ?array
    {
        $row = $this->where('alias', $alias)->first();

        return $row ?: null;
    }
}
