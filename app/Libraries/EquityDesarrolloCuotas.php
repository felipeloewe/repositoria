<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityPasivoCuotaModel;

/**
 * Desarrollo editable de un crédito: cuotas guardadas (manual / import) con fallback a proyección.
 */
final class EquityDesarrolloCuotas
{
    public static function tablasListas(): bool
    {
        return db_connect()->tableExists('equity_pasivo_cuotas');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(string $pasivoId): array
    {
        if ($pasivoId === '' || ! self::tablasListas()) {
            return [];
        }

        return model(EquityPasivoCuotaModel::class)
            ->where('pasivo_id', $pasivoId)
            ->orderBy('fecha', 'ASC')
            ->orderBy('dividendo_n', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Cuotas en forma de proyección (para Egresos / cierre).
     *
     * @param array<string, mixed> $pasivo
     * @return list<array<string, mixed>>
     */
    public static function paraProyeccion(array $pasivo, float $ufRate, int $meses = 12): array
    {
        $pasivoId = (string) ($pasivo['id'] ?? '');
        $saved = self::listar($pasivoId);
        if ($saved === []) {
            return EquityEgresosProyeccion::proyectar($pasivo, $ufRate, $meses);
        }

        $moneda = EquityEgresosProyeccion::moneda($pasivo);
        $out = [];
        foreach ($saved as $row) {
            $out[] = self::normalizarFila($row, $moneda, $ufRate);
            if (count($out) >= max(1, $meses)) {
                break;
            }
        }

        return $out;
    }

    /**
     * Reemplaza todo el desarrollo guardado de un pasivo.
     *
     * @param list<array<string, mixed>> $filas
     */
    public static function reemplazar(
        string $pasivoId,
        string $empresaId,
        ?string $personaId,
        array $filas,
        string $fuente = 'manual',
        ?string $monedaDefault = null
    ): int {
        if (! self::tablasListas() || $pasivoId === '') {
            return 0;
        }

        model(EquityPasivoCuotaModel::class)->where('pasivo_id', $pasivoId)->delete();
        helper('id');
        $n = 0;
        foreach ($filas as $fila) {
            $payload = self::filaAPayload($fila, $pasivoId, $empresaId, $personaId, $fuente, $monedaDefault);
            if ($payload === null) {
                continue;
            }
            $payload['id'] = new_id();
            model(EquityPasivoCuotaModel::class)->insert($payload);
            $n++;
        }

        return $n;
    }

    /**
     * Fusiona filas importadas: actualiza por dividendo_n o fecha; inserta si no hay match.
     * Solo escribe claves presentes (no null) en cada fila importada.
     *
     * @param list<array<string, mixed>> $filas
     * @return array{updated: int, inserted: int}
     */
    public static function fusionarImport(
        string $pasivoId,
        string $empresaId,
        ?string $personaId,
        array $filas,
        ?string $monedaDefault = null
    ): array {
        if (! self::tablasListas() || $pasivoId === '') {
            return ['updated' => 0, 'inserted' => 0];
        }

        $existentes = self::listar($pasivoId);
        $byDiv = [];
        $byFecha = [];
        foreach ($existentes as $e) {
            if (isset($e['dividendo_n']) && $e['dividendo_n'] !== null && $e['dividendo_n'] !== '') {
                $byDiv[(int) $e['dividendo_n']] = $e;
            }
            $f = trim((string) ($e['fecha'] ?? ''));
            if ($f !== '') {
                $byFecha[$f] = $e;
            }
        }

        helper('id');
        $updated = 0;
        $inserted = 0;
        foreach ($filas as $fila) {
            $match = null;
            $divN = isset($fila['dividendo_n']) && $fila['dividendo_n'] !== null && $fila['dividendo_n'] !== ''
                ? (int) $fila['dividendo_n'] : null;
            $fecha = self::normalizeFecha($fila['fecha'] ?? null);
            if ($divN !== null && isset($byDiv[$divN])) {
                $match = $byDiv[$divN];
            } elseif ($fecha !== null && isset($byFecha[$fecha])) {
                $match = $byFecha[$fecha];
            }

            if (is_array($match)) {
                $patch = self::patchDesdeImport($fila);
                if ($patch !== []) {
                    $patch['fuente'] = 'import';
                    model(EquityPasivoCuotaModel::class)->update((string) $match['id'], $patch);
                    $updated++;
                }
                continue;
            }

            $payload = self::filaAPayload($fila, $pasivoId, $empresaId, $personaId, 'import', $monedaDefault);
            if ($payload === null) {
                continue;
            }
            $payload['id'] = new_id();
            model(EquityPasivoCuotaModel::class)->insert($payload);
            $inserted++;
        }

        return ['updated' => $updated, 'inserted' => $inserted];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizarFila(array $row, string $moneda, float $ufRate): array
    {
        $cuotaUf  = self::num($row, 'cuota_uf');
        $cuotaClp = self::num($row, 'cuota_clp');
        $amortUf  = self::num($row, 'amortizacion_uf');
        $amortClp = self::num($row, 'amortizacion_clp');
        $intUf    = self::num($row, 'interes_uf');
        $intClp   = self::num($row, 'interes_clp');
        $saldoUf  = self::num($row, 'saldo_uf');
        $saldoClp = self::num($row, 'saldo_clp');

        if ($moneda === 'UF' && $ufRate > 0) {
            if ($cuotaUf !== null && ($cuotaClp === null || $cuotaClp <= 0)) {
                $cuotaClp = round($cuotaUf * $ufRate, 2);
            }
            if ($amortUf !== null && ($amortClp === null || $amortClp <= 0)) {
                $amortClp = round($amortUf * $ufRate, 2);
            }
            if ($intUf !== null && ($intClp === null || $intClp <= 0)) {
                $intClp = round($intUf * $ufRate, 2);
            }
            if ($saldoUf !== null && ($saldoClp === null || $saldoClp <= 0)) {
                $saldoClp = round($saldoUf * $ufRate, 2);
            }
        } elseif ($moneda === 'CLP' && $ufRate > 0) {
            if ($cuotaClp !== null && ($cuotaUf === null || $cuotaUf <= 0)) {
                $cuotaUf = round($cuotaClp / $ufRate, 4);
            }
            if ($amortClp !== null && ($amortUf === null || $amortUf <= 0)) {
                $amortUf = round($amortClp / $ufRate, 4);
            }
            if ($intClp !== null && ($intUf === null || $intUf <= 0)) {
                $intUf = round($intClp / $ufRate, 4);
            }
            if ($saldoClp !== null && ($saldoUf === null || $saldoUf <= 0)) {
                $saldoUf = round($saldoClp / $ufRate, 4);
            }
        }

        return [
            'id'               => $row['id'] ?? null,
            'fecha'            => self::normalizeFecha($row['fecha'] ?? null),
            'dividendo_n'      => isset($row['dividendo_n']) && $row['dividendo_n'] !== '' && $row['dividendo_n'] !== null
                ? (int) $row['dividendo_n'] : null,
            'cuota_uf'         => $cuotaUf,
            'cuota_clp'        => $cuotaClp,
            'amortizacion_uf'  => $amortUf,
            'amortizacion_clp' => $amortClp,
            'interes_uf'       => $intUf,
            'interes_clp'      => $intClp,
            'saldo_uf'         => $saldoUf,
            'saldo_clp'        => $saldoClp,
            'moneda'           => $moneda,
            'fuente'           => $row['fuente'] ?? 'manual',
        ];
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>|null
     */
    private static function filaAPayload(
        array $fila,
        string $pasivoId,
        string $empresaId,
        ?string $personaId,
        string $fuente,
        ?string $monedaDefault
    ): ?array {
        $fecha = self::normalizeFecha($fila['fecha'] ?? null);
        $divN  = isset($fila['dividendo_n']) && $fila['dividendo_n'] !== null && $fila['dividendo_n'] !== ''
            ? (int) $fila['dividendo_n'] : null;
        $hasMoney = false;
        foreach (['cuota_uf', 'cuota_clp', 'amortizacion_uf', 'amortizacion_clp', 'interes_uf', 'interes_clp', 'saldo_uf', 'saldo_clp'] as $k) {
            if (isset($fila[$k]) && $fila[$k] !== null && $fila[$k] !== '') {
                $hasMoney = true;
                break;
            }
        }
        if ($fecha === null && $divN === null && ! $hasMoney) {
            return null;
        }

        return [
            'pasivo_id'         => $pasivoId,
            'empresa_id'        => $empresaId,
            'persona_id'        => $personaId,
            'fecha'             => $fecha,
            'dividendo_n'       => $divN,
            'moneda'            => isset($fila['moneda']) && $fila['moneda'] !== ''
                ? strtoupper((string) $fila['moneda'])
                : $monedaDefault,
            'cuota_uf'          => self::dec($fila['cuota_uf'] ?? null, 4),
            'cuota_clp'         => self::dec($fila['cuota_clp'] ?? null, 2),
            'amortizacion_uf'   => self::dec($fila['amortizacion_uf'] ?? null, 4),
            'amortizacion_clp'  => self::dec($fila['amortizacion_clp'] ?? null, 2),
            'interes_uf'        => self::dec($fila['interes_uf'] ?? null, 4),
            'interes_clp'       => self::dec($fila['interes_clp'] ?? null, 2),
            'saldo_uf'          => self::dec($fila['saldo_uf'] ?? null, 4),
            'saldo_clp'         => self::dec($fila['saldo_clp'] ?? null, 2),
            'fuente'            => $fuente,
        ];
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private static function patchDesdeImport(array $fila): array
    {
        $patch = [];
        $map = [
            'fecha' => null,
            'dividendo_n' => null,
            'cuota_uf' => 4, 'cuota_clp' => 2,
            'amortizacion_uf' => 4, 'amortizacion_clp' => 2,
            'interes_uf' => 4, 'interes_clp' => 2,
            'saldo_uf' => 4, 'saldo_clp' => 2,
            'moneda' => null,
        ];
        foreach ($map as $k => $scale) {
            if (! array_key_exists($k, $fila) || $fila[$k] === null || $fila[$k] === '') {
                continue;
            }
            if ($k === 'fecha') {
                $f = self::normalizeFecha($fila[$k]);
                if ($f !== null) {
                    $patch[$k] = $f;
                }
                continue;
            }
            if ($k === 'dividendo_n') {
                $patch[$k] = (int) $fila[$k];
                continue;
            }
            if ($k === 'moneda') {
                $patch[$k] = strtoupper((string) $fila[$k]);
                continue;
            }
            $patch[$k] = self::dec($fila[$k], (int) $scale);
        }

        return $patch;
    }

    private static function normalizeFecha(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1) {
            return $v;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $v, $m) === 1) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            // Si día > 12, formato D/M/Y; si no, preferir D/M/Y chileno.
            if ($d > 12 && $mo <= 12) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }

            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $v, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $ts = strtotime($v);

        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    /** @param array<string, mixed> $row */
    private static function num(array $row, string $key): ?float
    {
        if (! isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
            return null;
        }

        return (float) $row[$key];
    }

    private static function dec(mixed $v, int $scale): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_string($v)) {
            $parsed = EquityUf::parseDecimal($v, $scale);
            if ($parsed === null) {
                return null;
            }

            return number_format($parsed, $scale, '.', '');
        }

        return number_format((float) $v, $scale, '.', '');
    }
}
