<?php

declare(strict_types=1);

namespace App\Libraries;

/** Fase 4 ACL helpers for fiscalización and canales seguros. */
final class FlujosAcl
{
    public static function canRequestFiscalizacion(array $memb): bool
    {
        $rol = (string) ($memb['rol'] ?? '');

        return Roles::isMaster($rol) || $rol === Roles::JEFE_SUCURSAL;
    }

    public static function canReviewFiscalizacion(array $memb): bool
    {
        return Roles::isMaster((string) ($memb['rol'] ?? ''));
    }

    public static function canViewFiscalizacion(array $memb, array $solicitud): bool
    {
        if (($memb['empresa_id'] ?? null) !== ($solicitud['empresa_id'] ?? null)) {
            return false;
        }

        $rol = (string) ($memb['rol'] ?? '');
        if (Roles::isMaster($rol)) {
            return true;
        }

        if ($rol === Roles::JEFE_SUCURSAL) {
            if (($solicitud['solicitado_por_id'] ?? null) === ($memb['usuario_id'] ?? null)) {
                return true;
            }

            return ($memb['sucursal_id'] ?? null) !== null
                && ($solicitud['sucursal_id'] ?? null) === ($memb['sucursal_id'] ?? null);
        }

        return false;
    }

    public static function canManageCanalPaquetes(array $memb): bool
    {
        return Roles::isMaster((string) ($memb['rol'] ?? ''));
    }

    public static function canViewCanalPaquete(array $memb, array $paquete): bool
    {
        if (($memb['empresa_id'] ?? null) !== ($paquete['empresa_id'] ?? null)) {
            return false;
        }

        $rol = (string) ($memb['rol'] ?? '');
        if (Roles::isMaster($rol)) {
            return true;
        }

        if (DocumentAcl::isExternal($rol) && ($paquete['destinatario_id'] ?? null) === ($memb['usuario_id'] ?? null)) {
            return ($paquete['estado'] ?? '') === 'ACTIVO';
        }

        return false;
    }

    public static function canBrowseFlujos(array $memb): bool
    {
        $rol = (string) ($memb['rol'] ?? '');

        return Roles::isMaster($rol)
            || $rol === Roles::JEFE_SUCURSAL
            || DocumentAcl::isExternal($rol);
    }
}
