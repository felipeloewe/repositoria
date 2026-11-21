<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityActivoValorModel;
use DateTimeImmutable;

/**
 * Bitácora de valores comerciales / avalúo fiscal con corte a fin de mes.
 *
 * Si se modifica un valor a mitad de mes, el nuevo monto se atribuye al cierre
 * del mes en curso; el mes anterior conserva el valor previo.
 */
final class EquityActivoBitacora
{
    public const CAMPO_VALOR_COMERCIAL = 'valor_comercial';
    public const CAMPO_AVALUO_FISCAL   = 'avaluo_fiscal';

    public const TIPO_BIENES     = 'bienes_raices';
    public const TIPO_VEHICULOS  = 'vehiculos';
    public const TIPO_SOCIEDADES = 'sociedades';
    public const TIPO_OTROS      = 'otros_activos';

    /** @return list<string> */
    public static function camposParaClave(string $clave): array
    {
        return match ($clave) {
            'bienes-raices' => [self::CAMPO_VALOR_COMERCIAL, self::CAMPO_AVALUO_FISCAL],
            'vehiculos', 'sociedades', 'otros-activos' => [self::CAMPO_VALOR_COMERCIAL],
            default => [],
        };
    }

    public static function tipoDesdeClave(string $clave): ?string
    {
        return match ($clave) {
            'bienes-raices' => self::TIPO_BIENES,
            'vehiculos'     => self::TIPO_VEHICULOS,
            'sociedades'    => self::TIPO_SOCIEDADES,
            'otros-activos' => self::TIPO_OTROS,
            default         => null,
        };
    }

    public static function labelCampo(string $campo): string
    {
        return match ($campo) {
            self::CAMPO_VALOR_COMERCIAL => 'Valor comercial',
            self::CAMPO_AVALUO_FISCAL   => 'Avalúo fiscal',
            default                     => $campo,
        };
    }

    public static function fechaCierreMes(?DateTimeImmutable $ref = null): string
    {
        $ref ??= new DateTimeImmutable('today');

        return $ref->modify('last day of this month')->format('Y-m-d');
    }

    public static function fechaCierreMesAnterior(?DateTimeImmutable $ref = null): string
    {
        $ref ??= new DateTimeImmutable('today');

        return $ref->modify('last day of previous month')->format('Y-m-d');
    }

    /**
     * Extrae el par UF/CLP actual de un registro de activo según el campo.
     *
     * @param array<string, mixed> $row
     *
     * @return array{0: ?string, 1: ?string} [uf, clp] como string decimal o null
     */
    public static function extraerValores(string $clave, string $campo, array $row): array
    {
        if ($campo === self::CAMPO_AVALUO_FISCAL) {
            $clp = $row['avaluo_fiscal'] ?? null;

            return [null, self::normClp($clp)];
        }

        if ($clave === 'otros-activos') {
            return [self::normUf($row['saldo_uf'] ?? null), self::normClp($row['saldo_clp'] ?? null)];
        }

        return [self::normUf($row['valor_uf'] ?? null), self::normClp($row['valor_clp'] ?? null)];
    }

    /**
     * Extrae el par nuevo desde el payload ya resuelto de store.
     *
     * @param array<string, mixed> $data
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function extraerValoresData(string $clave, string $campo, array $data): array
    {
        return self::extraerValores($clave, $campo, $data);
    }

    public static function mismosValores(?string $ufA, ?string $clpA, ?string $ufB, ?string $clpB): bool
    {
        return self::cmpNum($ufA, $ufB) && self::cmpNum($clpA, $clpB);
    }

    /**
     * Registra alta o cambio: upsert del corte del mes en curso;
     * si no hay corte del mes anterior y había valor previo, lo congela ahí.
     *
     * @return bool true si escribió algo
     */
    public static function registrar(
        string $empresaId,
        string $personaId,
        string $activoTipo,
        string $activoId,
        string $campo,
        ?string $ufNuevo,
        ?string $clpNuevo,
        ?string $ufAnterior,
        ?string $clpAnterior,
        ?string $usuarioId,
        ?DateTimeImmutable $ref = null,
        ?string $nota = null
    ): bool {
        if (! db_connect()->tableExists('equity_activo_valores')) {
            return false;
        }

        // Sin cambio (incluye alta sin montos).
        if (self::mismosValores($ufNuevo, $clpNuevo, $ufAnterior, $clpAnterior)) {
            return false;
        }

        $cierreActual   = self::fechaCierreMes($ref);
        $cierreAnterior = self::fechaCierreMesAnterior($ref);
        $model          = model(EquityActivoValorModel::class);
        $wrote          = false;

        // Congelar valor antiguo en el mes anterior si aún no hay corte.
        if ($ufAnterior !== null || $clpAnterior !== null) {
            $prev = $model
                ->where('activo_tipo', $activoTipo)
                ->where('activo_id', $activoId)
                ->where('campo', $campo)
                ->where('fecha_cierre', $cierreAnterior)
                ->first();
            if ($prev === null) {
                helper('id');
                $model->insert([
                    'id'              => new_id(),
                    'empresa_id'      => $empresaId,
                    'persona_id'      => $personaId,
                    'activo_tipo'     => $activoTipo,
                    'activo_id'       => $activoId,
                    'campo'           => $campo,
                    'fecha_cierre'    => $cierreAnterior,
                    'valor_uf'        => $ufAnterior,
                    'valor_clp'       => $clpAnterior,
                    'cambiado_por_id' => $usuarioId,
                    'nota'            => $nota ?? 'Valor congelado al cierre del mes anterior',
                ]);
                $wrote = true;
            }
        }

        if ($ufNuevo === null && $clpNuevo === null) {
            return $wrote;
        }

        $actual = $model
            ->where('activo_tipo', $activoTipo)
            ->where('activo_id', $activoId)
            ->where('campo', $campo)
            ->where('fecha_cierre', $cierreActual)
            ->first();

        if (is_array($actual)) {
            $model->update($actual['id'], [
                'valor_uf'        => $ufNuevo,
                'valor_clp'       => $clpNuevo,
                'persona_id'      => $personaId,
                'cambiado_por_id' => $usuarioId,
                'nota'            => $nota,
            ]);
        } else {
            helper('id');
            $model->insert([
                'id'              => new_id(),
                'empresa_id'      => $empresaId,
                'persona_id'      => $personaId,
                'activo_tipo'     => $activoTipo,
                'activo_id'       => $activoId,
                'campo'           => $campo,
                'fecha_cierre'    => $cierreActual,
                'valor_uf'        => $ufNuevo,
                'valor_clp'       => $clpNuevo,
                'cambiado_por_id' => $usuarioId,
                'nota'            => $nota ?? ($ufAnterior === null && $clpAnterior === null ? 'Valor inicial' : null),
            ]);
        }

        return true;
    }

    /**
     * Aplica bitácora para todos los campos relevantes de una sección de activos.
     *
     * @param array<string, mixed>|null $before fila previa (null en alta)
     * @param array<string, mixed>      $after  datos guardados (con ids)
     */
    public static function sincronizarDesdeStore(
        string $clave,
        string $empresaId,
        string $personaId,
        string $activoId,
        ?array $before,
        array $after,
        ?string $usuarioId
    ): void {
        $tipo = self::tipoDesdeClave($clave);
        $campos = self::camposParaClave($clave);
        if ($tipo === null || $campos === []) {
            return;
        }

        foreach ($campos as $campo) {
            [$ufNew, $clpNew] = self::extraerValoresData($clave, $campo, $after);
            [$ufOld, $clpOld] = $before === null
                ? [null, null]
                : self::extraerValores($clave, $campo, $before);
            self::registrar(
                $empresaId,
                $personaId,
                $tipo,
                $activoId,
                $campo,
                $ufNew,
                $clpNew,
                $ufOld,
                $clpOld,
                $usuarioId
            );
        }
    }

    /**
     * Historial ordenado (más reciente primero) de un activo.
     *
     * @return list<array<string, mixed>>
     */
    public static function historial(string $activoTipo, string $activoId, ?string $campo = null): array
    {
        if (! db_connect()->tableExists('equity_activo_valores')) {
            return [];
        }

        $q = model(EquityActivoValorModel::class)
            ->where('activo_tipo', $activoTipo)
            ->where('activo_id', $activoId)
            ->orderBy('fecha_cierre', 'DESC')
            ->orderBy('campo', 'ASC');
        if ($campo !== null && $campo !== '') {
            $q->where('campo', $campo);
        }

        return $q->findAll();
    }

    /**
     * Valor vigente a una fecha de cierre (última fila con fecha_cierre <= $hasta).
     *
     * @return array{valor_uf: ?float, valor_clp: ?float, fecha_cierre: ?string}|null
     */
    public static function valorAlCierre(string $activoTipo, string $activoId, string $campo, string $hasta): ?array
    {
        if (! db_connect()->tableExists('equity_activo_valores')) {
            return null;
        }

        $row = model(EquityActivoValorModel::class)
            ->where('activo_tipo', $activoTipo)
            ->where('activo_id', $activoId)
            ->where('campo', $campo)
            ->where('fecha_cierre <=', $hasta)
            ->orderBy('fecha_cierre', 'DESC')
            ->first();
        if (! is_array($row)) {
            return null;
        }

        return [
            'valor_uf'     => $row['valor_uf'] !== null && $row['valor_uf'] !== '' ? (float) $row['valor_uf'] : null,
            'valor_clp'    => $row['valor_clp'] !== null && $row['valor_clp'] !== '' ? (float) $row['valor_clp'] : null,
            'fecha_cierre' => (string) $row['fecha_cierre'],
        ];
    }

    private static function normUf(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return number_format((float) $v, 4, '.', '');
    }

    private static function normClp(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return number_format((float) $v, 2, '.', '');
    }

    private static function cmpNum(?string $a, ?string $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        return abs((float) $a - (float) $b) < 0.00005;
    }
}
