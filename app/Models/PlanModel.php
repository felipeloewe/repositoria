<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PlanModel extends Model
{
    protected $table            = 'planes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'codigo', 'nombre', 'descripcion', 'tipo_aplicable',
        'max_usuarios', 'max_storage_bytes', 'es_gratuito', 'precio_mensual', 'activo',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('es_gratuito', 'DESC')->orderBy('precio_mensual', 'ASC')->findAll();
    }

    public function findByCodigo(string $codigo): ?array
    {
        $row = $this->where('codigo', $codigo)->first();

        return $row ?: null;
    }
}
