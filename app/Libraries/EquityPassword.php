<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Reglas de clave Equity: alfanumérica desde 6; caracteres especiales opcionales.
 */
final class EquityPassword
{
    public static function mintDefault(int $length = 8): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $out     = '';
        $out .= $letters[random_int(0, strlen($letters) - 1)];
        $out .= $digits[random_int(0, strlen($digits) - 1)];
        $alphabet = $letters . $digits;
        for ($i = 2; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return str_shuffle($out);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function validate(string $password): array
    {
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'La clave debe tener al menos 6 caracteres.'];
        }
        // Letras y números permitidos; especiales opcionales (cualquier printable excepto espacio).
        if (! preg_match('/^[A-Za-z0-9[:punct:]]+$/', $password)) {
            return ['ok' => false, 'error' => 'La clave solo puede incluir letras, números y caracteres especiales (sin espacios).'];
        }
        if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password)) {
            return ['ok' => false, 'error' => 'La clave debe ser alfanumérica (al menos una letra y un número).'];
        }

        return ['ok' => true];
    }
}
