<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Menús e IA de Equity según Estado de Situación Patrimonial
 * (activos / pasivos / ingresos / egresos / respaldos).
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
                'label'       => 'Grupo Familiar',
                'grupo'       => 'Principal',
                'path'        => '/equity',
                'descripcion' => 'Antecedentes personales, laborales y cónyuge/conviviente',
            ],
            [
                'clave'       => 'situacion-financiera',
                'label'       => 'Situación financiera',
                'grupo'       => 'Principal',
                'path'        => '/equity/situacion-financiera',
                'descripcion' => 'Créditos, vencimientos y carga mensual (cuotas)',
            ],
            [
                'clave'       => 'cierre',
                'label'       => 'Cierre mensual',
                'grupo'       => 'Principal',
                'path'        => '/equity/cierre',
                'descripcion' => 'V.B. del mes/año, reportes en historial y desarrollo de créditos',
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
                'label'       => 'Hipotecarios',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/inmuebles',
                'descripcion' => 'Créditos hipotecarios asociados a bienes raíces',
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
                'descripcion' => 'Consumo, descuentos y otros',
            ],
            [
                'clave'       => 'pasivos-indirecta',
                'label'       => 'Deuda indirecta',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/indirecta',
                'descripcion' => 'Referencia: codeudor, fiador o avalista (no deuda directa)',
            ],
            [
                'clave'       => 'pasivos-lineas',
                'label'       => 'Líneas disponibles',
                'grupo'       => 'Pasivos',
                'path'        => '/equity/pasivos/lineas',
                'descripcion' => 'Referencia: cupos (libre disposición, línea, tarjeta u otros)',
            ],
            [
                'clave'       => 'ingresos',
                'label'       => 'Ingresos',
                'grupo'       => 'Ingresos',
                'path'        => '/equity/ingresos',
                'descripcion' => 'Renta · Honorarios · Inversiones · Otros',
            ],
            [
                'clave'       => 'ingresos-renta',
                'label'       => 'Renta',
                'grupo'       => 'Ingresos',
                'path'        => '/equity/ingresos/renta',
                'descripcion' => 'Fija / Variable',
            ],
            [
                'clave'       => 'ingresos-honorarios',
                'label'       => 'Honorarios',
                'grupo'       => 'Ingresos',
                'path'        => '/equity/ingresos/honorarios',
                'descripcion' => 'Honorarios / Comisiones / Bonos',
            ],
            [
                'clave'       => 'ingresos-inversiones',
                'label'       => 'Inversiones',
                'grupo'       => 'Ingresos',
                'path'        => '/equity/ingresos/inversiones',
                'descripcion' => 'Arriendos / Mutuos / Dividendos',
            ],
            [
                'clave'       => 'ingresos-otros',
                'label'       => 'Otros',
                'grupo'       => 'Ingresos',
                'path'        => '/equity/ingresos/otros',
                'descripcion' => 'Pensiones / Donaciones / Indemnizaciones / Capital ER / Intereses ER',
            ],
            [
                'clave'       => 'egresos',
                'label'       => 'Egresos',
                'grupo'       => 'Egresos',
                'path'        => '/equity/egresos',
                'descripcion' => 'Hipotecarios · Circulantes · Otros',
            ],
            [
                'clave'       => 'egresos-hipotecarios',
                'label'       => 'Hipotecarios',
                'grupo'       => 'Egresos',
                'path'        => '/equity/egresos/hipotecarios',
                'descripcion' => 'Dividendos y próximos vencimientos',
            ],
            [
                'clave'       => 'egresos-circulantes',
                'label'       => 'Circulantes',
                'grupo'       => 'Egresos',
                'path'        => '/equity/egresos/circulantes',
                'descripcion' => 'Cuotas de corto plazo',
            ],
            [
                'clave'       => 'egresos-otros',
                'label'       => 'Otros',
                'grupo'       => 'Egresos',
                'path'        => '/equity/egresos/otros',
                'descripcion' => 'Consumo y otros pasivos',
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
                ['clave' => 'situacion-financiera', 'label' => 'Situación financiera', 'path' => '/equity/situacion-financiera'],
                ['clave' => 'cierre', 'label' => 'Cierre mensual', 'path' => '/equity/cierre'],
                ['clave' => 'personas', 'label' => 'Grupo Familiar', 'path' => '/equity'],
                ['clave' => 'socios', 'label' => 'Antecedentes Personales', 'path' => '/equity?tipo=socio'],
                ['clave' => 'gerentes', 'label' => 'Antecedentes Laborales', 'path' => '/equity?tipo=gerente'],
                ['clave' => 'familiares', 'label' => 'Antecedentes Grupo Familiar', 'path' => '/equity?tipo=familiar'],
            ],


            'Estado de situación' => [
                ['clave' => 'resumen', 'label' => 'Resumen', 'path' => '/equity/resumen'],
            ],
            'Ingresos' => [
                ['clave' => 'ingresos', 'label' => 'Resumen ingresos', 'path' => '/equity/ingresos'],
                ['clave' => 'ingresos-renta', 'label' => 'Renta', 'path' => '/equity/ingresos/renta'],
                ['clave' => 'ingresos-honorarios', 'label' => 'Honorarios', 'path' => '/equity/ingresos/honorarios'],
                ['clave' => 'ingresos-inversiones', 'label' => 'Inversiones', 'path' => '/equity/ingresos/inversiones'],
                ['clave' => 'ingresos-otros', 'label' => 'Otros', 'path' => '/equity/ingresos/otros'],
            ],
            'Egresos' => [
                ['clave' => 'egresos', 'label' => 'Resumen egresos', 'path' => '/equity/egresos'],
                ['clave' => 'egresos-hipotecarios', 'label' => 'Hipotecarios', 'path' => '/equity/egresos/hipotecarios'],
                ['clave' => 'egresos-circulantes', 'label' => 'Circulantes', 'path' => '/equity/egresos/circulantes'],
                ['clave' => 'egresos-otros', 'label' => 'Otros', 'path' => '/equity/egresos/otros'],
            ],
            'Activos' => [
                ['clave' => 'bienes-raices', 'label' => 'Bienes raíces', 'path' => '/equity/activos/bienes-raices'],
                ['clave' => 'vehiculos', 'label' => 'Vehículos', 'path' => '/equity/activos/vehiculos'],
                ['clave' => 'sociedades', 'label' => 'Sociedades', 'path' => '/equity/activos/sociedades'],
                ['clave' => 'otros-activos', 'label' => 'Otros activos', 'path' => '/equity/activos/otros'],
            ],
            'Pasivos' => [
                ['clave' => 'pasivos-inmuebles', 'label' => 'Hipotecarios', 'path' => '/equity/pasivos/inmuebles'],
                ['clave' => 'pasivos-circulantes', 'label' => 'Circulantes', 'path' => '/equity/pasivos/circulantes'],
                ['clave' => 'otros-pasivos', 'label' => 'Otros pasivos', 'path' => '/equity/pasivos/otros'],
                ['clave' => 'pasivos-indirecta', 'label' => 'Deuda indirecta', 'path' => '/equity/pasivos/indirecta'],
                ['clave' => 'pasivos-lineas', 'label' => 'Líneas disponibles', 'path' => '/equity/pasivos/lineas'],
            ],
            'Documentos' => [
                ['clave' => 'respaldos', 'label' => 'Respaldos', 'path' => '/equity/respaldos'],
            ],
        ];
    }

    /**
     * Fuentes de ingreso del menú Ingresos.
     *
     * @return array<string, array{clave: string, label: string, descripcion: string, campos: list<array{key: string, label: string, subtipo: string}>}>
     */
    public static function fuentesIngreso(): array
    {
        return [
            'renta' => [
                'clave'       => 'renta',
                'label'       => 'Renta',
                'descripcion' => 'Ingreso laboral fijo y variable',
                'campos'      => [
                    ['key' => 'ingreso_fijo', 'label' => 'Fija', 'subtipo' => 'fija'],
                    ['key' => 'ingreso_variable', 'label' => 'Variable', 'subtipo' => 'variable'],
                ],
            ],
            'honorarios' => [
                'clave'       => 'honorarios',
                'label'       => 'Honorarios',
                'descripcion' => 'Honorarios profesionales, comisiones y bonos',
                'campos'      => [
                    ['key' => 'ingreso_honorarios', 'label' => 'Honorarios', 'subtipo' => 'honorarios'],
                    ['key' => 'ingreso_comisiones', 'label' => 'Comisiones', 'subtipo' => 'comisiones'],
                    ['key' => 'ingreso_bonos', 'label' => 'Bonos', 'subtipo' => 'bonos'],
                ],
            ],
            'inversiones' => [
                'clave'       => 'inversiones',
                'label'       => 'Inversiones',
                'descripcion' => 'Arriendos, mutuos y dividendos',
                'campos'      => [
                    ['key' => 'ingreso_arriendos', 'label' => 'Arriendos', 'subtipo' => 'arriendos'],
                    ['key' => 'ingreso_mutuos', 'label' => 'Mutuos', 'subtipo' => 'mutuos'],
                    ['key' => 'ingreso_dividendos', 'label' => 'Dividendos', 'subtipo' => 'dividendos'],
                ],
            ],
            'otros' => [
                'clave'       => 'otros',
                'label'       => 'Otros',
                'descripcion' => 'Pensiones, donaciones, indemnizaciones, Capital ER e Intereses ER',
                'campos'      => [
                    ['key' => 'ingreso_pensiones', 'label' => 'Pensiones', 'subtipo' => 'pensiones'],
                    ['key' => 'ingreso_donaciones', 'label' => 'Donaciones', 'subtipo' => 'donaciones'],
                    ['key' => 'ingreso_indemnizaciones', 'label' => 'Indemnizaciones', 'subtipo' => 'indemnizaciones'],
                    ['key' => 'ingreso_capital_er', 'label' => 'Capital ER', 'subtipo' => 'capital_er'],
                    ['key' => 'ingreso_intereses_er', 'label' => 'Intereses ER', 'subtipo' => 'intereses_er'],
                ],
            ],
        ];
    }

    /**
     * Fuentes de ingreso que permiten reclasificar tipos entre sí (p. ej. Balance Inversiones ↔ Otros).
     *
     * @return list<string>
     */
    public static function fuentesIngresoReclasificables(): array
    {
        return ['inversiones', 'otros'];
    }

    /**
     * Mapa subtipo → meta (incluye fuente) para tipologías reclasificables.
     *
     * @return array<string, array{fuente: string, fuente_label: string, key: string, label: string, subtipo: string}>
     */
    public static function subtiposIngresoReclasificables(): array
    {
        $out     = [];
        $fuentes = self::fuentesIngreso();
        foreach (self::fuentesIngresoReclasificables() as $clave) {
            $meta = $fuentes[$clave] ?? null;
            if ($meta === null) {
                continue;
            }
            foreach ($meta['campos'] as $c) {
                $out[$c['subtipo']] = [
                    'fuente'       => $clave,
                    'fuente_label' => $meta['label'],
                    'key'          => $c['key'],
                    'label'        => $c['label'],
                    'subtipo'      => $c['subtipo'],
                ];
            }
        }

        return $out;
    }

    public static function fuenteDeSubtipoIngreso(string $subtipo): ?string
    {
        foreach (self::fuentesIngreso() as $clave => $meta) {
            foreach ($meta['campos'] as $c) {
                if ($c['subtipo'] === $subtipo) {
                    return $clave;
                }
            }
        }

        return null;
    }

    /**
     * Fuentes de egreso (pasivos) del menú Egresos — una pestaña por categoría.
     *
     * @return array<string, array{clave: string, label: string, descripcion: string, categoria: string, pasivo_path: string}>
     */
    public static function fuentesEgreso(): array
    {
        return [
            'hipotecarios' => [
                'clave'       => 'hipotecarios',
                'label'       => 'Hipotecarios',
                'descripcion' => 'Dividendos proyectados desde la fecha de registro',
                'categoria'   => 'inmueble',
                'pasivo_path' => '/equity/pasivos/inmuebles',
            ],
            'circulantes' => [
                'clave'       => 'circulantes',
                'label'       => 'Circulantes',
                'descripcion' => 'Cuotas de créditos de corto plazo',
                'categoria'   => 'circulante',
                'pasivo_path' => '/equity/pasivos/circulantes',
            ],
            'otros' => [
                'clave'       => 'otros',
                'label'       => 'Otros',
                'descripcion' => 'Consumo, descuentos y otros pasivos',
                'categoria'   => 'otro',
                'pasivo_path' => '/equity/pasivos/otros',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function opcionesPeriodicidadIngreso(): array
    {
        return [
            'mensual'    => 'Mensual',
            'trimestral' => 'Trimestral',
            'semestral'  => 'Semestral',
            'anual'      => 'Anual',
        ];
    }

    /** @return array<string, string> */
    public static function opcionesEstadoContratoIngreso(): array
    {
        return [
            'vigente' => 'Vigente',
            'vencido' => 'Vencido',
        ];
    }

    /** Convierte monto del contrato a equivalente mensual. */
    public static function montoMensualEquivalente(?float $monto, ?string $periodicidad): float
    {
        if ($monto === null || $monto <= 0) {
            return 0.0;
        }

        return match ($periodicidad) {
            'trimestral' => $monto / 3,
            'semestral'  => $monto / 6,
            'anual'      => $monto / 12,
            default      => $monto, // mensual
        };
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
                'label'     => 'Hipotecarios',
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
            'pasivos-indirecta' => [
                'clave'     => 'pasivos-indirecta',
                'label'     => 'Deuda indirecta',
                'grupo'     => 'Pasivos',
                'table'     => 'equity_pasivos',
                'categoria' => 'indirecta',
            ],
            'pasivos-lineas' => [
                'clave'     => 'pasivos-lineas',
                'label'     => 'Líneas disponibles',
                'grupo'     => 'Pasivos',
                'table'     => 'equity_pasivos',
                'categoria' => 'linea',
            ],
        ];

        return $map[$clave] ?? null;
    }

    /** @return array<int|string, string> */
    public static function opcionesSexo(): array
    {
        return [1 => 'Masculino', 2 => 'Femenino'];
    }

    /** @return array<int|string, string> */
    public static function opcionesNacionalidad(): array
    {
        return [
            1 => 'Chilena',
            2 => 'Extranjera',
            3 => 'Extranjera c/ Permanencia',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesEstadoCivil(): array
    {
        return [
            1 => 'Soltero',
            2 => 'Casado',
            3 => 'Viudo',
            4 => 'Divorciado',
            5 => 'Conviviente Civil',
        ];
    }

    /** @return array<string, string> */
    public static function opcionesRegimenPatrimonial(): array
    {
        return [
            '2.1' => 'Casado · Separación total de Bienes',
            '2.2' => 'Casado · Sociedad Conyugal',
            '2.3' => 'Casado · Participación en los Gananciales',
            '5.1' => 'Conviviente Civil · Separación de Bienes',
            '5.2' => 'Conviviente Civil · Comunidad de Bienes',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesLugarHabita(): array
    {
        return [
            1 => 'Propia sin deuda',
            2 => 'Propia con deuda',
            3 => 'Propia de Cónyuge',
            4 => 'Arrendada',
            5 => 'Familiar / Padres',
            6 => 'Fiscal / Empleador',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesNivelEstudios(): array
    {
        return [
            1 => 'Básica',
            2 => 'Media',
            3 => 'Técnica',
            4 => 'Univ. Incompleta',
            5 => 'Univ. Completa',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoActividad(): array
    {
        return [
            1 => 'Dependiente',
            2 => 'Independiente',
            3 => 'Pensionado',
            4 => 'No trabaja',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoContrato(): array
    {
        return [
            1 => 'Indefinido',
            2 => 'Plazo Fijo',
            3 => 'A contrata',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoRenta(): array
    {
        return [
            1 => 'Fija',
            2 => 'Variable',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesComplementaRenta(): array
    {
        return [
            1 => 'Sí',
            2 => 'No',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoInmueble(): array
    {
        return [
            1 => 'Casa',
            2 => 'Departamento',
            3 => 'Oficina',
            4 => 'Local Comercial',
            5 => 'Estacionamiento',
            6 => 'Bodega',
            7 => 'Terreno',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesGarantiaInmueble(): array
    {
        return [
            1 => 'Gtía. en Consorcio',
            2 => 'Gtía. en otra Inst.',
            3 => 'Deja en Gtía.',
            4 => 'No está en Gtía.',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesSiNo(): array
    {
        return [
            1 => 'Sí',
            2 => 'No',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoVehiculo(): array
    {
        return [
            1 => 'Automóvil',
            2 => 'Camioneta',
            3 => 'Jeep',
            4 => 'Station Wagon',
            5 => 'Furgón',
            6 => 'Minibus',
            7 => 'Bus',
            8 => 'Camión',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoOtroActivo(): array
    {
        return [
            1 => 'Depósito a Plazo',
            2 => 'Acciones',
            3 => 'Bonos',
            4 => 'Cuentas de Ahorro',
            5 => 'Otros',
        ];
    }

    /** @return array<int|string, string> */
    public static function opcionesTipoLineaDisponible(): array
    {
        return [
            1 => 'Crédito libre disposición',
            2 => 'Línea de crédito',
            3 => 'Tarjeta de crédito',
            4 => 'Otros',
        ];
    }


    public static function labelOpcion(array $opts, mixed $value): string
    {

        if ($value === null || $value === '') {
            return '—';
        }
        $key = is_numeric($value) ? (int) $value : (string) $value;

        return (string) ($opts[$key] ?? $opts[(string) $value] ?? $value);
    }
}

