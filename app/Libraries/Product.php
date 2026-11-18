<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Producto / marca en el mismo codebase (repositoria.booklet.cl · equity.booklet.cl).
 * Look idéntico; menús y flujos distintos por host.
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
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
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
            self::KEY_EQUITY => 'equity.booklet.cl<br>Socios · Gerentes · Respaldos',
            default          => 'MVP Fases 1–4<br>CI4 + MySQL',
        };
    }

    public static function homePath(): string
    {
        return '/dashboard';
    }

    public static function sidebarStorageKey(): string
    {
        return self::isEquity() ? 'equity.sidebar.collapsed' : 'repositoria.sidebar.collapsed';
    }

    /**
     * En host Equity, solo rutas del flujo financiero (+ detalle/descarga de respaldos).
     * Repositoria no se restringe aquí.
     */
    public static function isPathAllowed(string $path): bool
    {
        if (! self::isEquity()) {
            return true;
        }

        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $prefixes = [
            '/dashboard',
            '/equity',
            '/organizacion',
            '/perfiles',
            '/empresa/cambiar',
            '/login',
            '/logout',
            // Respaldos reutilizan detalle/descarga documental
            '/documentos/tipos',
        ];
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        // /documentos/{id} y /documentos/{id}/download|...
        if (preg_match('#^/documentos/[A-Za-z0-9_-]+(/|$)#', $path) === 1) {
            // Bloquear listados generales del gestor documental
            $blockedExact = [
                '/documentos',
                '/documentos/archivados',
                '/documentos/archivado',
                '/documentos/en-baja',
            ];
            if (in_array($path, $blockedExact, true)) {
                return false;
            }
            if (str_starts_with($path, '/documentos/archivados')) {
                return false;
            }

            return true;
        }

        return false;
    }
}
