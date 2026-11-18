<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Menús e IA de Equity según Estado de Situación Patrimonial
 * (activos / pasivos / ingresos / respaldos).
 */
final class EquityCatalogo
{
    /**
     * @return list<array{clave: string, label: string, grupo: string, path: string, descripcion: string}>
     */
    public static function menusInicio(): array
    {
        return [
            [
                'clave'       => 'personas',
                'label'       => 'Personas',
                'grupo'       => 'Principal',
                'path'        => '/equity',
                'descripcion' => 'Socios y gerentes de la empresa',
            ],
            [
                'clave'       => 'resumen',
                'label'       => 'Resumen patrimonial',
                'grupo'       => 'Estado de situación',
                'path'        => '/equity/resumen',
                'descripcion' => 'Totales de activos, pasivos y patrimonio neto',
            ],
            [
                'clave'       => 'bienes-raices',
                'label'       => 'Bienes raíces',
                'grupo'       => 'Activos',
                'path'        => '/equity/activos/bienes-raices',
                'descripcion' => 'Propiedades, tasaciones, rol y avalúo',
            ],
            [
                'clave'       => 'vehiculos',
                'label'       => 'Vehículos',
                'grupo'       => 'Activos',
                'path'        => '/equity/activos/vehiculos',
                'descripcion' => 'Autos, camionetas y otros vehículos',
            ],
            [
                'clave'       => 'sociedades',
                'label'       => 'Sociedades',
                'grupo'       => 'Activos',
                'path'        => '/equity/activos/sociedades',
                'descripcion' => 'Participaciones directas e indirectas',
            ],
            [
                'clave'       => 'otros-activos',
                'label'       => 'Otros activos',
                'grupo'       => 'Activos',
                'path'        => '/equity/activos/otros',
                'descripcion' => 'Depósitos, acciones, bonos, ahorros',
            ],
            [
                'clave'       => 'pasivos-inmuebles',
                'label'       => 'Pasivos inmuebles',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/inmuebles',
                'descripcion' => 'Hipotecarios y créditos asociados a bienes raíces',
            ],
            [
                'clave'       => 'pasivos-circulantes',
                'label'       => 'Pasivos circulantes',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/circulantes',
                'descripcion' => 'Créditos, leasing y corto plazo',
            ],
            [
                'clave'       => 'otros-pasivos',
                'label'       => 'Otros pasivos',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/otros',
                'descripcion' => 'Tarjetas, líneas, consumo y otros',
            ],
            [
                'clave'       => 'ingresos',
                'label'       => 'Ingresos',
                'grupo'       => 'Antecedentes financieros',
                'path'        => '/equity/ingresos',
                'descripcion' => 'Rentas, honorarios y totales de ingreso',
            ],
            [
                'clave'       => 'respaldos',
                'label'       => 'Respaldos',
                'grupo'       => 'Documentos',
                'path'        => '/equity/respaldos',
                'descripcion' => 'Evidencias documentales del estado de situación',
            ],
        ];
    }

    /** @return array<string, list<array{clave: string, label: string, path: string}>> */
    public static function navGroups(): array
    {
        return [
            'Principal' => [
                ['clave' => 'inicio', 'label' => 'Inicio', 'path' => '/dashboard'],
                ['clave' => 'personas', 'label' => 'Personas', 'path' => '/equity'],
                ['clave' => 'socios', 'label' => 'Socios', 'path' => '/equity?tipo=socio'],
                ['clave' => 'gerentes', 'label' => 'Gerentes', 'path' => '/equity?tipo=gerente'],
            ],
            'Estado de situación' => [
                ['clave' => 'resumen', 'label' => 'Resumen', 'path' => '/equity/resumen'],
                ['clave' => 'ingresos', 'label' => 'Ingresos', 'path' => '/equity/ingresos'],
            ],
            'Activos' => [
                ['clave' => 'bienes-raices', 'label' => 'Bienes raíces', 'path' => '/equity/activos/bienes-raices'],
                ['clave' => 'vehiculos', 'label' => 'Vehículos', 'path' => '/equity/activos/vehiculos'],
                ['clave' => 'sociedades', 'label' => 'Sociedades', 'path' => '/equity/activos/sociedades'],
                ['clave' => 'otros-activos', 'label' => 'Otros activos', 'path' => '/equity/activos/otros'],
            ],
            'Pasivos' => [
                ['clave' => 'pasivos-inmuebles', 'label' => 'Inmuebles', 'path' => '/equity/pasivos/inmuebles'],
                ['clave' => 'pasivos-circulantes', 'label' => 'Circulantes', 'path' => '/equity/pasivos/circulantes'],
                ['clave' => 'otros-pasivos', 'label' => 'Otros pasivos', 'path' => '/equity/pasivos/otros'],
            ],
            'Documentos' => [
                ['clave' => 'respaldos', 'label' => 'Respaldos', 'path' => '/equity/respaldos'],
            ],
        ];
    }

    /** @return array{clave: string, label: string, grupo: string, table: string, categoria?: string}|null */
    public static function seccion(string $clave): ?array
    {
        $map = [
            'bienes-raices' => [
                'clave'  => 'bienes-raices',
                'label'  => 'Bienes raíces',
                'grupo'  => 'Activos',
                'table'  => 'equity_bienes_raices',
            ],
            'vehiculos' => [
                'clave'  => 'vehiculos',
                'label'  => 'Vehículos',
                'grupo'  => 'Activos',
                'table'  => 'equity_vehiculos',
            ],
            'sociedades' => [
                'clave'  => 'sociedades',
                'label'  => 'Sociedades',
                'grupo'  => 'Activos',
                'table'  => 'equity_sociedades',
            ],
            'otros-activos' => [
                'clave'  => 'otros-activos',
                'label'  => 'Otros activos',
                'grupo'  => 'Activos',
                'table'  => 'equity_otros_activos',
            ],
            'pasivos-inmuebles' => [
                'clave'     => 'pasivos-inmuebles',
                'label'     => 'Pasivos inmuebles',
                'grupo'     => 'Pasivos',
                'table'     => 'equity_pasivos',
                'categoria' => 'inmueble',
            ],
            'pasivos-circulantes' => [
                'clave'     => 'pasivos-circulantes',
                'label'     => 'Pasivos circulantes',
                'grupo'     => 'Pasivos',
                'table'     => 'equity_pasivos',
                'categoria' => 'circulante',
            ],
            'otros-pasivos' => [
                'clave'     => 'otros-pasivos',
                'label'     => 'Otros pasivos',
                'grupo'     => 'Pasivos',
                'table'     => 'equity_pasivos',
                'categoria' => 'otro',
            ],
        ];

        return $map[$clave] ?? null;
    }
}
