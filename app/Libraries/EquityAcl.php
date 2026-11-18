<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Acceso al módulo Equity (personas · fichas · respaldos).
 * Default: Administrador Master. Perfiles pueden habilitar/deshabilitar.
 */
final class EquityAcl
{
    public const TIPO_SOCIO   = 'socio';
    public const TIPO_GERENTE = 'gerente';

    public const TIPOS = [
        self::TIPO_SOCIO,
        self::TIPO_GERENTE,
    ];

    public static function labelTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_GERENTE => 'Antecedentes Laborales',
            default            => 'Antecedentes Personales',
        };
    }

    public static function normalizeTipo(?string $tipo): string
    {
        return $tipo === self::TIPO_GERENTE ? self::TIPO_GERENTE : self::TIPO_SOCIO;
    }

    /** @param array<string, mixed> $memb */
    public static function canAccess(array $memb): bool
    {
        return PerfilAcl::canMenu($memb, 'menu.equity');
    }

    /** Alta/edición de personas y fichas. @param array<string, mixed> $memb */
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
