<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Proyecta vencimientos / cuotas mensuales de pasivos para el menú Egresos.
 * Hipotecarios: parte desde fecha_registro (dividendo actual) y avanza con
 * la misma amortización/dividendo conocidos. Otras categorías: cuota_mensual
 * con day-of-month si hay dia_cargo_pac o fecha_vencimiento.
 */
final class EquityEgresosProyeccion
{
    /**
     * @param array<string, mixed> $row
     * @return list<array{
     *   fecha: string,
     *   dividendo_n: int|null,
     *   cuota_uf: float|null,
     *   cuota_clp: float|null,
     *   saldo_uf: float|null,
     *   amortizacion_uf: float|null
     * }>
     */
    public static function proyectar(array $row, float $ufRate, int $meses = 12): array
    {
        $meses = max(1, min(60, $meses));
        $categoria = (string) ($row['categoria'] ?? '');

        if ($categoria === 'inmueble') {
            return self::proyectarHipotecario($row, $ufRate, $meses);
        }
        if ($categoria === 'linea') {
            return [];
        }

        return self::proyectarCuotaFija($row, $ufRate, $meses);
    }

    /**
     * Cuota mensual en CLP preferida (dividendo / cuota).
     */
    public static function cuotaClp(array $row, float $ufRate): ?float
    {
        foreach (['dividendo_clp', 'cuota_mensual'] as $k) {
            if (isset($row[$k]) && $row[$k] !== null && $row[$k] !== '' && (float) $row[$k] > 0) {
                return round((float) $row[$k], 2);
            }
        }
        if (! empty($row['dividendo_uf']) && $ufRate > 0) {
            return round((float) $row['dividendo_uf'] * $ufRate, 2);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private static function proyectarHipotecario(array $row, float $ufRate, int $meses): array
    {
        $base = self::fechaBase($row);
        if ($base === null) {
            return [];
        }

        $saldo = isset($row['saldo_uf']) && $row['saldo_uf'] !== null && $row['saldo_uf'] !== ''
            ? (float) $row['saldo_uf']
            : null;
        $amort = isset($row['amortizacion_uf']) && $row['amortizacion_uf'] !== null && $row['amortizacion_uf'] !== ''
            ? (float) $row['amortizacion_uf']
            : 0.0;
        $divUf = isset($row['dividendo_uf']) && $row['dividendo_uf'] !== null && $row['dividendo_uf'] !== ''
            ? (float) $row['dividendo_uf']
            : null;
        $divClp = self::cuotaClp($row, $ufRate);
        $divN = isset($row['dividendo_n']) && $row['dividendo_n'] !== null && $row['dividendo_n'] !== ''
            ? (int) $row['dividendo_n']
            : null;
        $plazo = isset($row['plazo_pactado']) && $row['plazo_pactado'] !== null && $row['plazo_pactado'] !== ''
            ? (int) $row['plazo_pactado']
            : (isset($row['cuotas_totales']) && $row['cuotas_totales'] !== null && $row['cuotas_totales'] !== ''
                ? (int) $row['cuotas_totales']
                : null);

        $out = [];
        for ($i = 0; $i < $meses; $i++) {
            if ($divN !== null && $plazo !== null && $divN > $plazo) {
                break;
            }
            if ($saldo !== null && $saldo <= 0) {
                break;
            }

            $fecha = $base->modify('+' . $i . ' month');
            $out[] = [
                'fecha'           => $fecha->format('Y-m-d'),
                'dividendo_n'     => $divN,
                'cuota_uf'        => $divUf,
                'cuota_clp'       => $divClp,
                'saldo_uf'        => $saldo !== null ? round($saldo, 4) : null,
                'amortizacion_uf' => $amort > 0 ? $amort : null,
            ];

            if ($saldo !== null && $amort > 0) {
                $saldo = max(0, round($saldo - $amort, 4));
            }
            if ($divN !== null) {
                $divN++;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private static function proyectarCuotaFija(array $row, float $ufRate, int $meses): array
    {
        $cuota = self::cuotaClp($row, $ufRate);
        if ($cuota === null || $cuota <= 0) {
            return [];
        }

        $base = self::fechaBase($row);
        if ($base === null) {
            // Sin fecha: proyectar desde el día 1 del mes siguiente.
            $base = (new \DateTimeImmutable('first day of next month'))->setTime(0, 0);
            if (! empty($row['dia_cargo_pac'])) {
                $d = max(1, min(28, (int) $row['dia_cargo_pac']));
                $base = $base->setDate((int) $base->format('Y'), (int) $base->format('m'), $d);
            }
        }

        $restantes = null;
        if (isset($row['cuotas_totales'], $row['cuotas_pagadas'])
            && $row['cuotas_totales'] !== null && $row['cuotas_totales'] !== ''
            && $row['cuotas_pagadas'] !== null && $row['cuotas_pagadas'] !== ''
        ) {
            $restantes = max(0, (int) $row['cuotas_totales'] - (int) $row['cuotas_pagadas']);
        }

        $out = [];
        $limit = $restantes !== null ? min($meses, $restantes) : $meses;
        for ($i = 0; $i < $limit; $i++) {
            $fecha = $base->modify('+' . $i . ' month');
            $out[] = [
                'fecha'           => $fecha->format('Y-m-d'),
                'dividendo_n'     => null,
                'cuota_uf'        => $ufRate > 0 ? round($cuota / $ufRate, 4) : null,
                'cuota_clp'       => $cuota,
                'saldo_uf'        => null,
                'amortizacion_uf' => null,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fechaBase(array $row): ?\DateTimeImmutable
    {
        foreach (['fecha_registro', 'fecha_vencimiento'] as $k) {
            $raw = trim((string) ($row[$k] ?? ''));
            if ($raw === '') {
                continue;
            }
            // Si fecha_vencimiento es lejana (fin de crédito), no sirve como base mensual.
            if ($k === 'fecha_vencimiento') {
                try {
                    $dt = new \DateTimeImmutable($raw);
                } catch (\Exception) {
                    continue;
                }
                $hoy = new \DateTimeImmutable('today');
                if ($dt > $hoy->modify('+18 months')) {
                    continue;
                }

                return $dt;
            }
            try {
                return new \DateTimeImmutable($raw);
            } catch (\Exception) {
                continue;
            }
        }

        if (! empty($row['dia_cargo_pac'])) {
            $d = max(1, min(28, (int) $row['dia_cargo_pac']));
            $hoy = new \DateTimeImmutable('today');
            $cand = $hoy->setDate((int) $hoy->format('Y'), (int) $hoy->format('m'), $d);
            if ($cand < $hoy) {
                $cand = $cand->modify('+1 month');
            }

            return $cand;
        }

        return null;
    }
}
