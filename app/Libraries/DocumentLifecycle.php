<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Modalidad de tipo (borrador|oficial) + ciclo de vida provisional de oficiales.
 *
 * Los nombres «Borrador / En revisión / Aprobado… / Vigente… / Obsoleto…» son
 * TIPOS de documento (catálogo), no estados. Estados oficiales = tríada provisional
 * hasta que Felipe confirme el ciclo definitivo.
 */
final class DocumentLifecycle
{
    public const MODALIDAD_BORRADOR = 'borrador';
    public const MODALIDAD_OFICIAL  = 'oficial';

    /** Catálogo semilla de tipos (códigos). */
    public const TIPO_CODIGO_BORRADOR    = 'BORRADOR';
    public const TIPO_CODIGO_EN_REVISION = 'EN_REVISION';
    public const TIPO_CODIGO_APROBADO    = 'APROBADO';
    public const TIPO_CODIGO_VIGENTE     = 'VIGENTE';
    public const TIPO_CODIGO_OBSOLETO    = 'OBSOLETO';

    /**
     * Estados provisionales del ciclo (oficiales) — NO reutilizar etiquetas del catálogo de tipos.
     */
    public const EN_ELABORACION = 'EN_ELABORACION';
    public const PUBLICADO      = 'PUBLICADO';
    public const ARCHIVADO      = 'ARCHIVADO';

    public const ESTADOS = [
        self::EN_ELABORACION,
        self::PUBLICADO,
        self::ARCHIVADO,
    ];

    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        self::EN_ELABORACION => [self::PUBLICADO],
        self::PUBLICADO      => [self::EN_ELABORACION, self::ARCHIVADO],
        self::ARCHIVADO      => [],
    ];

    /**
     * Catálogo semilla: nombre, código, modalidad sugerida.
     *
     * @return list<array{nombre: string, codigo: string, modalidad: string, descripcion: string}>
     */
    public static function catalogoTiposSemilla(): array
    {
        return [
            [
                'nombre'      => 'Borrador',
                'codigo'      => self::TIPO_CODIGO_BORRADOR,
                'modalidad'   => self::MODALIDAD_BORRADOR,
                'descripcion' => 'Tipo borrador: control de cambios y fecha de término.',
            ],
            [
                'nombre'      => 'En revisión',
                'codigo'      => self::TIPO_CODIGO_EN_REVISION,
                'modalidad'   => self::MODALIDAD_BORRADOR,
                'descripcion' => 'Tipo en revisión (modalidad borrador en seed).',
            ],
            [
                'nombre'      => 'Aprobado (en firmas)',
                'codigo'      => self::TIPO_CODIGO_APROBADO,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Tipo aprobado en firmas (modalidad oficial en seed).',
            ],
            [
                'nombre'      => 'Vigente (oficial)',
                'codigo'      => self::TIPO_CODIGO_VIGENTE,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Tipo vigente oficial: ciclo, versiones y vencimientos.',
            ],
            [
                'nombre'      => 'Obsoleto / Archivado',
                'codigo'      => self::TIPO_CODIGO_OBSOLETO,
                'modalidad'   => self::MODALIDAD_OFICIAL,
                'descripcion' => 'Tipo obsoleto/archivado (modalidad oficial en seed).',
            ],
        ];
    }

    public static function labelEstado(string $estado): string
    {
        return match ($estado) {
            self::EN_ELABORACION => 'En elaboración',
            self::PUBLICADO      => 'Publicado',
            self::ARCHIVADO      => 'Archivado',
            // Legado (MVP previo que confundió tipos con estados)
            'BORRADOR'    => 'En elaboración',
            'EN_REVISION' => 'En elaboración',
            'APROBADO'    => 'En elaboración',
            'VIGENTE'     => 'Publicado',
            'OBSOLETO'    => 'Archivado',
            default       => $estado,
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
     * Normaliza estados legados del MVP anterior hacia la tríada provisional.
     */
    public static function normalizarEstado(?string $estado): string
    {
        $estado = (string) $estado;

        return match ($estado) {
            self::EN_ELABORACION, self::PUBLICADO, self::ARCHIVADO => $estado,
            'BORRADOR', 'EN_REVISION', 'APROBADO', '' => self::EN_ELABORACION,
            'VIGENTE'  => self::PUBLICADO,
            'OBSOLETO' => self::ARCHIVADO,
            default    => self::EN_ELABORACION,
        };
    }

    /**
     * @return list<string>
     */
    public static function siguientes(string $estado): array
    {
        $estado = self::normalizarEstado($estado);

        return self::TRANSICIONES[$estado] ?? [];
    }

    public static function puedeTransicionar(string $desde, string $hasta): bool
    {
        return in_array($hasta, self::siguientes($desde), true);
    }

    /**
     * Master publica (→ PUBLICADO) y archiva (→ ARCHIVADO).
     */
    public static function puedeActorTransicionar(array $memb, string $hasta): bool
    {
        if (! Roles::canUploadDocs($memb['rol'])) {
            return false;
        }

        if (in_array($hasta, [self::PUBLICADO, self::ARCHIVADO], true)) {
            return Roles::isMaster($memb['rol']);
        }

        return true;
    }

    /**
     * Stub de alerta: publicado y dentro de la ventana alerta_dias antes de vence_at.
     */
    public static function estaPorVencer(array $doc): bool
    {
        if (self::normalizarEstado($doc['estado'] ?? null) !== self::PUBLICADO) {
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
        if (self::normalizarEstado($doc['estado'] ?? null) !== self::PUBLICADO) {
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
            self::PUBLICADO      => 'estado-publicado',
            self::ARCHIVADO      => 'estado-archivado',
            default              => 'estado-elaboracion',
        };
    }

    /** Estados en los que se permite subir nueva versión (oficial). */
    public static function puedeVersionar(string $estado): bool
    {
        return in_array(self::normalizarEstado($estado), [self::EN_ELABORACION, self::PUBLICADO], true);
    }
}
