<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Fase 2 document ACL + aislamiento ámbito socio.
 *
 * Master: all tenant docs (general).
 * Jefe: own sucursal + departamento-level (sucursal_id null) of their depto.
 * Externals (Contador/Abogado/Prevencionista): authorized only.
 * Socio docs (ambito=socio): only membresías with es_socio in the same empresa.
 */
final class DocumentAcl
{
    public const AMBITO_GENERAL = 'general';
    public const AMBITO_SOCIO   = 'socio';

    public static function isExternal(string $rol): bool
    {
        return in_array($rol, [
            Roles::CONTADOR,
            Roles::ABOGADO,
            Roles::PREVENCIONISTA,
        ], true);
    }

    public static function isSocio(array $memb): bool
    {
        return (int) ($memb['es_socio'] ?? 0) === 1;
    }

    public static function isSocioDoc(array $doc): bool
    {
        return ($doc['ambito'] ?? self::AMBITO_GENERAL) === self::AMBITO_SOCIO;
    }

    public static function normalizeAmbito(?string $ambito): string
    {
        return $ambito === self::AMBITO_SOCIO ? self::AMBITO_SOCIO : self::AMBITO_GENERAL;
    }

    /**
     * @param array<string, mixed> $memb Active membership (+ departamento_id when Jefe)
     * @param array<string, mixed> $doc  empresa_id, departamento_id, sucursal_id, autorizado_usuario_ids[], ambito?
     */
    public static function canView(array $memb, array $doc): bool
    {
        if (($memb['empresa_id'] ?? null) !== ($doc['empresa_id'] ?? null)) {
            return false;
        }

        if (self::isSocioDoc($doc)) {
            return self::isSocio($memb);
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
     * Master: any section (general). Jefe: only sections scoped to their sucursal.
     * Socio docs: any socio of the empresa (pool compartido).
     *
     * @param array<string, mixed> $memb
     * @param array{sucursal_id?: ?string, departamento_id?: ?string, ambito?: ?string} $target
     */
    public static function canUpload(array $memb, array $target): bool
    {
        if (self::normalizeAmbito($target['ambito'] ?? null) === self::AMBITO_SOCIO) {
            return self::isSocio($memb);
        }

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

        if (self::isSocioDoc($doc)) {
            return self::isSocio($memb);
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
