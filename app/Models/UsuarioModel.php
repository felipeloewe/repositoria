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
    protected $allowedFields    = ['id', 'email', 'nombre', 'rut', 'password_hash', 'activo'];

    public function findByEmail(string $email): ?array
    {
        $row = $this->where('email', $email)->first();

        return $row ?: null;
    }
}
