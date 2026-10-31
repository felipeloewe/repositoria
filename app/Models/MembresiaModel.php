<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MembresiaModel extends Model
{
    protected $table            = 'membresias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['id', 'empresa_id', 'usuario_id', 'rol', 'sucursal_id', 'activa'];

    /**
     * Active memberships for a user, with empresa + sucursal labels.
     *
     * @return list<array<string, mixed>>
     */
    public function forUsuario(string $usuarioId): array
    {
        return $this->db->table('membresias m')
            ->select('m.*, e.nombre AS empresa_nombre, e.slug AS empresa_slug, s.nombre AS sucursal_nombre')
            ->join('empresas e', 'e.id = m.empresa_id')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->where('m.usuario_id', $usuarioId)
            ->where('m.activa', 1)
            ->where('e.activa', 1)
            ->orderBy('e.nombre', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function findActive(string $membresiaId): ?array
    {
        $row = $this->db->table('membresias m')
            ->select('m.*, e.nombre AS empresa_nombre, e.slug AS empresa_slug, s.nombre AS sucursal_nombre, s.departamento_id AS departamento_id, u.nombre AS usuario_nombre, u.email AS usuario_email')
            ->join('empresas e', 'e.id = m.empresa_id')
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->where('m.id', $membresiaId)
            ->where('m.activa', 1)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
