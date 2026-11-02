<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Repositorio documental (Fase 2). */
class DocumentoModel extends Model
{
    protected $table            = 'documentos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'id', 'empresa_id', 'departamento_id', 'sucursal_id', 'seccion_id',
        'tipo_id', 'estado', 'version_actual', 'vence_at', 'alerta_dias', 'fecha_termino', 'notas_ciclo',
        'renueva_de_id', 'cadena_id',
        'cabecera_partes', 'cabecera_asunto', 'cabecera_inicio', 'cabecera_termino', 'cabecera_autorizantes',
        'titulo', 'nombre_archivo', 'ruta_relativa', 'mime', 'tamano_bytes', 'subido_por_id',
    ];
}
