<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Ciclo de vida de documentos oficiales + modalidad de tipo.
 */
final class DocumentLifecycle
{
    public const MODALIDAD_BORRADOR = 'borrador';
    public const MODALIDAD_OFICIAL  = 'oficial';

    public const BORRADOR    = 'BORRADOR';
    public const EN_REVISION = 'EN_REVISION';
    public const APROBADO    = 'APROBADO';
    public const VIGENTE     = 'VIGENTE';
    public const OBSOLETO    = 'OBSOLETO';

    public const ESTADOS = [
        self::BORRADOR,
        self::EN_REVISION,
        self::APROBADO,
        self::VIGENTE,
        self::OBSOLETO,
    ];

    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        self::BORRADOR    => [self::EN_REVISION],
        self::EN_REVISION => [self::BORRADOR, self::APROBADO],
        self::APROBADO    => [self::EN_REVISION, self::VIGENTE],
        self::VIGENTE     => [self::OBSOLETO],
        self::OBSOLETO    => [],
    ];

    public static function labelEstado(string $estado): string
    {
        return match ($estado) {
            self::BORRADOR    => 'Borrador',
            self::EN_REVISION => 'En revisión',
            self::APROBADO    => 'Aprobado (en firmas)',
            self::VIGENTE     => 'Vigente (oficial)',
            self::OBSOLETO    => 'Obsoleto / Archivado',
            default           => $estado,
        };
    }

    public static function labelModalidad(string $modalidad): string
    {
        return match ($modalidad) {
            self::MODALIDAD_BORRADOR => 'Borrador',
            self::MODALIDAD_OFICIAL  => 'Oficial',
            default                  => $modalidad,
        };
    }

    public static function isOficial(?array $tipo): bool
    {
        return ($tipo['modalidad'] ?? '') === self::MODALIDAD_OFICIAL;
    }

    public static function isBorradorTipo(?array $tipo): bool
    {
        return ($tipo['modalidad'] ?? '') === self::MODALIDAD_BORRADOR;
    }

    /**
     * @return list<string>
     */
    public static function siguientes(string $estado): array
    {
        return self::TRANSICIONES[$estado] ?? [];
    }

    public static function puedeTransicionar(string $desde, string $hasta): bool
    {
        return in_array($hasta, self::siguientes($desde), true);
    }

    /**
     * Master puede publicar (→ VIGENTE) y archivar (→ OBSOLETO).
     * Jefe / Master con ACL de carga pueden el resto.
     */
    public static function puedeActorTransicionar(array $memb, string $hasta): bool
    {
        if (! Roles::canUploadDocs($memb['rol'])) {
            return false;
        }

        if (in_array($hasta, [self::VIGENTE, self::OBSOLETO], true)) {
            return Roles::isMaster($memb['rol']);
        }

        return true;
    }

    /**
     * Stub de alerta: vigente y dentro de la ventana alerta_dias antes de vence_at.
     */
    public static function estaPorVencer(array $doc): bool
    {
        if (($doc['estado'] ?? '') !== self::VIGENTE) {
            return false;
        }
        $vence = $doc['vence_at'] ?? null;
        if ($vence === null || $vence === '') {
            return false;
        }
        $dias = isset($doc['alerta_dias']) && $doc['alerta_dias'] !== null && $doc['alerta_dias'] !== ''
            ? (int) $doc['alerta_dias']
            : 30;
        if ($dias < 0) {
            $dias = 0;
        }

        $hoy    = strtotime(date('Y-m-d'));
        $limite = strtotime((string) $vence . ' -' . $dias . ' days');
        $fin    = strtotime((string) $vence);
        if ($hoy === false || $limite === false || $fin === false) {
            return false;
        }

        return $hoy >= $limite && $hoy <= $fin;
    }

    public static function estaVencido(array $doc): bool
    {
        if (($doc['estado'] ?? '') !== self::VIGENTE) {
            return false;
        }
        $vence = $doc['vence_at'] ?? null;
        if ($vence === null || $vence === '') {
            return false;
        }
        $hoy = strtotime(date('Y-m-d'));
        $fin = strtotime((string) $vence);

        return $hoy !== false && $fin !== false && $hoy > $fin;
    }

    public static function cssEstado(string $estado): string
    {
        return match ($estado) {
            self::BORRADOR    => 'estado-borrador',
            self::EN_REVISION => 'estado-revision',
            self::APROBADO    => 'estado-aprobado',
            self::VIGENTE     => 'estado-vigente',
            self::OBSOLETO    => 'estado-obsoleto',
            default           => 'estado-borrador',
        };
    }
}
