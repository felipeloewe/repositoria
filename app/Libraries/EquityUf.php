<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityUfValorModel;

/**
 * UF al cierre de mes: montos patrimoniales se fijan en UF;
 * el equivalente en $ se recalcula con el valor vigente.
 */
final class EquityUf
{
    /** Fallback si la empresa aún no carga valores (cierre 31.10.2026). */
    public const DEFAULT_VALOR_CLP = 41247.63;

    public const DEFAULT_FECHA_CIERRE = '2026-10-31';

    /**
     * Último valor UF con fecha_cierre <= $hasta (o el más reciente si $hasta es null).
     *
     * @return array{fecha_cierre: string, valor_clp: float}|null
     */
    public static function vigente(string $empresaId, ?string $hasta = null): ?array
    {
        if (! db_connect()->tableExists('equity_uf_valores')) {
            return self::fallbackRow();
        }

        $q = model(EquityUfValorModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha_cierre', 'DESC');
        if ($hasta !== null && $hasta !== '') {
            $q->where('fecha_cierre <=', $hasta);
        }
        $row = $q->first();
        if ($row === null) {
            return self::fallbackRow();
        }

        return [
            'fecha_cierre' => (string) $row['fecha_cierre'],
            'valor_clp'    => (float) $row['valor_clp'],
        ];
    }

    public static function valorClp(string $empresaId, ?string $hasta = null): float
    {
        $v = self::vigente($empresaId, $hasta);

        return $v !== null ? (float) $v['valor_clp'] : self::DEFAULT_VALOR_CLP;
    }

    public static function ufAPesos(?float $uf, string $empresaId, ?string $hasta = null): ?float
    {
        if ($uf === null) {
            return null;
        }
        $rate = self::valorClp($empresaId, $hasta);
        if ($rate <= 0) {
            return null;
        }

        return round($uf * $rate, 2);
    }

    public static function pesosAUf(?float $pesos, string $empresaId, ?string $hasta = null): ?float
    {
        if ($pesos === null) {
            return null;
        }
        $rate = self::valorClp($empresaId, $hasta);
        if ($rate <= 0) {
            return null;
        }

        return round($pesos / $rate, 4);
    }

    /**
     * Resuelve par UF/$: UF es canónico; $ se deriva si hay UF.
     * Si solo viene $, se deriva UF con la tasa vigente.
     *
     * @return array{uf: ?string, clp: ?string}
     */
    public static function resolverPar(?string $ufRaw, ?string $clpRaw, string $empresaId, ?string $hasta = null): array
    {
        $uf  = self::parseDecimal($ufRaw, 4);
        $clp = self::parseDecimal($clpRaw, 2);
        $rate = self::valorClp($empresaId, $hasta);

        if ($uf !== null) {
            $clpCalc = $rate > 0 ? round($uf * $rate, 2) : $clp;

            return [
                'uf'  => number_format($uf, 4, '.', ''),
                'clp' => $clpCalc !== null ? number_format($clpCalc, 2, '.', '') : null,
            ];
        }

        if ($clp !== null) {
            $ufCalc = $rate > 0 ? round($clp / $rate, 4) : null;

            return [
                'uf'  => $ufCalc !== null ? number_format($ufCalc, 4, '.', '') : null,
                'clp' => number_format($clp, 2, '.', ''),
            ];
        }

        return ['uf' => null, 'clp' => null];
    }

    /** Formato chileno para $ (con decimales opcionales). */
    public static function fmtPesos(?float $v, int $dec = 0): string
    {
        if ($v === null) {
            return '—';
        }

        return '$' . number_format($v, $dec, ',', '.');
    }

    public static function fmtUf(?float $v, int $dec = 2): string
    {
        if ($v === null) {
            return '—';
        }

        return number_format($v, $dec, ',', '.') . ' UF';
    }

    /** Último día del mes YYYY-MM. */
    public static function cierreDeMes(int $anio, int $mes): string
    {
        $mes = max(1, min(12, $mes));
        $ts  = mktime(0, 0, 0, $mes + 1, 0, $anio);

        return date('Y-m-d', $ts !== false ? $ts : time());
    }

    public static function parseDecimal(?string $raw, int $scale = 2): ?float
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }
        if (! is_numeric($v)) {
            return null;
        }

        return round((float) $v, $scale);
    }

    /** @return array{fecha_cierre: string, valor_clp: float} */
    private static function fallbackRow(): array
    {
        return [
            'fecha_cierre' => self::DEFAULT_FECHA_CIERRE,
            'valor_clp'    => self::DEFAULT_VALOR_CLP,
        ];
    }
}
