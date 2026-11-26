<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Banco registrado en antecedentes bancarios de una persona. */
class EquityPersonaBancoModel extends Model
{
    protected $table            = 'equity_persona_bancos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'persona_id', 'banco_nombre', 'notas', 'creado_por_id',
    ];
}
