<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityPersonaBancoModel;
use App\Models\EquityPersonaCuentaModel;
use App\Models\EquityPersonaTarjetaModel;

/**
 * Antecedentes bancarios: bancos → cuentas corrientes + tarjetas de crédito.
 */
final class EquityBancarios
{
    public const MARCAS = [
        'visa'             => 'Visa',
        'mastercard'       => 'Mastercard',
        'american_express' => 'American Express',
        'diners'           => 'Diners',
        'magna'            => 'Magna',
        'otra'             => 'Otra',
    ];

    public const TIPOS_CUENTA = [
        'corriente' => 'Cuenta corriente',
        'vista'     => 'Cuenta vista',
        'ahorro'    => 'Cuenta de ahorro',
        'otra'      => 'Otra',
    ];

    public const MONEDAS = ['CLP', 'USD', 'UF', 'EUR'];

    public static function tablasListas(): bool
    {
        $db = db_connect();

        return $db->tableExists('equity_persona_bancos')
            && $db->tableExists('equity_persona_cuentas')
            && $db->tableExists('equity_persona_tarjetas');
    }

    public static function marcaSoportaUsd(string $marca): bool
    {
        $m = strtolower(trim($marca));

        return $m === 'visa' || $m === 'mastercard';
    }

    public static function labelMarca(?string $marca): string
    {
        $k = strtolower(trim((string) $marca));

        return self::MARCAS[$k] ?? ($marca !== null && $marca !== '' ? (string) $marca : '—');
    }

    public static function labelTipoCuenta(?string $tipo): string
    {
        $k = strtolower(trim((string) $tipo));

        return self::TIPOS_CUENTA[$k] ?? ($tipo !== null && $tipo !== '' ? (string) $tipo : '—');
    }

    /**
     * @return list<array{banco: array, cuentas: list<array>, tarjetas: list<array>}>
     */
    public static function arbolPersona(string $empresaId, string $personaId): array
    {
        if (! self::tablasListas()) {
            return [];
        }

        $bancos = model(EquityPersonaBancoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->orderBy('banco_nombre', 'ASC')
            ->findAll();

        if ($bancos === []) {
            return [];
        }

        $bancoIds = array_column($bancos, 'id');

        $cuentas = model(EquityPersonaCuentaModel::class)
            ->whereIn('banco_id', $bancoIds)
            ->orderBy('created_at', 'ASC')
            ->findAll();
        $tarjetas = model(EquityPersonaTarjetaModel::class)
            ->whereIn('banco_id', $bancoIds)
            ->orderBy('created_at', 'ASC')
            ->findAll();

        $byBancoC = [];
        foreach ($cuentas as $c) {
            $byBancoC[(string) $c['banco_id']][] = $c;
        }
        $byBancoT = [];
        foreach ($tarjetas as $t) {
            $byBancoT[(string) $t['banco_id']][] = $t;
        }

        $out = [];
        foreach ($bancos as $b) {
            $id = (string) $b['id'];
            $out[] = [
                'banco'    => $b,
                'cuentas'  => $byBancoC[$id] ?? [],
                'tarjetas' => $byBancoT[$id] ?? [],
            ];
        }

        return $out;
    }

    /** Borra bancos/cuentas/tarjetas de la persona (scrub / baja). */
    public static function scrubPersona(string $personaId): void
    {
        if (! self::tablasListas()) {
            return;
        }
        $db = db_connect();
        $db->table('equity_persona_tarjetas')->where('persona_id', $personaId)->delete();
        $db->table('equity_persona_cuentas')->where('persona_id', $personaId)->delete();
        $db->table('equity_persona_bancos')->where('persona_id', $personaId)->delete();
    }

    public static function normalizarVencimiento(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        // MM/AAAA o MM-AAAA o YYYY-MM
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
            $mm = (int) $m[1];
            $yy = (int) $m[2];
            if ($mm < 1 || $mm > 12 || $yy < 1990 || $yy > 2100) {
                return null;
            }

            return sprintf('%02d/%04d', $mm, $yy);
        }
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $s, $m)) {
            $yy = (int) $m[1];
            $mm = (int) $m[2];
            if ($mm < 1 || $mm > 12 || $yy < 1990 || $yy > 2100) {
                return null;
            }

            return sprintf('%02d/%04d', $mm, $yy);
        }

        return null;
    }

    public static function normalizarUltimos4(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) > 4) {
            $digits = substr($digits, -4);
        }
        if (strlen($digits) < 4) {
            return null;
        }

        return $digits;
    }

    public static function moneyOrNull($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $raw = str_replace(['.', ' '], ['', ''], $raw);
            $raw = str_replace(',', '.', $raw);
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
