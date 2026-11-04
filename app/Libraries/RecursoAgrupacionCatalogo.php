<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Catálogo genérico Administración → Recursos:
 * Agrupación → Servicios → Entidades prestadoras.
 * Por empresa: checkbox en agrupación + checkbox en cada entidad.
 */
final class RecursoAgrupacionCatalogo
{
    /**
     * @return list<array{
     *   clave: string,
     *   nombre: string,
     *   servicios: list<array{clave: string, nombre: string, entidades: list<array{clave: string, nombre: string}>}>
     * }>
     */
    public static function catalogo(): array
    {
        return [
            [
                'clave'     => 'basicos_sanitarios',
                'nombre'    => 'Servicios Básicos y Sanitarios',
                'servicios' => [
                    [
                        'clave'     => 'agua',
                        'nombre'    => 'Agua',
                        'entidades' => self::ents([
                            'aguas_andinas'      => 'Aguas Andinas',
                            'aguas_antofagasta'  => 'Aguas Antofagasta',
                            'aguas_araucania'    => 'Aguas Araucanía',
                            'aguas_cordillera'   => 'Aguas Cordillera',
                            'aguas_magallanes'   => 'Aguas Magallanes',
                            'aguas_del_valle'    => 'Aguas del Valle',
                            'essbio'             => 'Essbio',
                            'esval'              => 'Esval',
                            'smapa'              => 'Smapa',
                            'suralis'            => 'Suralis (ex-Essal)',
                            'servipag_agua'      => 'Servipag',
                        ]),
                    ],
                    [
                        'clave'     => 'luz',
                        'nombre'    => 'Luz',
                        'entidades' => self::ents([
                            'enel'           => 'Enel',
                            'cge'            => 'CGE',
                            'chilquinta'     => 'Chilquinta',
                            'frontel'        => 'Frontel',
                            'saesa'          => 'Saesa',
                            'edelaysen'      => 'Edelaysen',
                            'edelmag'        => 'Edelmag',
                            'luz_osorno'     => 'Luz Osorno',
                            'servipag_luz'   => 'Servipag',
                        ]),
                    ],
                    [
                        'clave'     => 'gas',
                        'nombre'    => 'Gas',
                        'entidades' => self::ents([
                            'metrogas'       => 'Metrogas',
                            'abastible'      => 'Abastible',
                            'gasco'          => 'Gasco',
                            'lipigas'        => 'Lipigas',
                            'gasvalpo'       => 'Gasvalpo',
                            'intergas'       => 'Intergas',
                            'servipag_gas'   => 'Servipag',
                        ]),
                    ],
                ],
            ],
            [
                'clave'     => 'telecomunicaciones',
                'nombre'    => 'Telecomunicaciones y Conectividad',
                'servicios' => [
                    [
                        'clave'     => 'internet_tv',
                        'nombre'    => 'Internet / TV Cable y Satelital',
                        'entidades' => self::ents([
                            'vtr'              => 'VTR',
                            'movistar'         => 'Movistar',
                            'claro'            => 'Claro',
                            'entel'            => 'Entel',
                            'gtd'              => 'GTD',
                            'wom'              => 'WOM',
                            'mundo_pacifico'   => 'Mundo Pacífico',
                            'directv'          => 'DirecTV',
                            'zapping'          => 'Zapping',
                            'servipag_internet'=> 'Servipag',
                        ]),
                    ],
                    [
                        'clave'     => 'telefonia_celular',
                        'nombre'    => 'Telefonía Celular',
                        'entidades' => self::ents([
                            'entel_cel'        => 'Entel',
                            'movistar_cel'     => 'Movistar',
                            'claro_cel'        => 'Claro',
                            'wom_cel'          => 'WOM',
                            'virgin_mobile'    => 'Virgin Mobile',
                            'vtr_cel'          => 'VTR',
                            'servipag_celular' => 'Servipag',
                        ]),
                    ],
                ],
            ],
            [
                'clave'     => 'transporte_movilidad',
                'nombre'    => 'Transporte y Movilidad',
                'servicios' => [
                    [
                        'clave'     => 'autopistas_tag',
                        'nombre'    => 'Autopistas (TAG)',
                        'entidades' => self::ents([
                            'autopista_central'       => 'Autopista Central',
                            'costanera_norte'         => 'Costanera Norte',
                            'vespucio_norte'          => 'Vespucio Norte',
                            'vespucio_sur'            => 'Vespucio Sur',
                            'vespucio_oriente_avo1'   => 'Vespucio Oriente (AVO1)',
                            'ruta_del_maipo'          => 'Ruta del Maipo',
                            'ruta_78'                 => 'Ruta 78',
                            'ruta_68'                 => 'Ruta 68',
                            'autopista_valles_biobio' => 'Autopista Valles del Biobío',
                        ]),
                    ],
                ],
            ],
            [
                'clave'     => 'bancos_financieras',
                'nombre'    => 'Bancos, Financieras y Créditos',
                'servicios' => [
                    [
                        'clave'     => 'bancos_tarjetas',
                        'nombre'    => 'Bancos y Tarjetas de Crédito',
                        'entidades' => self::ents([
                            'banco_de_chile'   => 'Banco de Chile',
                            'banco_santander'  => 'Banco Santander',
                            'bci'              => 'Bci',
                            'banco_estado'     => 'Banco Estado',
                            'itau'             => 'Itaú',
                            'scotiabank'       => 'Scotiabank',
                            'banco_falabella'  => 'Banco Falabella',
                            'banco_ripley'     => 'Banco Ripley',
                            'banco_bice'       => 'Banco BICE',
                            'consorcio_banco'  => 'Consorcio',
                        ]),
                    ],
                    [
                        'clave'     => 'casas_comerciales',
                        'nombre'    => 'Casas Comerciales / Retail',
                        'entidades' => self::ents([
                            'falabella' => 'Falabella',
                            'ripley'    => 'Ripley',
                            'paris'     => 'Paris',
                            'la_polar'  => 'La Polar',
                            'hites'     => 'Hites',
                            'johnson'   => 'Johnson',
                            'abcdin'    => 'ABCDIN',
                        ]),
                    ],
                ],
            ],
            [
                'clave'     => 'seguros_educacion_otros',
                'nombre'    => 'Seguros, Educación y Otros',
                'servicios' => [
                    [
                        'clave'     => 'seguros_prevision',
                        'nombre'    => 'Seguros y Previsión',
                        'entidades' => self::ents([
                            'consorcio_seguros'      => 'Consorcio Seguros',
                            'banchile_seguros'       => 'Banchile Seguros',
                            'metlife'                => 'MetLife',
                            'chilena_consolidada'    => 'Chilena Consolidada',
                            'afp_habitat'            => 'AFP Habitat',
                            'afp_capital'            => 'AFP Capital',
                            'afp_cuprum'             => 'AFP Cuprum',
                            'afp_provida'            => 'AFP Provida',
                            'afp_uno'                => 'AFP Uno',
                            'isapre_banmedica'       => 'Isapre Banmédica',
                            'isapre_colmena'         => 'Isapre Colmena',
                            'isapre_consalud'        => 'Isapre Consalud',
                            'isapre_nueva_mas_vida'  => 'Isapre Nueva Más Vida',
                            'isapre_vida_tres'       => 'Isapre Vida Tres',
                        ]),
                    ],
                    [
                        'clave'     => 'educacion',
                        'nombre'    => 'Educación',
                        'entidades' => self::ents([
                            'u_chile'              => 'Universidad de Chile',
                            'u_catolica'           => 'Pontificia Universidad Católica',
                            'u_santa_maria'        => 'Universidad Técnica Federico Santa María',
                            'u_adolfo_ibanez'      => 'Universidad Adolfo Ibáñez',
                            'u_diego_portales'     => 'Universidad Diego Portales',
                            'institutos_profesionales' => 'Institutos profesionales',
                            'centros_formacion_tecnica' => 'Centros de formación técnica',
                            'colegios_particulares' => 'Colegios particulares',
                        ]),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $map
     * @return list<array{clave: string, nombre: string}>
     */
    private static function ents(array $map): array
    {
        $out = [];
        foreach ($map as $clave => $nombre) {
            $out[] = ['clave' => $clave, 'nombre' => $nombre];
        }

        return $out;
    }

    /** Idempotent: materializa catálogo + filas de checkbox por empresa (default OFF). */
    public static function ensureForEmpresa(string $empresaId, ?\CodeIgniter\Database\BaseConnection $db = null): void
    {
        helper('id');
        $db ??= db_connect();
        $now = date('Y-m-d H:i:s');

        $ordenA = 0;
        foreach (self::catalogo() as $agr) {
            $ordenA++;
            $agrId = self::ensureAgrupacion($db, $agr['clave'], $agr['nombre'], $ordenA, $now);
            self::ensureEmpresaAgrupacion($db, $empresaId, $agrId, $now);

            $ordenS = 0;
            foreach ($agr['servicios'] as $svc) {
                $ordenS++;
                $svcId = self::ensureServicio($db, $agrId, $svc['clave'], $svc['nombre'], $ordenS, $now);
                $ordenE = 0;
                foreach ($svc['entidades'] as $ent) {
                    $ordenE++;
                    $entId = self::ensureEntidad($db, $svcId, $ent['clave'], $ent['nombre'], $ordenE, $now);
                    self::ensureEmpresaEntidad($db, $empresaId, $entId, $now);
                }
            }
        }
    }

    public static function ensureForAllEmpresas(?\CodeIgniter\Database\BaseConnection $db = null): int
    {
        $db ??= db_connect();
        $empresas = $db->table('empresas')->select('id')->get()->getResultArray();
        foreach ($empresas as $e) {
            self::ensureForEmpresa((string) $e['id'], $db);
        }

        return count($empresas);
    }

    private static function ensureAgrupacion(
        \CodeIgniter\Database\BaseConnection $db,
        string $clave,
        string $nombre,
        int $orden,
        string $now
    ): string {
        $row = $db->table('recurso_agrupaciones')->where('clave_sistema', $clave)->get()->getRowArray();
        if (is_array($row)) {
            $db->table('recurso_agrupaciones')->where('id', $row['id'])->update([
                'nombre'     => $nombre,
                'orden'      => $orden,
                'updated_at' => $now,
            ]);

            return (string) $row['id'];
        }
        $id = new_id();
        $db->table('recurso_agrupaciones')->insert([
            'id'            => $id,
            'clave_sistema' => $clave,
            'nombre'        => $nombre,
            'orden'         => $orden,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id;
    }

    private static function ensureServicio(
        \CodeIgniter\Database\BaseConnection $db,
        string $agrupacionId,
        string $clave,
        string $nombre,
        int $orden,
        string $now
    ): string {
        $row = $db->table('recurso_servicios')->where('clave_sistema', $clave)->get()->getRowArray();
        if (is_array($row)) {
            $db->table('recurso_servicios')->where('id', $row['id'])->update([
                'agrupacion_id' => $agrupacionId,
                'nombre'        => $nombre,
                'orden'         => $orden,
                'updated_at'    => $now,
            ]);

            return (string) $row['id'];
        }
        $id = new_id();
        $db->table('recurso_servicios')->insert([
            'id'            => $id,
            'agrupacion_id' => $agrupacionId,
            'clave_sistema' => $clave,
            'nombre'        => $nombre,
            'orden'         => $orden,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id;
    }

    private static function ensureEntidad(
        \CodeIgniter\Database\BaseConnection $db,
        string $servicioId,
        string $clave,
        string $nombre,
        int $orden,
        string $now
    ): string {
        $row = $db->table('recurso_entidades')->where('clave_sistema', $clave)->get()->getRowArray();
        if (is_array($row)) {
            $db->table('recurso_entidades')->where('id', $row['id'])->update([
                'servicio_id' => $servicioId,
                'nombre'      => $nombre,
                'orden'       => $orden,
                'updated_at'  => $now,
            ]);

            return (string) $row['id'];
        }
        $id = new_id();
        $db->table('recurso_entidades')->insert([
            'id'            => $id,
            'servicio_id'   => $servicioId,
            'clave_sistema' => $clave,
            'nombre'        => $nombre,
            'orden'         => $orden,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id;
    }

    private static function ensureEmpresaAgrupacion(
        \CodeIgniter\Database\BaseConnection $db,
        string $empresaId,
        string $agrupacionId,
        string $now
    ): void {
        $row = $db->table('empresa_recurso_agrupacion')
            ->where('empresa_id', $empresaId)
            ->where('agrupacion_id', $agrupacionId)
            ->get()
            ->getRowArray();
        if (is_array($row)) {
            return;
        }
        $db->table('empresa_recurso_agrupacion')->insert([
            'id'            => new_id(),
            'empresa_id'    => $empresaId,
            'agrupacion_id' => $agrupacionId,
            'marcado'       => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private static function ensureEmpresaEntidad(
        \CodeIgniter\Database\BaseConnection $db,
        string $empresaId,
        string $entidadId,
        string $now
    ): void {
        $row = $db->table('empresa_recurso_entidad')
            ->where('empresa_id', $empresaId)
            ->where('entidad_id', $entidadId)
            ->get()
            ->getRowArray();
        if (is_array($row)) {
            return;
        }
        $db->table('empresa_recurso_entidad')->insert([
            'id'         => new_id(),
            'empresa_id' => $empresaId,
            'entidad_id' => $entidadId,
            'marcado'    => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function setAgrupacionMarcada(string $empresaId, string $agrupacionId, bool $marcado): bool
    {
        self::ensureForEmpresa($empresaId);
        $db = db_connect();
        $row = $db->table('empresa_recurso_agrupacion')
            ->where('empresa_id', $empresaId)
            ->where('agrupacion_id', $agrupacionId)
            ->get()
            ->getRowArray();
        if (! is_array($row)) {
            return false;
        }
        $db->table('empresa_recurso_agrupacion')->where('id', $row['id'])->update([
            'marcado'    => $marcado ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public static function setEntidadMarcada(string $empresaId, string $entidadId, bool $marcado): bool
    {
        self::ensureForEmpresa($empresaId);
        $db = db_connect();
        $row = $db->table('empresa_recurso_entidad')
            ->where('empresa_id', $empresaId)
            ->where('entidad_id', $entidadId)
            ->get()
            ->getRowArray();
        if (! is_array($row)) {
            return false;
        }
        $db->table('empresa_recurso_entidad')->where('id', $row['id'])->update([
            'marcado'    => $marcado ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Árbol para UI con estado de checkboxes de la empresa.
     *
     * @return list<array<string, mixed>>
     */
    public static function treeForEmpresa(string $empresaId): array
    {
        self::ensureForEmpresa($empresaId);
        $db = db_connect();

        $agrups = $db->table('recurso_agrupaciones')->orderBy('orden', 'ASC')->get()->getResultArray();
        $svcs   = $db->table('recurso_servicios')->orderBy('orden', 'ASC')->get()->getResultArray();
        $ents   = $db->table('recurso_entidades')->orderBy('orden', 'ASC')->get()->getResultArray();

        $agrMarc = [];
        foreach ($db->table('empresa_recurso_agrupacion')->where('empresa_id', $empresaId)->get()->getResultArray() as $r) {
            $agrMarc[(string) $r['agrupacion_id']] = (int) ($r['marcado'] ?? 0) === 1;
        }
        $entMarc = [];
        foreach ($db->table('empresa_recurso_entidad')->where('empresa_id', $empresaId)->get()->getResultArray() as $r) {
            $entMarc[(string) $r['entidad_id']] = (int) ($r['marcado'] ?? 0) === 1;
        }

        $entsBySvc = [];
        foreach ($ents as $e) {
            $entsBySvc[(string) $e['servicio_id']][] = [
                'id'      => (string) $e['id'],
                'clave'   => (string) $e['clave_sistema'],
                'nombre'  => (string) $e['nombre'],
                'marcado' => $entMarc[(string) $e['id']] ?? false,
            ];
        }
        $svcsByAgr = [];
        foreach ($svcs as $s) {
            $sid = (string) $s['id'];
            $svcsByAgr[(string) $s['agrupacion_id']][] = [
                'id'        => $sid,
                'clave'     => (string) $s['clave_sistema'],
                'nombre'    => (string) $s['nombre'],
                'entidades' => $entsBySvc[$sid] ?? [],
            ];
        }

        $out = [];
        foreach ($agrups as $a) {
            $aid = (string) $a['id'];
            $out[] = [
                'id'        => $aid,
                'clave'     => (string) $a['clave_sistema'],
                'nombre'    => (string) $a['nombre'],
                'marcado'   => $agrMarc[$aid] ?? false,
                'servicios' => $svcsByAgr[$aid] ?? [],
            ];
        }

        return $out;
    }
}
