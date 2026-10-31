<?php

declare(strict_types=1);

/**
 * Stable ID helpers (cuid-like). Email/RUT are attributes, not ACL keys.
 */
if (! function_exists('new_id')) {
    function new_id(): string
    {
        // Compact cuid-like: c + base36 time + random
        $time = base_convert((string) (int) (microtime(true) * 1000), 10, 36);
        $rand = bin2hex(random_bytes(8));

        return 'c' . $time . $rand;
    }
}
