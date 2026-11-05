<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DocumentoArchivoRespaldoModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoSolicitudModel;
use RuntimeException;

/**
 * Solicitudes de archivo / baja por no-Master; validación Master (y Jefe en archivo).
 */
final class DocumentoSolicitudService
{
    public const TIPO_ARCHIVO = 'ARCHIVO';
    public const TIPO_BAJA    = 'BAJA';

    public const PENDIENTE  = 'PENDIENTE';
    public const APROBADA   = 'APROBADA';
    public const RECHAZADA  = 'RECHAZADA';
    public const CANCELADA  = 'CANCELADA';

    public static function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_ARCHIVO => 'Archivo',
            self::TIPO_BAJA    => 'Baja',
            default            => $tipo,
        };
    }

    public static function labelEstado(string $estado): string
    {
        return match ($estado) {
            self::PENDIENTE => 'Pendiente',
            self::APROBADA  => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
            self::CANCELADA => 'Cancelada',
            default         => $estado,
        };
    }

    /** @param array<string, mixed> $memb */
    public static function isMaster(array $memb): bool
    {
        return Roles::isMaster((string) ($memb['rol'] ?? ''));
    }

    /** Non-master with view on the doc. */
    public static function canRequestForDoc(array $memb, array $doc): bool
    {
        if (self::isMaster($memb)) {
            return false;
        }

        return DocumentAcl::canView($memb, $doc);
    }

    /** @param array<string, mixed> $memb */
    public static function canValidateArchivo(array $memb): bool
    {
        $rol = (string) ($memb['rol'] ?? '');

        return Roles::isMaster($rol) || $rol === Roles::JEFE_SUCURSAL;
    }

    /** @param array<string, mixed> $memb */
    public static function canValidateBaja(array $memb): bool
    {
        return self::isMaster($memb);
    }

    /**
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $doc
     */
    public function solicitar(array $memb, array $doc, string $tipo, ?string $nota = null): array
    {
        helper('id');
        $tipo = strtoupper($tipo);
        if (! in_array($tipo, [self::TIPO_ARCHIVO, self::TIPO_BAJA], true)) {
            throw new RuntimeException('Tipo de solicitud inválido.');
        }
        if (! self::canRequestForDoc($memb, $doc)) {
            throw new RuntimeException('No puedes solicitar archivo/baja sobre este documento.');
        }

        $estadoDoc = DocumentLifecycle::normalizarEstado($doc['estado'] ?? null);
        if ($tipo === self::TIPO_ARCHIVO && $estadoDoc === DocumentLifecycle::ARCHIVADO) {
            throw new RuntimeException('El documento ya está archivado.');
        }

        $db = db_connect();
        $pending = $db->table('documento_solicitudes')
            ->where('documento_id', $doc['id'])
            ->where('tipo', $tipo)
            ->where('estado', self::PENDIENTE)
            ->countAllResults();
        if ($pending > 0) {
            throw new RuntimeException('Ya hay una solicitud ' . self::labelTipo($tipo) . ' pendiente para este documento.');
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'id'                       => new_id(),
            'empresa_id'               => $doc['empresa_id'],
            'documento_id'             => $doc['id'],
            'tipo'                     => $tipo,
            'estado'                   => self::PENDIENTE,
            'solicitante_usuario_id'   => $memb['usuario_id'],
            'solicitante_membresia_id' => $memb['id'] ?? null,
            'sucursal_id'              => $memb['sucursal_id'] ?? ($doc['sucursal_id'] ?? null),
            'departamento_id'          => $memb['departamento_id'] ?? ($doc['departamento_id'] ?? null),
            'nota_solicitud'           => $nota !== null && trim($nota) !== '' ? trim($nota) : null,
            'created_at'               => $now,
            'updated_at'               => $now,
        ];
        model(DocumentoSolicitudModel::class)->insert($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $solicitud
     */
    public function resolver(array $memb, array $solicitud, bool $aprobar, ?string $nota = null): array
    {
        if (($solicitud['estado'] ?? '') !== self::PENDIENTE) {
            throw new RuntimeException('La solicitud ya fue resuelta.');
        }

        $tipo = (string) $solicitud['tipo'];
        if ($tipo === self::TIPO_ARCHIVO && ! self::canValidateArchivo($memb)) {
            throw new RuntimeException('Solo Master o Jefe pueden validar archivo.');
        }
        if ($tipo === self::TIPO_BAJA && ! self::canValidateBaja($memb)) {
            throw new RuntimeException('Solo Master puede validar bajas.');
        }
        if ((string) ($memb['empresa_id'] ?? '') !== (string) ($solicitud['empresa_id'] ?? '')) {
            throw new RuntimeException('Solicitud de otra empresa.');
        }

        $doc = model(DocumentoModel::class)->find($solicitud['documento_id']);
        if ($doc === null) {
            throw new RuntimeException('Documento no encontrado.');
        }

        $respaldoId = null;
        if ($aprobar && $tipo === self::TIPO_BAJA) {
            $respaldo = $this->ultimoRespaldoDescargado((string) $memb['empresa_id']);
            if ($respaldo === null) {
                throw new RuntimeException(
                    'Para aprobar una baja debes haber descargado antes un respaldo ZIP (Documentos → Respaldo).'
                );
            }
            $respaldoId = $respaldo['id'];
        }

        $now = date('Y-m-d H:i:s');
        if ($aprobar) {
            if ($tipo === self::TIPO_ARCHIVO) {
                (new DocumentArchiveService())->archivar(
                    $memb,
                    $doc,
                    $nota ?: ('Archivo aprobado · solicitud ' . $solicitud['id'])
                );
            } else {
                $this->eliminarDocumento($memb, $doc);
            }
        }

        model(DocumentoSolicitudModel::class)->update($solicitud['id'], [
            'estado'               => $aprobar ? self::APROBADA : self::RECHAZADA,
            'validador_usuario_id' => $memb['usuario_id'],
            'validado_at'          => $now,
            'nota_validacion'      => $nota !== null && trim($nota) !== '' ? trim($nota) : null,
            'respaldo_id'          => $respaldoId,
            'updated_at'           => $now,
        ]);

        return model(DocumentoSolicitudModel::class)->find($solicitud['id']) ?? $solicitud;
    }

    /** @return array<string, mixed>|null */
    public function ultimoRespaldoDescargado(string $empresaId): ?array
    {
        $row = model(DocumentoArchivoRespaldoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('descargado_at !=', null)
            ->orderBy('descargado_at', 'DESC')
            ->first();

        return $row ?: null;
    }

    /**
     * Hard-delete alineado a Documentos::delete (sin HTTP).
     *
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $doc
     */
    private function eliminarDocumento(array $memb, array $doc): void
    {
        if (! self::isMaster($memb) && ! DocumentAcl::canDelete($memb, $doc)) {
            throw new RuntimeException('Sin permiso para eliminar.');
        }

        $id = (string) $doc['id'];

        $versiones = model(\App\Models\DocumentoVersionModel::class)->where('documento_id', $id)->findAll();
        foreach ($versiones as $v) {
            try {
                DocumentStorage::delete((string) ($v['ruta_relativa'] ?? ''));
            } catch (\Throwable) {
            }
        }

        model(\App\Models\DocumentoAutorizacionModel::class)->where('documento_id', $id)->delete();
        model(\App\Models\DocumentoVersionModel::class)->where('documento_id', $id)->delete();
        model(\App\Models\DocumentoEstadoHistorialModel::class)->where('documento_id', $id)->delete();
        model(\App\Models\DocumentoCambioModel::class)->where('documento_id', $id)->delete();
        model(DocumentoModel::class)->delete($id);
        try {
            DocumentStorage::delete((string) ($doc['ruta_relativa'] ?? ''));
        } catch (\Throwable) {
        }
    }
}
