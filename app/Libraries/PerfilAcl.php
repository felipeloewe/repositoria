<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Resolución de menú/capacidades: si la membresía tiene perfil_id, manda el checkbox;
 * si no, fallback al modelo Roles / DocumentAcl actual.
 */
final class PerfilAcl
{
    /** @var array<string, array<string, bool>> */
    private static array $cache = [];

    /**
     * @param array<string, mixed> $memb
     * @return array<string, bool>|null
     */
    public static function permisosFor(array $memb): ?array
    {
        $perfilId = $memb['perfil_id'] ?? null;
        if ($perfilId === null || $perfilId === '') {
            return null;
        }
        $perfilId = (string) $perfilId;
        if (! isset(self::$cache[$perfilId])) {
            self::$cache[$perfilId] = PerfilCatalogo::permisosMap($perfilId);
        }

        return self::$cache[$perfilId];
    }

    /**
     * @param array<string, mixed> $memb
     */
    public static function enabled(array $memb, string $clave, ?bool $fallback = null): bool
    {
        $map = self::permisosFor($memb);
        if ($map === null) {
            return $fallback ?? false;
        }

        return ! empty($map[$clave]);
    }

    /** @param array<string, mixed> $memb */
    public static function canMenu(array $memb, string $menuKey): bool
    {
        $clave = str_starts_with($menuKey, 'menu.') ? $menuKey : 'menu.' . $menuKey;
        $map   = self::permisosFor($memb);

        if ($map !== null) {
            return ! empty($map[$clave]);
        }

        // Fallback legacy Roles / flags
        $rol = (string) ($memb['rol'] ?? '');

        return match ($clave) {
            'menu.inicio', 'menu.documentos', 'menu.contratos', 'menu.correo' => true,
            'menu.documentos_tipos',
            'menu.documentos_archivados',
            'menu.documentos_respaldo',
            'menu.organizacion',
            'menu.departamentos',
            'menu.recursos',
            'menu.perfiles' => Roles::isMaster($rol),
            'menu.formularios' => EmpresaFormService::canAccess($memb),
            'menu.socios' => DocumentAcl::isSocio($memb),
            'menu.flujos' => FlujosAcl::canBrowseFlujos($memb),
            default => false,
        };
    }

    /** @param array<string, mixed> $memb */
    public static function canCap(array $memb, string $capKey): bool
    {
        $clave = str_starts_with($capKey, 'cap.') ? $capKey : 'cap.' . $capKey;
        $map   = self::permisosFor($memb);

        if ($map !== null) {
            return ! empty($map[$clave]);
        }

        // Fallback: mirror current product rules coarsely
        $rol = (string) ($memb['rol'] ?? '');

        return match ($clave) {
            'cap.docs_upload', 'cap.contratos_upload' => Roles::canUploadDocs($rol),
            'cap.docs_delete' => Roles::isMaster($rol) || $rol === Roles::JEFE_SUCURSAL,
            'cap.contratos_delete', 'cap.docs_archive', 'cap.recursos_contratos_upload' => Roles::isMaster($rol),
            'cap.socios_upload',
            'cap.socios_delete',
            'cap.socios_contratos_upload',
            'cap.socios_contratos_delete' => DocumentAcl::isSocio($memb),
            default => false,
        };
    }
}
