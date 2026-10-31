<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Fase 2 document ACL.
 *
 * Master: all tenant docs.
 * Jefe: own sucursal + departamento-level (sucursal_id null) of their depto.
 * Externals (Contador/Abogado/Prevencionista): authorized only.
 */
final class DocumentAcl
{
    public static function isExternal(string $rol): bool
    {
        return in_array($rol, [
            Roles::CONTADOR,
            Roles::ABOGADO,
            Roles::PREVENCIONISTA,
        ], true);
    }

    /**
     * @param array<string, mixed> $memb Active membership (+ departamento_id when Jefe)
     * @param array<string, mixed> $doc  empresa_id, departamento_id, sucursal_id, autorizado_usuario_ids[]
     */
    public static function canView(array $memb, array $doc): bool
    {
        if (($memb['empresa_id'] ?? null) !== ($doc['empresa_id'] ?? null)) {
            return false;
        }

        $rol = (string) ($memb['rol'] ?? '');

        if (Roles::isMaster($rol)) {
            return true;
        }

        if ($rol === Roles::JEFE_SUCURSAL) {
            $docSucursal = $doc['sucursal_id'] ?? null;
            if ($docSucursal === null || $docSucursal === '') {
                return ($memb['departamento_id'] ?? null) === ($doc['departamento_id'] ?? null);
            }

            return ($memb['sucursal_id'] ?? null) === $docSucursal;
        }

        if (self::isExternal($rol)) {
            $auth = $doc['autorizado_usuario_ids'] ?? [];

            return in_array((string) ($memb['usuario_id'] ?? ''), $auth, true);
        }

        return false;
    }

    /**
     * Master: any section. Jefe: only sections scoped to their sucursal (not depto-level).
     *
     * @param array<string, mixed> $memb
     * @param array{sucursal_id: ?string, departamento_id?: ?string} $target
     */
    public static function canUpload(array $memb, array $target): bool
    {
        $rol = (string) ($memb['rol'] ?? '');

        if (Roles::isMaster($rol)) {
            return true;
        }

        if ($rol === Roles::JEFE_SUCURSAL) {
            $targetSucursal = $target['sucursal_id'] ?? null;
            if ($targetSucursal === null || $targetSucursal === '') {
                return false;
            }

            return ($memb['sucursal_id'] ?? null) === $targetSucursal;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $memb
     * @param array<string, mixed> $doc
     */
    public static function canDelete(array $memb, array $doc): bool
    {
        if (! self::canView($memb, $doc)) {
            return false;
        }

        $rol = (string) ($memb['rol'] ?? '');

        if (Roles::isMaster($rol)) {
            return true;
        }

        if ($rol === Roles::JEFE_SUCURSAL) {
            return ($memb['sucursal_id'] ?? null) !== null
                && ($memb['sucursal_id'] ?? null) === ($doc['sucursal_id'] ?? null);
        }

        return false;
    }

    public static function canAuthorize(array $memb): bool
    {
        return Roles::isMaster((string) ($memb['rol'] ?? ''));
    }
}
