<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Proyecta vencimientos / cuotas mensuales de pasivos para el menú Egresos.
 * Análisis siempre en pesos: si el crédito es UF se convierte con la UF vigente;
 * si es CLP se usa el $ y se muestra la equivalencia UF.
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
     *   saldo_clp: float|null,
     *   amortizacion_uf: float|null
     * }>
     */
    public static function proyectar(array $row, float $ufRate, int $meses = 12): array
    {
        $meses = max(1, min(60, $meses));
        $categoria = (string) ($row['categoria'] ?? '');

        // Hipotecarios, circulantes y otros usan la misma proyección por dividendo / cuota.
        if (in_array($categoria, ['inmueble', 'circulante', 'otro'], true)) {
            $porDividendo = self::proyectarHipotecario($row, $ufRate, $meses);
            if ($porDividendo !== []) {
                return $porDividendo;
            }

            return self::proyectarCuotaFija($row, $ufRate, $meses);
        }
        if ($categoria === 'linea') {
            return [];
        }

        return self::proyectarCuotaFija($row, $ufRate, $meses);
    }

    /**
     * Moneda del pasivo: UF (default hipotecarios) o CLP.
     */
    public static function moneda(array $row): string
    {
        $m = strtoupper(trim((string) ($row['moneda'] ?? '')));
        if ($m === 'CLP' || $m === '$' || $m === 'PESOS') {
            return 'CLP';
        }

        return 'UF';
    }

    /**
     * Cuota mensual en pesos para análisis (Ingresos/Egresos).
     * UF canónico → UF × tasa vigente; CLP canónico → $ guardado (o deriva desde UF).
     */
    public static function cuotaClp(array $row, float $ufRate): ?float
    {
        $moneda = self::moneda($row);
        $divUf  = self::num($row, 'dividendo_uf');
        $divClp = self::num($row, 'dividendo_clp');
        $cuota  = self::num($row, 'cuota_mensual');

        if ($moneda === 'UF') {
            if ($divUf !== null && $divUf > 0 && $ufRate > 0) {
                return round($divUf * $ufRate, 2);
            }
            if ($divClp !== null && $divClp > 0) {
                return round($divClp, 2);
            }
            if ($cuota !== null && $cuota > 0) {
                return round($cuota, 2);
            }

            return null;
        }

        // CLP canónico
        if ($divClp !== null && $divClp > 0) {
            return round($divClp, 2);
        }
        if ($cuota !== null && $cuota > 0) {
            return round($cuota, 2);
        }
        if ($divUf !== null && $divUf > 0 && $ufRate > 0) {
            return round($divUf * $ufRate, 2);
        }

        return null;
    }

    /**
     * Equivalencia UF de la cuota (para mostrar junto al $ de análisis).
     */
    public static function cuotaUf(array $row, float $ufRate): ?float
    {
        $moneda = self::moneda($row);
        $divUf  = self::num($row, 'dividendo_uf');
        if ($moneda === 'UF' && $divUf !== null && $divUf > 0) {
            return round($divUf, 4);
        }
        $clp = self::cuotaClp($row, $ufRate);
        if ($clp !== null && $ufRate > 0) {
            return round($clp / $ufRate, 4);
        }
        if ($divUf !== null && $divUf > 0) {
            return round($divUf, 4);
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

        $saldoUf = self::num($row, 'saldo_uf');
        if ($saldoUf === null) {
            $saldoClpOnly = self::num($row, 'saldo_clp');
            if ($saldoClpOnly !== null && $ufRate > 0) {
                $saldoUf = round($saldoClpOnly / $ufRate, 4);
            }
        }
        $amort = self::num($row, 'amortizacion_uf') ?? 0.0;
        $divUf = self::cuotaUf($row, $ufRate);
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
            if ($saldoUf !== null && $saldoUf <= 0) {
                break;
            }

            $fecha = $base->modify('+' . $i . ' month');
            // Recalcular $ de análisis con UF vigente (cuota en UF × tasa).
            $cuotaClpMes = $divClp;
            if ($divUf !== null && $ufRate > 0 && self::moneda($row) === 'UF') {
                $cuotaClpMes = round($divUf * $ufRate, 2);
            }
            $out[] = [
                'fecha'           => $fecha->format('Y-m-d'),
                'dividendo_n'     => $divN,
                'cuota_uf'        => $divUf,
                'cuota_clp'       => $cuotaClpMes,
                'saldo_uf'        => $saldoUf !== null ? round($saldoUf, 4) : null,
                'saldo_clp'       => ($saldoUf !== null && $ufRate > 0) ? round($saldoUf * $ufRate, 2) : null,
                'amortizacion_uf' => $amort > 0 ? $amort : null,
            ];

            if ($saldoUf !== null && $amort > 0) {
                $saldoUf = max(0, round($saldoUf - $amort, 4));
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
        $cuotaClp = self::cuotaClp($row, $ufRate);
        if ($cuotaClp === null || $cuotaClp <= 0) {
            return [];
        }
        $cuotaUf = self::cuotaUf($row, $ufRate);

        $base = self::fechaBase($row);
        if ($base === null) {
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
                'cuota_uf'        => $cuotaUf,
                'cuota_clp'       => $cuotaClp,
                'saldo_uf'        => null,
                'saldo_clp'       => null,
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

    /**
     * @param array<string, mixed> $row
     */
    private static function num(array $row, string $key): ?float
    {
        if (! isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
            return null;
        }

        return (float) $row[$key];
    }
}
