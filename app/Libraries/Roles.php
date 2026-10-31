<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Membership roles (Fase 1). Entry is by real membership — no role switcher.
 */
final class Roles
{
    public const ADMINISTRADOR_MASTER = 'ADMINISTRADOR_MASTER';
    public const JEFE_SUCURSAL        = 'JEFE_SUCURSAL';
    public const CONTADOR             = 'CONTADOR';
    public const ABOGADO              = 'ABOGADO';
    public const PREVENCIONISTA       = 'PREVENCIONISTA';

    public const ALL = [
        self::ADMINISTRADOR_MASTER,
        self::JEFE_SUCURSAL,
        self::CONTADOR,
        self::ABOGADO,
        self::PREVENCIONISTA,
    ];

    public static function label(string $rol): string
    {
        return match ($rol) {
            self::ADMINISTRADOR_MASTER => 'Administrador Master',
            self::JEFE_SUCURSAL        => 'Jefe de Sucursal',
            self::CONTADOR             => 'Contador',
            self::ABOGADO              => 'Abogado',
            self::PREVENCIONISTA       => 'Prevencionista',
            default                    => $rol,
        };
    }

    public static function isMaster(string $rol): bool
    {
        return $rol === self::ADMINISTRADOR_MASTER;
    }

    public static function canManageOrg(string $rol): bool
    {
        return self::isMaster($rol);
    }

    public static function isExternal(string $rol): bool
    {
        return DocumentAcl::isExternal($rol);
    }

    /** Master and Jefe can upload (with further ACL on section scope). */
    public static function canUploadDocs(string $rol): bool
    {
        return self::isMaster($rol) || $rol === self::JEFE_SUCURSAL;
    }
}
