<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Modalidad de tipo + ciclo de vida con hilo de renovación.
 *
 * Tipos de catálogo (Borrador, En revisión, …, Vigente, Obsoleto) ≠ estados.
 * Tipo Vigente → modalidad oficial; oficiales viven en un hilo de renovación.
 * Borradores también versionan archivo.
 */
final class DocumentLifecycle
{
    public const MODALIDAD_BORRADOR = 'borrador';
    public const MODALIDAD_OFICIAL  = 'oficial';

    public const TIPO_CODIGO_BORRADOR    = 'BORRADOR';
    public const TIPO_CODIGO_EN_REVISION = 'EN_REVISION';
    public const TIPO_CODIGO_APROBADO    = 'APROBADO';
    public const TIPO_CODIGO_VIGENTE     = 'VIGENTE';
    public const TIPO_CODIGO_OBSOLETO    = 'OBSOLETO';

    /** Estados del ciclo (oficiales / hilo de renovación). */
    public const EN_ELABORACION = 'EN_ELABORACION';
    public const VIGENTE        = 'VIGENTE';
    public const VENCIDO        = 'VENCIDO';
    public const RENOVADO       = 'RENOVADO';
    public const ARCHIVADO      = 'ARCHIVADO';

    /** @deprecated Use VIGENTE — alias del MVP Publicado */
    public const PUBLICADO = self::VIGENTE;

    public const ESTADOS = [
        self::EN_ELABORACION,
        self::VIGENTE,
        self::VENCIDO,
        self::RENOVADO,
        self::ARCHIVADO,
    ];

    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        self::EN_ELABORACION => [self::VIGENTE, self::ARCHIVADO],
        self::VIGENTE        => [self::VENCIDO, self::ARCHIVADO, self::EN_ELABORACION],
        self::VENCIDO        => [self::ARCHIVADO],
        self::RENOVADO       => [self::ARCHIVADO],
        self::ARCHIVADO      => [],
    ];

    /**
     * @return list<array{nombre: string, codigo: string, modalidad: string, descripcion: string}>
     */
    public static function catalogoTiposSemilla(): array
    {
        return [
            [
                'nombre'      => 'Borrador',
                'codigo'      => self::TIPO_CODIGO_BORRADOR,
                'modalidad'   => self::MODALIDAD_BORRADOR,
                'descripcion' => 'Borrador: versiones, control de cambios y fecha término.',
            ],
            [
                'nombre'      => 'En revisión',
                'codigo'      => self::TIPO_CODIGO_EN_REVISION,
                'modalidad'   => self::MODALIDAD_BORRADOR,
                'descripcion' => 'En revisión (modalidad borrador en seed).',
            ],
            [
                'nombre'      => 'Aprobado (en firmas)',
                'codigo'      => self::TIPO_CODIGO_APROBADO,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Aprobado en firmas (modalidad oficial en seed).',
            ],
            [
                'nombre'      => 'Vigente (oficial)',
                'codigo'      => self::TIPO_CODIGO_VIGENTE,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Vigente oficial: hilo de renovación, versiones y vencimientos.',
            ],
            [
                'nombre'      => 'Obsoleto / Archivado',
                'codigo'      => self::TIPO_CODIGO_OBSOLETO,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Obsoleto/archivado (modalidad oficial en seed).',
            ],
        ];
    }

    public static function labelEstado(string $estado): string
    {
        return match (self::normalizarEstado($estado)) {
            self::EN_ELABORACION => 'En elaboración',
            self::VIGENTE        => 'Vigente',
            self::VENCIDO        => 'Vencido',
            self::RENOVADO       => 'Renovado',
            self::ARCHIVADO      => 'Archivado',
            default              => $estado,
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

    public static function normalizarEstado(?string $estado): string
    {
        $estado = (string) $estado;

        return match ($estado) {
            self::EN_ELABORACION, self::VIGENTE, self::VENCIDO, self::RENOVADO, self::ARCHIVADO => $estado,
            'PUBLICADO' => self::VIGENTE,
            'BORRADOR', 'EN_REVISION', 'APROBADO', '' => self::EN_ELABORACION,
            'OBSOLETO' => self::ARCHIVADO,
            default    => self::EN_ELABORACION,
        };
    }

    /**
     * @return list<string>
     */
    public static function siguientes(string $estado): array
    {
        return self::TRANSICIONES[self::normalizarEstado($estado)] ?? [];
    }

    public static function puedeTransicionar(string $desde, string $hasta): bool
    {
        return in_array($hasta, self::siguientes($desde), true);
    }

    public static function puedeActorTransicionar(array $memb, string $hasta): bool
    {
        if (! Roles::canUploadDocs($memb['rol'])) {
            return false;
        }

        // Publicar a vigente, marcar vencido, renovado o archivar → Master
        if (in_array($hasta, [self::VIGENTE, self::VENCIDO, self::RENOVADO, self::ARCHIVADO], true)) {
            return Roles::isMaster($memb['rol']);
        }

        return true;
    }

    public static function estaPorVencer(array $doc): bool
    {
        if (self::normalizarEstado($doc['estado'] ?? null) !== self::VIGENTE) {
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
        $estado = self::normalizarEstado($doc['estado'] ?? null);
        if ($estado === self::VENCIDO) {
            return true;
        }
        if ($estado !== self::VIGENTE) {
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
        return match (self::normalizarEstado($estado)) {
            self::EN_ELABORACION => 'estado-elaboracion',
            self::VIGENTE        => 'estado-publicado',
            self::VENCIDO        => 'estado-vencido-ciclo',
            self::RENOVADO       => 'estado-renovado',
            self::ARCHIVADO      => 'estado-archivado',
            default              => 'estado-elaboracion',
        };
    }

    public static function puedeVersionar(string $estado): bool
    {
        // Ambas modalidades versionan; estados terminales no
        $e = self::normalizarEstado($estado);

        return ! in_array($e, [self::ARCHIVADO, self::RENOVADO], true);
    }

    public static function puedeRenovar(array $doc, ?array $tipo): bool
    {
        if (! self::isOficial($tipo)) {
            return false;
        }
        $e = self::normalizarEstado($doc['estado'] ?? null);

        return in_array($e, [self::VIGENTE, self::VENCIDO], true);
    }
}
