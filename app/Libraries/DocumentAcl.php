<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Fase 2 document ACL + aislamiento por ámbito.
 *
 * Master: all tenant docs (general / contrato).
 * Jefe: own sucursal + departamento-level (sucursal_id null) of their depto (general/contrato).
 * Externals (Contador/Abogado/Prevencionista): authorized only (general/contrato).
 * Socio docs (ambito=socio | socio_contrato): only membresías with es_socio in the same empresa.
 */
final class DocumentAcl
{
    public const AMBITO_GENERAL         = 'general';
    public const AMBITO_SOCIO           = 'socio';
    public const AMBITO_CONTRATO        = 'contrato';
    public const AMBITO_SOCIO_CONTRATO  = 'socio_contrato';

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
        return self::normalizeAmbito($doc['ambito'] ?? null) === self::AMBITO_SOCIO;
    }

    public static function isContratoDoc(array $doc): bool
    {
        return self::normalizeAmbito($doc['ambito'] ?? null) === self::AMBITO_CONTRATO;
    }

    public static function isSocioContratoDoc(array $doc): bool
    {
        return self::normalizeAmbito($doc['ambito'] ?? null) === self::AMBITO_SOCIO_CONTRATO;
    }

    /** Pools aislados del listado Documentos / archivados generales. */
    public static function isIsolatedPool(?string $ambito): bool
    {
        return in_array(self::normalizeAmbito($ambito), [
            self::AMBITO_SOCIO,
            self::AMBITO_CONTRATO,
            self::AMBITO_SOCIO_CONTRATO,
        ], true);
    }

    /** Pool visible solo a es_socio (socios docs o socios contratos). */
    public static function isSocioPool(?string $ambito): bool
    {
        return in_array(self::normalizeAmbito($ambito), [
            self::AMBITO_SOCIO,
            self::AMBITO_SOCIO_CONTRATO,
        ], true);
    }

    public static function normalizeAmbito(?string $ambito): string
    {
        return match ($ambito) {
            self::AMBITO_SOCIO          => self::AMBITO_SOCIO,
            self::AMBITO_CONTRATO       => self::AMBITO_CONTRATO,
            self::AMBITO_SOCIO_CONTRATO => self::AMBITO_SOCIO_CONTRATO,
            default                     => self::AMBITO_GENERAL,
        };
    }

    /** Ruta de listado según ámbito (back-links / fallbacks). */
    public static function listPathForAmbito(?string $ambito): string
    {
        return match (self::normalizeAmbito($ambito)) {
            self::AMBITO_SOCIO          => '/socios',
            self::AMBITO_CONTRATO       => '/contratos',
            self::AMBITO_SOCIO_CONTRATO => '/socios/contratos',
            default                     => '/documentos',
        };
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

        if (self::isSocioPool($doc['ambito'] ?? null)) {
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
     * Socio / socio_contrato: any socio of the empresa (pool compartido).
     * Contrato (v1 pool sin sucursal): Master o Jefe (`canUploadDocs`).
     *
     * @param array<string, mixed> $memb
     * @param array{sucursal_id?: ?string, departamento_id?: ?string, ambito?: ?string} $target
     */
    public static function canUpload(array $memb, array $target): bool
    {
        $ambito = self::normalizeAmbito($target['ambito'] ?? null);

        if (self::isSocioPool($ambito)) {
            return self::isSocio($memb);
        }

        if ($ambito === self::AMBITO_CONTRATO) {
            return Roles::canUploadDocs((string) ($memb['rol'] ?? ''));
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

        if (self::isSocioPool($doc['ambito'] ?? null)) {
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
