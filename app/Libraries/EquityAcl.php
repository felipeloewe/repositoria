<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Acceso al módulo Equity (personas · fichas · respaldos).
 * Default: Administrador Master. Perfiles pueden habilitar/deshabilitar.
 */
final class EquityAcl
{
    public const TIPO_SOCIO    = 'socio';
    public const TIPO_GERENTE  = 'gerente';
    public const TIPO_FAMILIAR = 'familiar';

    public const TIPOS = [
        self::TIPO_SOCIO,
        self::TIPO_GERENTE,
        self::TIPO_FAMILIAR,
    ];

    public static function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_GERENTE  => 'Antecedentes Laborales',
            self::TIPO_FAMILIAR => 'Antecedentes Grupo Familiar',
            default             => 'Antecedentes Personales',
        };
    }

    public static function normalizeTipo(?string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_GERENTE  => self::TIPO_GERENTE,
            self::TIPO_FAMILIAR => self::TIPO_FAMILIAR,
            default             => self::TIPO_SOCIO,
        };
    }

    /** Normaliza RUT para comparar (sin puntos/guión, mayúsculas). */
    public static function normalizeRut(?string $rut): string
    {
        $rut = strtoupper(trim((string) $rut));
        $rut = str_replace(['.', '-', ' '], '', $rut);

        return $rut;
    }



    /** @param array<string, mixed> $memb */
    public static function canAccess(array $memb): bool
    {
        // En host Equity, toda membresía activa del grupo puede declarar.
        if (Product::isEquity()) {
            return true;
        }

        return PerfilAcl::canMenu($memb, 'menu.equity');
    }

    /**
     * Completar formularios de la persona de sesión (propios o representados).
     *
     * @param array<string, mixed> $memb
     */
    public static function canDeclare(array $memb): bool
    {
        return self::canAccess($memb);
    }

    /** Administración de grupo / UF / alta de personas. @param array<string, mixed> $memb */
    public static function canManage(array $memb): bool
    {
        if (! self::canAccess($memb)) {
            return false;
        }

        return PerfilAcl::canCap($memb, 'cap.equity_manage');
    }

    /** Subir respaldos (documentos ambito=equity). @param array<string, mixed> $memb */
    public static function canUpload(array $memb): bool
    {
        if (! self::canAccess($memb)) {
            return false;
        }

        return DocumentAcl::canUpload($memb, ['ambito' => DocumentAcl::AMBITO_EQUITY]);
    }

    /** @param array<string, mixed> $memb */
    public static function canDeleteRespaldo(array $memb): bool
    {
        if (! self::canAccess($memb)) {
            return false;
        }

        return PerfilAcl::canCap($memb, 'cap.equity_delete');
    }
}
