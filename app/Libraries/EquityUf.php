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
     * Resuelve par UF/$ con la tasa vigente.
     * - preferencia `UF`: UF canónico → calcula $
     * - preferencia `CLP`: $ canónico → calcula UF
     * - `auto`: si hay UF se usa como canónico; si no, $
     * Ingresos/Egresos analizan siempre en pesos (usar el `clp` resultante).
     *
     * @param 'auto'|'UF'|'CLP'|string $preferencia
     * @return array{uf: ?string, clp: ?string}
     */
    public static function resolverPar(
        ?string $ufRaw,
        ?string $clpRaw,
        string $empresaId,
        ?string $hasta = null,
        string $preferencia = 'auto'
    ): array {
        $uf   = self::parseDecimal($ufRaw, 4);
        $clp  = self::parseDecimal($clpRaw, 2);
        $rate = self::valorClp($empresaId, $hasta);
        $pref = strtoupper(trim($preferencia));
        if ($pref !== 'UF' && $pref !== 'CLP') {
            $pref = 'auto';
        }

        $useClp = ($pref === 'CLP' && $clp !== null)
            || ($pref === 'auto' && $uf === null && $clp !== null);
        $useUf = ! $useClp && $uf !== null;

        if ($useUf) {
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

        if ($uf !== null) {
            $clpCalc = $rate > 0 ? round($uf * $rate, 2) : null;

            return [
                'uf'  => number_format($uf, 4, '.', ''),
                'clp' => $clpCalc !== null ? number_format($clpCalc, 2, '.', '') : null,
            ];
        }

        return ['uf' => null, 'clp' => null];
    }

    /**
     * Resuelve varios pares UF/$ de componentes de cuota con la misma preferencia de moneda.
     *
     * @param list<string> $bases p.ej. amortizacion, interes, comision
     * @param callable(string): ?string $get Post getter: fn('amortizacion_uf') => raw
     * @return array<string, ?string> keys base_uf / base_clp
     */
    public static function resolverParesComponentes(
        array $bases,
        callable $get,
        string $empresaId,
        string $preferencia = 'auto',
        ?string $hasta = null
    ): array {
        $out = [];
        foreach ($bases as $base) {
            $par = self::resolverPar(
                (string) ($get($base . '_uf') ?? ''),
                (string) ($get($base . '_clp') ?? ''),
                $empresaId,
                $hasta,
                $preferencia
            );
            $out[$base . '_uf']  = $par['uf'];
            $out[$base . '_clp'] = $par['clp'];
        }

        return $out;
    }

    /**
     * Formato de análisis: pesos primero (canónico para Ingresos/Egresos) + equivalencia UF.
     */
    public static function fmtAnalisisPesos(?float $clp, float $ufRate, int $decPesos = 0, int $decUf = 2): string
    {
        if ($clp === null) {
            return '—';
        }
        $uf = $ufRate > 0 ? round($clp / $ufRate, $decUf) : null;

        return self::fmtPesos($clp, $decPesos)
            . ($uf !== null ? ' · ' . self::fmtUf($uf, $decUf) : '');
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

    /**
     * Parsea montos tipados en UI chilena o anglosajona.
     * Acepta: 1500000 | 1.500.000 | 1.500.000,50 | 1500000,5 | $1.500.000 | 1,500,000.50
     * Para pesos ($scale ≤ 2), un solo grupo miles `15.000` → 15000.
     * Para UF ($scale ≥ 3), un solo punto se trata como decimal (`15.000` → 15).
     */
    public static function parseDecimal(?string $raw, int $scale = 2): ?float
    {
        $v = trim((string) $raw);
        if ($v === '') {
            return null;
        }

        // Quitar símbolo / texto de moneda y espacios (incl. NBSP).
        $v = preg_replace('/\s+/u', '', $v) ?? $v;
        $v = str_replace("\xc2\xa0", '', $v);
        $v = preg_replace('/^(clp|uf|pesos|usd)\.?/i', '', $v) ?? $v;
        $v = preg_replace('/(clp|uf|pesos|usd)\.?$/i', '', $v) ?? $v;
        $v = str_replace('$', '', $v);
        $v = trim($v);
        if ($v === '' || $v === '+' || $v === '-') {
            return null;
        }

        $neg = false;
        if (str_starts_with($v, '-')) {
            $neg = true;
            $v   = substr($v, 1);
        } elseif (str_starts_with($v, '+')) {
            $v = substr($v, 1);
        }
        $v = trim($v);
        if ($v === '') {
            return null;
        }

        $hasComma = str_contains($v, ',');
        $hasDot   = str_contains($v, '.');

        if ($hasComma && $hasDot) {
            // El separador que aparece último es el decimal.
            if (strrpos($v, ',') > strrpos($v, '.')) {
                // Chileno: 1.234.567,89
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                // US: 1,234,567.89
                $v = str_replace(',', '', $v);
            }
        } elseif ($hasComma) {
            if (substr_count($v, ',') > 1) {
                // Miles con coma: 1,234,567
                $v = str_replace(',', '', $v);
            } else {
                // Decimal chileno: 41247,63
                $v = str_replace(',', '.', $v);
            }
        } elseif ($hasDot) {
            if (substr_count($v, '.') > 1) {
                // Miles chilenos: 1.500.000
                $v = str_replace('.', '', $v);
            } elseif ($scale <= 2 && preg_match('/^\d{1,3}\.\d{3}$/', $v) === 1) {
                // Pesos con un grupo de miles: 15.000 → 15000
                $v = str_replace('.', '', $v);
            }
            // else: decimal (UF 3245.6789 o pesos con centavos 41247.63)
        }

        if (! is_numeric($v)) {
            return null;
        }

        $n = (float) $v;
        if ($neg) {
            $n = -$n;
        }

        return round($n, $scale);
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
