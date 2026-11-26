<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\UsuarioModel;

/**
 * Bóveda de secretos bancarios: PIN de perfil → DEK envuelta → cifrado AES-256-GCM.
 * Sin el PIN del usuario no se puede descifrar (el servidor solo guarda DEK envuelta).
 */
final class EquityVault
{
    public const SESSION_DEK_KEY   = 'equity_vault_dek';
    public const SESSION_UNTIL_KEY = 'equity_vault_until';
    public const UNLOCK_TTL_SEC    = 900; // 15 min
    public const PIN_MIN_LEN       = 4;
    public const PIN_MAX_LEN       = 12;
    public const PBKDF2_ITERS      = 120_000;

    public static function tablasListas(): bool
    {
        $db = db_connect();

        return $db->tableExists('equity_cuenta_claves')
            && $db->tableExists('equity_tarjeta_pines')
            && $db->fieldExists('vault_pin_hash', 'usuarios');
    }

    public static function usuarioTienePin(string $usuarioId): bool
    {
        $u = model(UsuarioModel::class)->find($usuarioId);
        if (! is_array($u)) {
            return false;
        }

        return ! empty($u['vault_pin_hash']) && ! empty($u['vault_wrapped_dek']) && ! empty($u['vault_salt']);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function configurarPin(string $usuarioId, string $pin, ?string $pinActual = null): array
    {
        $pin = trim($pin);
        $err = self::validarFormatoPin($pin);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }

        $u = model(UsuarioModel::class)->find($usuarioId);
        if (! is_array($u)) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }

        $tiene = ! empty($u['vault_pin_hash']) && ! empty($u['vault_wrapped_dek']);
        if ($tiene) {
            if ($pinActual === null || $pinActual === '') {
                return ['ok' => false, 'error' => 'Ingresa el PIN actual para cambiarlo.'];
            }
            $dek = self::unwrapDek($u, $pinActual);
            if ($dek === null) {
                return ['ok' => false, 'error' => 'PIN actual incorrecto.'];
            }
        } else {
            $dek = random_bytes(32);
        }

        $salt = bin2hex(random_bytes(16));
        $wrap = self::wrapDek($dek, $pin, $salt);
        if ($wrap === null) {
            return ['ok' => false, 'error' => 'No se pudo cifrar la bóveda.'];
        }

        model(UsuarioModel::class)->update($usuarioId, [
            'vault_pin_hash'    => password_hash($pin, PASSWORD_DEFAULT),
            'vault_salt'        => $salt,
            'vault_wrapped_dek' => $wrap,
            'vault_pin_set_at'  => date('Y-m-d H:i:s'),
        ]);

        self::guardarDekEnSesion($dek);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function desbloquear(string $usuarioId, string $pin): array
    {
        $u = model(UsuarioModel::class)->find($usuarioId);
        if (! is_array($u) || empty($u['vault_pin_hash'])) {
            return ['ok' => false, 'error' => 'Configura primero un PIN de bóveda.'];
        }
        if (! password_verify($pin, (string) $u['vault_pin_hash'])) {
            return ['ok' => false, 'error' => 'PIN incorrecto.'];
        }
        $dek = self::unwrapDek($u, $pin);
        if ($dek === null) {
            return ['ok' => false, 'error' => 'No se pudo abrir la bóveda.'];
        }
        self::guardarDekEnSesion($dek);

        return ['ok' => true];
    }

    public static function bloquear(): void
    {
        $session = session();
        $session->remove(self::SESSION_DEK_KEY);
        $session->remove(self::SESSION_UNTIL_KEY);
    }

    public static function estaDesbloqueada(): bool
    {
        return self::dekSesion() !== null;
    }

    public static function cifrar(string $plaintext): ?string
    {
        $dek = self::dekSesion();
        if ($dek === null) {
            return null;
        }

        return self::aesEncrypt($plaintext, $dek);
    }

    public static function descifrar(string $cipherPayload): ?string
    {
        $dek = self::dekSesion();
        if ($dek === null) {
            return null;
        }

        return self::aesDecrypt($cipherPayload, $dek);
    }

    public static function validarFormatoPin(string $pin): ?string
    {
        $len = strlen($pin);
        if ($len < self::PIN_MIN_LEN || $len > self::PIN_MAX_LEN) {
            return 'El PIN debe tener entre ' . self::PIN_MIN_LEN . ' y ' . self::PIN_MAX_LEN . ' dígitos.';
        }
        if (! ctype_digit($pin)) {
            return 'El PIN solo puede contener dígitos.';
        }

        return null;
    }

    public static function scrubUsuarioVault(string $usuarioId): void
    {
        $db = db_connect();
        if (! $db->fieldExists('vault_pin_hash', 'usuarios')) {
            return;
        }
        model(UsuarioModel::class)->update($usuarioId, [
            'vault_pin_hash'    => null,
            'vault_salt'        => null,
            'vault_wrapped_dek' => null,
            'vault_pin_set_at'  => null,
        ]);
        self::bloquear();
    }

    private static function guardarDekEnSesion(string $dek): void
    {
        $session = session();
        $session->set(self::SESSION_DEK_KEY, base64_encode($dek));
        $session->set(self::SESSION_UNTIL_KEY, time() + self::UNLOCK_TTL_SEC);
    }

    private static function dekSesion(): ?string
    {
        $session = session();
        $until = (int) ($session->get(self::SESSION_UNTIL_KEY) ?? 0);
        $b64   = $session->get(self::SESSION_DEK_KEY);
        if ($until < time() || ! is_string($b64) || $b64 === '') {
            self::bloquear();

            return null;
        }
        $dek = base64_decode($b64, true);
        if ($dek === false || strlen($dek) !== 32) {
            self::bloquear();

            return null;
        }
        // Renovar TTL en uso
        $session->set(self::SESSION_UNTIL_KEY, time() + self::UNLOCK_TTL_SEC);

        return $dek;
    }

    /** @param array<string, mixed> $usuario */
    private static function unwrapDek(array $usuario, string $pin): ?string
    {
        $salt = (string) ($usuario['vault_salt'] ?? '');
        $wrap = (string) ($usuario['vault_wrapped_dek'] ?? '');
        if ($salt === '' || $wrap === '') {
            return null;
        }
        $key = self::deriveKey($pin, $salt);

        return self::aesDecrypt($wrap, $key);
    }

    private static function wrapDek(string $dek, string $pin, string $salt): ?string
    {
        $key = self::deriveKey($pin, $salt);

        return self::aesEncrypt($dek, $key);
    }

    private static function deriveKey(string $pin, string $saltHex): string
    {
        $salt = hex2bin($saltHex);
        if ($salt === false) {
            $salt = $saltHex;
        }

        return hash_pbkdf2('sha256', $pin, $salt, self::PBKDF2_ITERS, 32, true);
    }

    private static function aesEncrypt(string $plaintext, string $key32): ?string
    {
        if (strlen($key32) !== 32) {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $key32, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) {
            return null;
        }

        return base64_encode(json_encode([
            'v'  => 1,
            'iv' => base64_encode($iv),
            't'  => base64_encode($tag),
            'c'  => base64_encode($ct),
        ], JSON_THROW_ON_ERROR));
    }

    private static function aesDecrypt(string $payload, string $key32): ?string
    {
        if (strlen($key32) !== 32) {
            return null;
        }
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }
        try {
            $j = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($j)) {
            return null;
        }
        $iv  = base64_decode((string) ($j['iv'] ?? ''), true);
        $tag = base64_decode((string) ($j['t'] ?? ''), true);
        $ct  = base64_decode((string) ($j['c'] ?? ''), true);
        if ($iv === false || $tag === false || $ct === false) {
            return null;
        }
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key32, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            return null;
        }

        return $pt;
    }
}
