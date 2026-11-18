<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Producto / marca en el mismo codebase (repositoria.booklet.cl · equity.booklet.cl).
 * Look idéntico; solo cambia nombre, tagline y foco de menú.
 */
final class Product
{
    public const KEY_REPOSITORIA = 'repositoria';
    public const KEY_EQUITY      = 'equity';

    public static function key(): string
    {
        $configured = env('PRODUCT_KEY');
        if (is_string($configured) && trim($configured) !== '') {
            $key = strtolower(trim($configured));

            return $key === self::KEY_EQUITY ? self::KEY_EQUITY : self::KEY_REPOSITORIA;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && (
            str_starts_with($host, 'equity.')
            || str_contains($host, 'equity.booklet')
        )) {
            return self::KEY_EQUITY;
        }

        return self::KEY_REPOSITORIA;
    }

    public static function isEquity(): bool
    {
        return self::key() === self::KEY_EQUITY;
    }

    public static function name(): string
    {
        return match (self::key()) {
            self::KEY_EQUITY => 'Equity',
            default          => 'Repositoria',
        };
    }

    public static function tagline(): string
    {
        return match (self::key()) {
            self::KEY_EQUITY => 'Información financiera',
            default          => 'Gestor documental',
        };
    }

    public static function footNote(): string
    {
        return match (self::key()) {
            self::KEY_EQUITY => 'equity.booklet.cl<br>CI4 + MySQL',
            default          => 'MVP Fases 1–4<br>CI4 + MySQL',
        };
    }
}
