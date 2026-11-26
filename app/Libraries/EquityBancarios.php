<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityCuentaClaveModel;
use App\Models\EquityPersonaBancoModel;
use App\Models\EquityPersonaCuentaModel;
use App\Models\EquityPersonaTarjetaModel;
use App\Models\EquityTarjetaPinModel;

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

    /** Enlaces oficiales a las apps de referencia (abrir / instalar). */
    public const APP_LINKS = [
        'google_wallet' => [
            'label' => 'Google Wallet',
            'url'   => 'https://wallet.google/',
        ],
        'gpay' => [
            'label' => 'G-Pay',
            'url'   => 'https://pay.google.com/',
        ],
        'link' => [
            'label' => 'LINK',
            'url'   => 'https://link.com/',
        ],
    ];

    public static function appUrl(string $key): ?string
    {
        return self::APP_LINKS[$key]['url'] ?? null;
    }

    public static function appLabel(string $key): string
    {
        return self::APP_LINKS[$key]['label'] ?? $key;
    }

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

        $clavesByCuenta = [];
        $pinesByTarjeta = [];
        if (EquityVault::tablasListas()) {
            $cuentaIds = array_column($cuentas, 'id');
            $tarjetaIds = array_column($tarjetas, 'id');
            if ($cuentaIds !== []) {
                $claves = model(EquityCuentaClaveModel::class)
                    ->whereIn('cuenta_id', $cuentaIds)
                    ->orderBy('fecha_inicio', 'DESC')
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
                foreach ($claves as $row) {
                    $clavesByCuenta[(string) $row['cuenta_id']][] = self::enriquecerSecreto($row);
                }
            }
            if ($tarjetaIds !== []) {
                $pines = model(EquityTarjetaPinModel::class)
                    ->whereIn('tarjeta_id', $tarjetaIds)
                    ->orderBy('fecha_inicio', 'DESC')
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
                foreach ($pines as $row) {
                    $pinesByTarjeta[(string) $row['tarjeta_id']][] = self::enriquecerSecreto($row);
                }
            }
        }

        $byBancoC = [];
        foreach ($cuentas as $c) {
            $cid = (string) $c['id'];
            $c['claves'] = $clavesByCuenta[$cid] ?? [];
            $byBancoC[(string) $c['banco_id']][] = $c;
        }
        $byBancoT = [];
        foreach ($tarjetas as $t) {
            $tid = (string) $t['id'];
            $t['pines'] = $pinesByTarjeta[$tid] ?? [];
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enriquecerSecreto(array $row): array
    {
        $hoy = date('Y-m-d');
        $estado = strtolower((string) ($row['estado'] ?? 'vigente'));
        $hasta = $row['vigencia_hasta'] ?? null;
        if ($estado === 'vigente' && is_string($hasta) && $hasta !== '' && $hasta < $hoy) {
            $estado = 'caducada';
            $row['estado_efectivo'] = 'caducada';
        } else {
            $row['estado_efectivo'] = $estado === 'caducada' ? 'caducada' : 'vigente';
        }
        $row['secreto_visible'] = null;
        if (EquityVault::estaDesbloqueada() && ! empty($row['secreto_cipher'])) {
            $row['secreto_visible'] = EquityVault::descifrar((string) $row['secreto_cipher']);
        }

        return $row;
    }

    /** Borra bancos/cuentas/tarjetas/secretos de la persona (scrub / baja). */
    public static function scrubPersona(string $personaId): void
    {
        if (! self::tablasListas()) {
            return;
        }
        $db = db_connect();
        if ($db->tableExists('equity_cuenta_claves')) {
            $db->table('equity_cuenta_claves')->where('persona_id', $personaId)->delete();
        }
        if ($db->tableExists('equity_tarjeta_pines')) {
            $db->table('equity_tarjeta_pines')->where('persona_id', $personaId)->delete();
        }
        $db->table('equity_persona_tarjetas')->where('persona_id', $personaId)->delete();
        $db->table('equity_persona_cuentas')->where('persona_id', $personaId)->delete();
        $db->table('equity_persona_bancos')->where('persona_id', $personaId)->delete();
    }

    public static function normalizarEstado(?string $estado): string
    {
        $e = strtolower(trim((string) $estado));

        return $e === 'caducada' ? 'caducada' : 'vigente';
    }

    public static function normalizarFecha(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }

        return null;
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
