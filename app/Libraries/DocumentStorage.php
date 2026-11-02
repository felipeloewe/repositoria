<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Local disk storage for document blobs (Fase 2).
 * Override root with STORAGE_ROOT in `.env`.
 */
final class DocumentStorage
{
    public static function root(): string
    {
        $configured = env('STORAGE_ROOT');
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), DIRECTORY_SEPARATOR);
        }

        return rtrim(ROOTPATH . 'storage' . DIRECTORY_SEPARATOR . 'documents', DIRECTORY_SEPARATOR);
    }

    public static function absolutePath(string $relative): string
    {
        $root = realpath(self::root()) ?: self::root();
        if (! is_dir($root)) {
            mkdir($root, 0750, true);
            $root = realpath($root) ?: $root;
        }

        $relative = str_replace(['\\', "\0"], ['/', ''], $relative);
        $relative = ltrim($relative, '/');
        $full     = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $fullReal = $full;

        $parent = dirname($full);
        if (! is_dir($parent)) {
            mkdir($parent, 0750, true);
        }

        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false) {
            throw new RuntimeException('STORAGE_ROOT inválido.');
        }

        // Before file exists, validate parent is under root
        $resolvedParent = realpath($parent);
        if ($resolvedParent === false || ! str_starts_with($resolvedParent, $resolvedRoot)) {
            throw new RuntimeException('Ruta fuera de STORAGE_ROOT.');
        }

        return $fullReal;
    }

    /**
     * @return array{ruta_relativa: string, tamano_bytes: int}
     */
    public static function save(string $empresaId, string $documentoId, string $nombreArchivo, string $binary): array
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $nombreArchivo) ?: 'archivo.bin';
        $rel  = $empresaId . '/' . $documentoId . '/' . $safe;
        $full = self::absolutePath($rel);

        if (file_put_contents($full, $binary) === false) {
            throw new RuntimeException('No se pudo escribir el archivo.');
        }

        return [
            'ruta_relativa' => $rel,
            'tamano_bytes'  => strlen($binary),
        ];
    }

    public static function read(string $relative): string
    {
        $full = self::absolutePath($relative);
        if (! is_file($full)) {
            throw new RuntimeException('Archivo no encontrado en storage.');
        }

        $data = file_get_contents($full);
        if ($data === false) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        return $data;
    }

    public static function delete(string $relative): void
    {
        try {
            $full = self::absolutePath($relative);
            if (is_file($full)) {
                unlink($full);
            }
        } catch (RuntimeException) {
            // ignore missing / invalid
        }
    }

    /**
     * Absolute path of an empresa folder under STORAGE_ROOT: `{root}/{empresaId}`.
     */
    public static function empresaDir(string $empresaId): string
    {
        $empresaId = preg_replace('/[^a-zA-Z0-9_-]+/', '', $empresaId) ?? '';
        if ($empresaId === '') {
            throw new RuntimeException('empresaId inválido para storage.');
        }

        $root = realpath(self::root()) ?: self::root();
        if (! is_dir($root)) {
            mkdir($root, 0750, true);
            $root = realpath($root) ?: $root;
        }

        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $empresaId;
    }

    /**
     * Real disk usage of `{STORAGE_ROOT}/{empresaId}/…` (recursive file sizes).
     * Prefers `du -sb` when available; falls back to RecursiveDirectoryIterator.
     */
    public static function empresaDiskBytes(string $empresaId): int
    {
        try {
            $dir = self::empresaDir($empresaId);
        } catch (RuntimeException) {
            return 0;
        }

        if (! is_dir($dir)) {
            return 0;
        }

        $du = self::duBytes($dir);
        if ($du !== null) {
            return $du;
        }

        return self::walkDirectoryBytes($dir);
    }

    private static function duBytes(string $dir): ?int
    {
        if (! function_exists('exec')) {
            return null;
        }

        $escaped = escapeshellarg($dir);
        $out     = [];
        $code    = 1;
        @exec('du -sb ' . $escaped . ' 2>/dev/null', $out, $code);
        if ($code !== 0 || $out === []) {
            return null;
        }

        $line = trim((string) $out[0]);
        if (! preg_match('/^(\d+)/', $line, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private static function walkDirectoryBytes(string $dir): int
    {
        $total = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total += (int) $file->getSize();
                }
            }
        } catch (\Throwable) {
            return $total;
        }

        return $total;
    }

    /**
     * Move blob from one empresa folder to another, keeping documentoId + filename.
     * Path layout: {empresaId}/{documentoId}/{filename}
     *
     * @return string New ruta_relativa
     */
    public static function relocateToEmpresa(string $rutaRelativa, string $nuevaEmpresaId, string $documentoId): string
    {
        $rutaRelativa = str_replace(['\\', "\0"], ['/', ''], $rutaRelativa);
        $rutaRelativa = ltrim($rutaRelativa, '/');
        $parts        = explode('/', $rutaRelativa);
        $filename     = $parts === [] ? 'archivo.bin' : (string) end($parts);
        $safe         = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'archivo.bin';
        $newRel       = $nuevaEmpresaId . '/' . $documentoId . '/' . $safe;

        if ($rutaRelativa === '' || $rutaRelativa === 'pending') {
            return $newRel;
        }

        if ($rutaRelativa === $newRel) {
            return $newRel;
        }

        $from = self::absolutePath($rutaRelativa);
        if (! is_file($from)) {
            // DB row may exist without blob (seed edge); still update path.
            return $newRel;
        }

        $to = self::absolutePath($newRel);
        if (! @rename($from, $to)) {
            $bytes = file_get_contents($from);
            if ($bytes === false || file_put_contents($to, $bytes) === false) {
                throw new RuntimeException('No se pudo mover el archivo de documento.');
            }
            @unlink($from);
        }

        // Best-effort cleanup of empty old dirs
        $oldDocDir = dirname($from);
        if (is_dir($oldDocDir) && count(array_diff(scandir($oldDocDir) ?: [], ['.', '..'])) === 0) {
            @rmdir($oldDocDir);
            $oldEmpDir = dirname($oldDocDir);
            if (is_dir($oldEmpDir) && count(array_diff(scandir($oldEmpDir) ?: [], ['.', '..'])) === 0) {
                @rmdir($oldEmpDir);
            }
        }

        return $newRel;
    }
}
