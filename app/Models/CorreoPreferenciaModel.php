<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Preferencia de sección destino para auto-ingreso de Correo. */
class CorreoPreferenciaModel extends Model
{
    protected $table            = 'correo_preferencias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = ['id', 'usuario_id', 'empresa_id', 'seccion_id'];

    public function findFor(string $usuarioId, string $empresaId): ?array
    {
        $row = $this->where('usuario_id', $usuarioId)
            ->where('empresa_id', $empresaId)
            ->first();

        return $row ?: null;
    }

    public function upsertSeccion(string $usuarioId, string $empresaId, string $seccionId): string
    {
        helper('id');

        $existing = $this->findFor($usuarioId, $empresaId);
        if ($existing !== null) {
            $this->update($existing['id'], ['seccion_id' => $seccionId]);

            return (string) $existing['id'];
        }

        $id = new_id();
        $this->insert([
            'id'         => $id,
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'seccion_id' => $seccionId,
        ]);

        return $id;
    }
}
