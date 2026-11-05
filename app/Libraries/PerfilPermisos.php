<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Catálogo de permisos (menú + capacidades) para perfiles estereotipados.
 * Por defecto cada estereotipo arranca con su set “completo”; el admin desmarca.
 */
final class PerfilPermisos
{
    public const ESTEREOTIPO_MASTER  = 'MASTER';
    public const ESTEREOTIPO_JEFE    = 'JEFE';
    public const ESTEREOTIPO_SOCIO   = 'SOCIO';
    public const ESTEREOTIPO_EXTERNO = 'EXTERNO';

    public const ESTEREOTIPOS = [
        self::ESTEREOTIPO_MASTER,
        self::ESTEREOTIPO_JEFE,
        self::ESTEREOTIPO_SOCIO,
        self::ESTEREOTIPO_EXTERNO,
    ];

    /** @return list<array{clave: string, grupo: string, label: string}> */
    public static function catalogo(): array
    {
        return [
            // Menú Principal
            ['clave' => 'menu.inicio', 'grupo' => 'Menú · Principal', 'label' => 'Inicio'],
            ['clave' => 'menu.documentos', 'grupo' => 'Menú · Principal', 'label' => 'Documentos'],
            ['clave' => 'menu.documentos_tipos', 'grupo' => 'Menú · Principal', 'label' => 'Documentos → Tipos'],
            ['clave' => 'menu.documentos_archivados', 'grupo' => 'Menú · Principal', 'label' => 'Documentos → Archivados'],
            ['clave' => 'menu.documentos_respaldo', 'grupo' => 'Menú · Principal', 'label' => 'Documentos → Respaldo'],
            ['clave' => 'menu.formularios', 'grupo' => 'Menú · Principal', 'label' => 'Formulario'],
            ['clave' => 'menu.contratos', 'grupo' => 'Menú · Principal', 'label' => 'Contratos'],
            ['clave' => 'menu.socios', 'grupo' => 'Menú · Principal', 'label' => 'Socios'],
            // Canales
            ['clave' => 'menu.correo', 'grupo' => 'Menú · Canales', 'label' => 'Correo'],
            ['clave' => 'menu.flujos', 'grupo' => 'Menú · Canales', 'label' => 'Flujos'],
            // Administración
            ['clave' => 'menu.organizacion', 'grupo' => 'Menú · Administración', 'label' => 'Organización'],
            ['clave' => 'menu.departamentos', 'grupo' => 'Menú · Administración', 'label' => 'Departamentos'],
            ['clave' => 'menu.recursos', 'grupo' => 'Menú · Administración', 'label' => 'Recursos'],
            ['clave' => 'menu.perfiles', 'grupo' => 'Menú · Administración', 'label' => 'Perfiles'],
            // Capacidades documentos / contratos
            ['clave' => 'cap.docs_upload', 'grupo' => 'Documentos', 'label' => 'Subir documentos (general)'],
            ['clave' => 'cap.docs_delete', 'grupo' => 'Documentos', 'label' => 'Eliminar documentos (general)'],
            ['clave' => 'cap.docs_archive', 'grupo' => 'Documentos', 'label' => 'Archivar documentos'],
            ['clave' => 'cap.contratos_upload', 'grupo' => 'Contratos', 'label' => 'Subir contratos'],
            ['clave' => 'cap.contratos_delete', 'grupo' => 'Contratos', 'label' => 'Eliminar contratos'],
            ['clave' => 'cap.socios_upload', 'grupo' => 'Socios', 'label' => 'Subir docs de socios'],
            ['clave' => 'cap.socios_delete', 'grupo' => 'Socios', 'label' => 'Eliminar docs de socios'],
            ['clave' => 'cap.socios_contratos_upload', 'grupo' => 'Socios', 'label' => 'Subir contratos de socios'],
            ['clave' => 'cap.socios_contratos_delete', 'grupo' => 'Socios', 'label' => 'Eliminar contratos de socios'],
            ['clave' => 'cap.recursos_contratos_upload', 'grupo' => 'Recursos', 'label' => 'Subir contratos desde Recursos'],
        ];
    }

    /** @return list<string> */
    public static function allClaves(): array
    {
        return array_column(self::catalogo(), 'clave');
    }

    public static function labelEstereotipo(string $estereotipo): string
    {
        return match ($estereotipo) {
            self::ESTEREOTIPO_MASTER  => 'Administrador Master',
            self::ESTEREOTIPO_JEFE    => 'Jefe de Sucursal',
            self::ESTEREOTIPO_SOCIO   => 'Socio',
            self::ESTEREOTIPO_EXTERNO => 'Externo',
            default                   => $estereotipo,
        };
    }

    /**
     * Defaults ON for each stereotype (full access within that stereotype’s world).
     *
     * @return array<string, bool> clave => habilitado
     */
    public static function defaultsFor(string $estereotipo): array
    {
        $all = self::allClaves();
        $off = array_fill_keys($all, false);

        $on = match ($estereotipo) {
            self::ESTEREOTIPO_MASTER => $all,
            self::ESTEREOTIPO_SOCIO => $all, // acceso completo + Socios
            self::ESTEREOTIPO_JEFE => [
                'menu.inicio',
                'menu.documentos',
                'menu.formularios',
                'menu.contratos',
                'menu.correo',
                'menu.flujos',
                'cap.docs_upload',
                'cap.docs_delete',
                'cap.contratos_upload',
                // contratos_delete off: hoy Jefe sube contratos (sucursal null) pero no puede borrar
            ],
            self::ESTEREOTIPO_EXTERNO => [
                'menu.inicio',
                'menu.documentos',
                'menu.contratos',
                'menu.correo',
                'menu.flujos',
                // sin upload/delete: solo lectura vía autorizaciones
            ],
            default => [],
        };

        foreach ($on as $clave) {
            if (isset($off[$clave])) {
                $off[$clave] = true;
            }
        }

        return $off;
    }

    /** Maps stereotype → membership rol (+ es_socio). */
    public static function membershipFor(string $estereotipo): array
    {
        return match ($estereotipo) {
            self::ESTEREOTIPO_MASTER => [
                'rol'      => Roles::ADMINISTRADOR_MASTER,
                'es_socio' => 0,
            ],
            self::ESTEREOTIPO_JEFE => [
                'rol'      => Roles::JEFE_SUCURSAL,
                'es_socio' => 0,
            ],
            self::ESTEREOTIPO_SOCIO => [
                'rol'      => Roles::ADMINISTRADOR_MASTER,
                'es_socio' => 1,
            ],
            self::ESTEREOTIPO_EXTERNO => [
                'rol'      => Roles::CONTADOR,
                'es_socio' => 0,
            ],
            default => [
                'rol'      => Roles::CONTADOR,
                'es_socio' => 0,
            ],
        };
    }

    public static function displayName(string $estereotipo, ?string $calificativo): string
    {
        $base = self::labelEstereotipo($estereotipo);
        $cal  = trim((string) $calificativo);

        return $cal === '' ? $base : $base . ' · ' . $cal;
    }
}
