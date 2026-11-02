<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\DocumentStorage;
use App\Libraries\PlanEntitlement;
use App\Libraries\Roles;
use CodeIgniter\Database\Seeder;

/**
 * Demo tenant data aligned with Next.js prototype seed (product reference).
 * Password for all users: demo123
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        helper('id');

        $now  = date('Y-m-d H:i:s');
        $hash = password_hash('demo123', PASSWORD_DEFAULT);

        // Clean slate (order matters for FKs)
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'promocion_sucursal_auditoria',
            'canal_paquete_auditoria', 'canal_paquete_documentos', 'canal_paquetes',
            'fiscalizacion_auditoria', 'fiscalizacion_documentos', 'fiscalizacion_solicitudes',
            'documento_cambios', 'documento_estado_historial', 'documento_versiones',
            'documento_autorizaciones', 'documentos', 'documento_tipos', 'documento_secciones',
            'membresias', 'temas_interes', 'actividades', 'sucursales',
            'departamentos', 'usuarios', 'empresas', 'planes',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->truncate();
            }
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');

        // Wipe demo files under storage root
        $root = DocumentStorage::root();
        if (is_dir($root)) {
            $this->rmTree($root);
        }
        if (! is_dir($root)) {
            mkdir($root, 0750, true);
        }

        $planFree    = new_id();
        $planStarter = new_id();
        $planPro     = new_id();

        $this->db->table('planes')->insertBatch([
            [
                'id' => $planFree, 'codigo' => PlanEntitlement::CODIGO_FREE, 'nombre' => 'Free Filial',
                'descripcion' => 'Gratis para tipo (b): hasta 3 usuarios y 10 GB.',
                'tipo_aplicable' => 'b', 'max_usuarios' => PlanEntitlement::FREE_MAX_USERS,
                'max_storage_bytes' => PlanEntitlement::FREE_MAX_STORAGE, 'es_gratuito' => 1,
                'precio_mensual' => 0, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $planStarter, 'codigo' => PlanEntitlement::CODIGO_STARTER, 'nombre' => 'Starter',
                'descripcion' => 'Plan de pago inicial: 10 usuarios / 50 GB.',
                'tipo_aplicable' => 'ambos', 'max_usuarios' => 10,
                'max_storage_bytes' => 50 * 1024 * 1024 * 1024, 'es_gratuito' => 0,
                'precio_mensual' => 29.90, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $planPro, 'codigo' => PlanEntitlement::CODIGO_PRO, 'nombre' => 'Pro',
                'descripcion' => 'Plan ampliado: 50 usuarios / 200 GB.',
                'tipo_aplicable' => 'ambos', 'max_usuarios' => 50,
                'max_storage_bytes' => 200 * 1024 * 1024 * 1024, 'es_gratuito' => 0,
                'precio_mensual' => 99.90, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $empresaDemo = new_id();
        $empresaBeta = new_id();

        $this->db->table('empresas')->insertBatch([
            [
                'id' => $empresaDemo, 'nombre' => 'Empresa Demo', 'slug' => 'empresa-demo',
                'rut' => '76.123.456-7', 'activa' => 1,
                'tipo' => PlanEntitlement::TIPO_A, 'plan_id' => $planStarter,
                'subscription_status' => PlanEntitlement::STATUS_ACTIVE,
                'seats_limit' => 10, 'storage_limit_bytes' => 50 * 1024 * 1024 * 1024,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $empresaBeta, 'nombre' => 'Empresa Beta', 'slug' => 'empresa-beta',
                'rut' => '77.987.654-3', 'activa' => 1,
                'tipo' => PlanEntitlement::TIPO_B, 'plan_id' => $planFree,
                'subscription_status' => PlanEntitlement::STATUS_FREE,
                'seats_limit' => PlanEntitlement::FREE_MAX_USERS,
                'storage_limit_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $deptoOps   = new_id();
        $deptoAdmin = new_id();
        $deptoBeta  = new_id();

        $this->db->table('departamentos')->insertBatch([
            ['id' => $deptoOps, 'empresa_id' => $empresaDemo, 'nombre' => 'Operaciones', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $deptoAdmin, 'empresa_id' => $empresaDemo, 'nombre' => 'Administración', 'created_at' => $now, 'updated_at' => $now],
            ['id' => $deptoBeta, 'empresa_id' => $empresaBeta, 'nombre' => 'General', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $sucNorte = new_id();
        $sucSur   = new_id();
        $sucBeta  = new_id();

        $this->db->table('sucursales')->insertBatch([
            [
                'id' => $sucNorte, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'nombre' => 'Sucursal Norte', 'codigo' => 'NTE', 'activa' => 1, 'estado' => 'ACTIVA',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $sucSur, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'nombre' => 'Sucursal Sur', 'codigo' => 'SUR', 'activa' => 1, 'estado' => 'ACTIVA',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $sucBeta, 'empresa_id' => $empresaBeta, 'departamento_id' => $deptoBeta,
                'nombre' => 'Casa Matriz', 'codigo' => 'CM', 'activa' => 1, 'estado' => 'ACTIVA',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $this->db->table('actividades')->insertBatch([
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps, 'nombre' => 'Logística', 'created_at' => $now, 'updated_at' => $now],
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps, 'nombre' => 'Mantención', 'created_at' => $now, 'updated_at' => $now],
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoAdmin, 'nombre' => 'Finanzas', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->db->table('temas_interes')->insertBatch([
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps, 'nombre' => 'Seguridad', 'created_at' => $now, 'updated_at' => $now],
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps, 'nombre' => 'Calidad', 'created_at' => $now, 'updated_at' => $now],
            ['id' => new_id(), 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoAdmin, 'nombre' => 'Compliance', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $users = [
            'admin'          => ['id' => new_id(), 'email' => 'admin@repositoria.local', 'nombre' => 'Admin Master'],
            'jefeNorte'      => ['id' => new_id(), 'email' => 'jefe.norte@repositoria.local', 'nombre' => 'Jefe Norte'],
            'jefeSur'        => ['id' => new_id(), 'email' => 'jefe.sur@repositoria.local', 'nombre' => 'Jefe Sur'],
            'contador'       => ['id' => new_id(), 'email' => 'contador@repositoria.local', 'nombre' => 'Contador Demo'],
            'abogado'        => ['id' => new_id(), 'email' => 'abogado@repositoria.local', 'nombre' => 'Abogado Demo'],
            'prevencionista' => ['id' => new_id(), 'email' => 'prevencionista@repositoria.local', 'nombre' => 'Prevencionista Demo'],
            'multi'          => ['id' => new_id(), 'email' => 'multi@repositoria.local', 'nombre' => 'Usuario Multi'],
        ];

        $userRows = [];
        foreach ($users as $key => $u) {
            $userRows[] = [
                'id' => $u['id'], 'email' => $u['email'], 'nombre' => $u['nombre'],
                'rut' => null, 'password_hash' => $hash, 'activo' => 1,
                'es_admin_central' => $key === 'admin' ? 1 : 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('usuarios')->insertBatch($userRows);

        $membRows = [
            [$users['admin']['id'], $empresaDemo, Roles::ADMINISTRADOR_MASTER, null],
            [$users['jefeNorte']['id'], $empresaDemo, Roles::JEFE_SUCURSAL, $sucNorte],
            [$users['jefeSur']['id'], $empresaDemo, Roles::JEFE_SUCURSAL, $sucSur],
            [$users['contador']['id'], $empresaDemo, Roles::CONTADOR, null],
            [$users['abogado']['id'], $empresaDemo, Roles::ABOGADO, null],
            [$users['prevencionista']['id'], $empresaDemo, Roles::PREVENCIONISTA, null],
            [$users['multi']['id'], $empresaDemo, Roles::CONTADOR, null],
            [$users['multi']['id'], $empresaBeta, Roles::ADMINISTRADOR_MASTER, null],
        ];

        $batch = [];
        foreach ($membRows as [$uid, $eid, $rol, $sid]) {
            $batch[] = [
                'id' => new_id(), 'empresa_id' => $eid, 'usuario_id' => $uid,
                'rol' => $rol, 'sucursal_id' => $sid, 'activa' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('membresias')->insertBatch($batch);

        // --- Fase 2: secciones + documentos de ejemplo ---
        $secOpsGeneral = new_id();
        $secOpsNorte   = new_id();
        $secOpsSur     = new_id();
        $secAdminCorp  = new_id();

        $this->db->table('documento_secciones')->insertBatch([
            [
                'id' => $secOpsGeneral, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'sucursal_id' => null, 'nombre' => 'General', 'codigo' => 'GENERAL',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $secOpsNorte, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'sucursal_id' => $sucNorte, 'nombre' => 'Liquidaciones', 'codigo' => 'LIQUIDACIONES',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $secOpsSur, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'sucursal_id' => $sucSur, 'nombre' => 'Contratos', 'codigo' => 'CONTRATOS',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $secAdminCorp, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoAdmin,
                'sucursal_id' => null, 'nombre' => 'Contratos', 'codigo' => 'CONTRATOS',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // Catálogo de tipos = los cinco nombres de Felipe (NO son estados del ciclo).
        $tipoIds = [];
        $tipoRows = [];
        foreach (\App\Libraries\DocumentLifecycle::catalogoTiposSemilla() as $t) {
            $tid = new_id();
            $tipoIds[$t['codigo']] = $tid;
            $tipoRows[] = [
                'id' => $tid, 'empresa_id' => $empresaDemo,
                'nombre' => $t['nombre'], 'codigo' => $t['codigo'], 'modalidad' => $t['modalidad'],
                'descripcion' => $t['descripcion'],
                'activo' => 1, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        $this->db->table('documento_tipos')->insertBatch($tipoRows);

        $docManual = new_id();
        $docLiqNorte = new_id();
        $docContratoPrev = new_id(); // id1 vencido/renovado
        $docContratoSur = new_id();  // id2 vigente (renovación)
        $docPolitica = new_id();
        $docMinuta = new_id();

        $vencePasado  = date('Y-m-d', strtotime('-5 days'));
        $vencePronto  = date('Y-m-d', strtotime('+20 days'));
        $venceFuturo  = date('Y-m-d', strtotime('+385 days'));

        $samples = [
            [
                'id' => $docManual,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsGeneral,
                'sucursal_id' => null,
                'tipo_id' => $tipoIds['EN_REVISION'],
                'estado' => 'EN_ELABORACION',
                'version_actual' => 1,
                'vence_at' => null,
                'alerta_dias' => null,
                'fecha_termino' => date('Y-m-d', strtotime('+21 days')),
                'cadena_id' => $docManual,
                'renueva_de_id' => null,
                'cabecera' => [
                    'asunto' => 'Manual operaciones',
                    'partes' => 'Empresa Demo',
                    'inicio' => date('Y-m-d', strtotime('-30 days')),
                    'termino' => date('Y-m-d', strtotime('+21 days')),
                    'autorizantes' => 'Jefe Norte',
                ],
                'titulo' => 'Manual de operaciones — nivel departamento',
                'filename' => 'manual-operaciones.txt',
                'body' => "Documento a nivel departamento Operaciones.\n",
                'authorize' => [],
                'oficial' => false,
            ],
            [
                'id' => $docLiqNorte,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsNorte,
                'sucursal_id' => $sucNorte,
                'tipo_id' => $tipoIds['APROBADO'],
                'estado' => 'EN_ELABORACION',
                'version_actual' => 1,
                'vence_at' => null,
                'alerta_dias' => 15,
                'fecha_termino' => null,
                'cadena_id' => $docLiqNorte,
                'renueva_de_id' => null,
                'cabecera' => [
                    'asunto' => 'Liquidaciones marzo',
                    'partes' => 'Sucursal Norte / Contador',
                    'inicio' => date('Y-m-01'),
                    'termino' => date('Y-m-t'),
                    'autorizantes' => 'Master',
                ],
                'titulo' => 'Liquidaciones Norte — marzo',
                'filename' => 'liquidaciones-norte-marzo.txt',
                'body' => "Liquidaciones Sucursal Norte — ejemplo seed.\nAutorizado a Contador.\n",
                'authorize' => [$users['contador']['id'], $users['multi']['id']],
                'oficial' => true,
            ],
            // Hilo renovación: id1 RENOVADO (venció) → id2 VIGENTE
            [
                'id' => $docContratoPrev,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsSur,
                'sucursal_id' => $sucSur,
                'tipo_id' => $tipoIds['VIGENTE'],
                'estado' => 'RENOVADO',
                'version_actual' => 1,
                'vence_at' => $vencePasado,
                'alerta_dias' => 30,
                'fecha_termino' => $vencePasado,
                'cadena_id' => $docContratoPrev,
                'renueva_de_id' => null,
                'cabecera' => [
                    'asunto' => 'Contrato proveedor Sur 2025-2026',
                    'partes' => 'Empresa Demo / Proveedor Sur SpA',
                    'inicio' => date('Y-m-d', strtotime('-370 days')),
                    'termino' => $vencePasado,
                    'autorizantes' => 'Abogado; Master',
                ],
                'titulo' => 'Contrato proveedor Sur',
                'filename' => 'contrato-sur-periodo-anterior.txt',
                'body' => "Contrato Sucursal Sur — período anterior (renovado).\n",
                'authorize' => [$users['abogado']['id']],
                'oficial' => true,
                'created_at' => date('Y-m-d H:i:s', strtotime('-400 days')),
            ],
            [
                'id' => $docContratoSur,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsSur,
                'sucursal_id' => $sucSur,
                'tipo_id' => $tipoIds['VIGENTE'],
                'estado' => 'VIGENTE',
                'version_actual' => 1,
                'vence_at' => $venceFuturo,
                'alerta_dias' => 30,
                'fecha_termino' => $venceFuturo,
                'cadena_id' => $docContratoPrev,
                'renueva_de_id' => $docContratoPrev,
                'cabecera' => [
                    'asunto' => 'Contrato proveedor Sur 2026-2027',
                    'partes' => 'Empresa Demo / Proveedor Sur SpA',
                    'inicio' => $vencePasado,
                    'termino' => $venceFuturo,
                    'autorizantes' => 'Abogado; Master',
                ],
                'titulo' => 'Contrato proveedor Sur',
                'filename' => 'contrato-sur-renovado.txt',
                'body' => "Contrato Sucursal Sur — renovación vigente.\nAutorizado a Abogado.\n",
                'authorize' => [$users['abogado']['id']],
                'oficial' => true,
                'prev_version' => null,
            ],
            [
                'id' => $docPolitica,
                'departamento_id' => $deptoAdmin,
                'seccion_id' => $secAdminCorp,
                'sucursal_id' => null,
                'tipo_id' => $tipoIds['VIGENTE'],
                'estado' => 'VIGENTE',
                'version_actual' => 1,
                'vence_at' => $vencePronto,
                'alerta_dias' => 30,
                'fecha_termino' => $vencePronto,
                'cadena_id' => $docPolitica,
                'renueva_de_id' => null,
                'cabecera' => [
                    'asunto' => 'Política de contratos',
                    'partes' => 'Administración',
                    'inicio' => date('Y-m-d', strtotime('-60 days')),
                    'termino' => $vencePronto,
                    'autorizantes' => 'Master',
                ],
                'titulo' => 'Política de contratos — Administración',
                'filename' => 'politica-contratos.txt',
                'body' => "Documento a nivel departamento Administración.\n",
                'authorize' => [],
                'oficial' => true,
            ],
            [
                'id' => $docMinuta,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsGeneral,
                'sucursal_id' => null,
                'tipo_id' => $tipoIds['BORRADOR'],
                'estado' => 'EN_ELABORACION',
                'version_actual' => 2,
                'vence_at' => null,
                'alerta_dias' => null,
                'fecha_termino' => date('Y-m-d', strtotime('+14 days')),
                'cadena_id' => $docMinuta,
                'renueva_de_id' => null,
                'cabecera' => [
                    'asunto' => 'Minuta reunión ops',
                    'partes' => 'Operaciones',
                    'inicio' => date('Y-m-d'),
                    'termino' => date('Y-m-d', strtotime('+14 days')),
                    'autorizantes' => 'Jefe Norte',
                ],
                'titulo' => 'Minuta reunión operaciones — borrador',
                'filename' => 'minuta-ops-v2.txt',
                'body' => "Minuta v2 — borrador con versionamiento + control de cambios.\n",
                'authorize' => [],
                'oficial' => false,
                'prev_version' => [
                    'filename' => 'minuta-ops-v1.txt',
                    'body' => "Minuta v1.\n",
                    'notas' => 'Borrador inicial',
                ],
            ],
        ];

        foreach ($samples as $d) {
            $saved = DocumentStorage::save($empresaDemo, $d['id'], $d['filename'], $d['body']);
            $cab = $d['cabecera'] ?? [];
            $this->db->table('documentos')->insert([
                'id'                    => $d['id'],
                'empresa_id'            => $empresaDemo,
                'departamento_id'       => $d['departamento_id'],
                'seccion_id'            => $d['seccion_id'],
                'sucursal_id'           => $d['sucursal_id'],
                'tipo_id'               => $d['tipo_id'],
                'estado'                => $d['estado'],
                'version_actual'        => $d['version_actual'],
                'vence_at'              => $d['vence_at'],
                'alerta_dias'           => $d['alerta_dias'],
                'fecha_termino'         => $d['fecha_termino'],
                'renueva_de_id'         => $d['renueva_de_id'],
                'cadena_id'             => $d['cadena_id'],
                'cabecera_partes'       => $cab['partes'] ?? null,
                'cabecera_asunto'       => $cab['asunto'] ?? null,
                'cabecera_inicio'       => $cab['inicio'] ?? null,
                'cabecera_termino'      => $cab['termino'] ?? null,
                'cabecera_autorizantes' => $cab['autorizantes'] ?? null,
                'titulo'                => $d['titulo'],
                'nombre_archivo'        => $d['filename'],
                'ruta_relativa'         => $saved['ruta_relativa'],
                'mime'                  => 'text/plain',
                'tamano_bytes'          => $saved['tamano_bytes'],
                'subido_por_id'         => $users['admin']['id'],
                'created_at'            => $d['created_at'] ?? $now,
                'updated_at'            => $now,
            ]);

            foreach ($d['authorize'] as $uid) {
                $this->db->table('documento_autorizaciones')->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $empresaDemo,
                    'documento_id' => $d['id'],
                    'usuario_id'   => $uid,
                    'created_at'   => $now,
                ]);
            }

            // Versiones para oficial y borrador
            if (! empty($d['prev_version'])) {
                $v1rel = $empresaDemo . '/' . $d['id'] . '/v1/' . $d['prev_version']['filename'];
                $v1full = DocumentStorage::absolutePath($v1rel);
                file_put_contents($v1full, $d['prev_version']['body']);
                $this->db->table('documento_versiones')->insert([
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                    'version_num' => 1, 'nombre_archivo' => $d['prev_version']['filename'],
                    'ruta_relativa' => $v1rel, 'mime' => 'text/plain',
                    'tamano_bytes' => strlen($d['prev_version']['body']),
                    'notas' => $d['prev_version']['notas'], 'creado_por_id' => $users['admin']['id'],
                    'created_at' => $now,
                ]);
                $this->db->table('documento_versiones')->insert([
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                    'version_num' => (int) $d['version_actual'], 'nombre_archivo' => $d['filename'],
                    'ruta_relativa' => $saved['ruta_relativa'], 'mime' => 'text/plain',
                    'tamano_bytes' => $saved['tamano_bytes'],
                    'notas' => 'Versión actual (seed)', 'creado_por_id' => $users['admin']['id'],
                    'created_at' => $now,
                ]);
            } else {
                $this->db->table('documento_versiones')->insert([
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                    'version_num' => 1, 'nombre_archivo' => $d['filename'],
                    'ruta_relativa' => $saved['ruta_relativa'], 'mime' => 'text/plain',
                    'tamano_bytes' => $saved['tamano_bytes'],
                    'notas' => 'Versión inicial', 'creado_por_id' => $users['admin']['id'],
                    'created_at' => $now,
                ]);
            }

            if (! empty($d['oficial'])) {
                $this->db->table('documento_estado_historial')->insert([
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                    'estado_desde' => null, 'estado_hasta' => 'EN_ELABORACION',
                    'actor_id' => $users['admin']['id'], 'nota' => 'Alta (seed)', 'created_at' => $now,
                ]);
                if ($d['estado'] !== 'EN_ELABORACION') {
                    $this->db->table('documento_estado_historial')->insert([
                        'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                        'estado_desde' => 'EN_ELABORACION', 'estado_hasta' => $d['estado'],
                        'actor_id' => $users['admin']['id'], 'nota' => 'Seed demo', 'created_at' => $now,
                    ]);
                }
            } else {
                $this->db->table('documento_cambios')->insertBatch([
                    [
                        'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                        'actor_id' => $users['admin']['id'], 'resumen' => 'Documento creado',
                        'detalle' => 'Archivo inicial', 'created_at' => $now,
                    ],
                    [
                        'id' => new_id(), 'empresa_id' => $empresaDemo, 'documento_id' => $d['id'],
                        'actor_id' => $users['jefeNorte']['id'], 'resumen' => 'Nueva versión / ajuste',
                        'detalle' => 'Control de cambios + versionado en borrador.', 'created_at' => $now,
                    ],
                ]);
            }
        }

        // --- Fase 4 demos ---
        if ($this->db->tableExists('fiscalizacion_solicitudes')) {
            $fisPendiente = new_id();
            $fisAprobada  = new_id();

            $this->db->table('fiscalizacion_solicitudes')->insert([
                'id' => $fisPendiente, 'empresa_id' => $empresaDemo,
                'departamento_id' => $deptoOps, 'sucursal_id' => $sucNorte,
                'tipo' => 'LIQUIDACION',
                'titulo' => 'Envío liquidaciones Norte — marzo (fiscalización)',
                'motivo' => 'Requiere OK Master antes de remitir a fiscalización / contador.',
                'estado' => 'PENDIENTE',
                'solicitado_por_id' => $users['jefeNorte']['id'],
                'revisado_por_id' => null, 'revisado_at' => null, 'comentario_revision' => null,
                'enviado_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->table('fiscalizacion_solicitudes')->insert([
                'id' => $fisAprobada, 'empresa_id' => $empresaDemo,
                'departamento_id' => $deptoOps, 'sucursal_id' => $sucSur,
                'tipo' => 'CONTRATO',
                'titulo' => 'Exportación contrato Sur — aprobada',
                'motivo' => 'Demo de solicitud ya autorizada por Master.',
                'estado' => 'APROBADA',
                'solicitado_por_id' => $users['jefeSur']['id'],
                'revisado_por_id' => $users['admin']['id'],
                'revisado_at' => $now,
                'comentario_revision' => 'OK para exportar.',
                'enviado_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $this->db->table('fiscalizacion_documentos')->insertBatch([
                ['id' => new_id(), 'solicitud_id' => $fisPendiente, 'documento_id' => $docLiqNorte],
                ['id' => new_id(), 'solicitud_id' => $fisAprobada, 'documento_id' => $docContratoSur],
            ]);

            $this->db->table('fiscalizacion_auditoria')->insertBatch([
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'solicitud_id' => $fisPendiente,
                    'actor_id' => $users['jefeNorte']['id'], 'accion' => 'CREATED',
                    'detalle' => 'LIQUIDACION · seed', 'created_at' => $now,
                ],
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'solicitud_id' => $fisPendiente,
                    'actor_id' => $users['jefeNorte']['id'], 'accion' => 'SUBMITTED',
                    'detalle' => null, 'created_at' => $now,
                ],
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'solicitud_id' => $fisAprobada,
                    'actor_id' => $users['jefeSur']['id'], 'accion' => 'CREATED',
                    'detalle' => 'CONTRATO · seed', 'created_at' => $now,
                ],
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'solicitud_id' => $fisAprobada,
                    'actor_id' => $users['admin']['id'], 'accion' => 'APPROVED',
                    'detalle' => 'OK para exportar.', 'created_at' => $now,
                ],
            ]);

            $paqContador = new_id();
            $paqAbogado  = new_id();
            $this->db->table('canal_paquetes')->insertBatch([
                [
                    'id' => $paqContador, 'empresa_id' => $empresaDemo,
                    'titulo' => 'Paquete liquidaciones — Contador',
                    'descripcion' => 'Acceso acotado a liquidaciones Norte.',
                    'destinatario_id' => $users['contador']['id'],
                    'creado_por_id' => $users['admin']['id'],
                    'estado' => 'ACTIVO', 'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'id' => $paqAbogado, 'empresa_id' => $empresaDemo,
                    'titulo' => 'Paquete contratos — Abogado',
                    'descripcion' => 'Contrato Sur para revisión legal.',
                    'destinatario_id' => $users['abogado']['id'],
                    'creado_por_id' => $users['admin']['id'],
                    'estado' => 'ACTIVO', 'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
            $this->db->table('canal_paquete_documentos')->insertBatch([
                ['id' => new_id(), 'paquete_id' => $paqContador, 'documento_id' => $docLiqNorte],
                ['id' => new_id(), 'paquete_id' => $paqAbogado, 'documento_id' => $docContratoSur],
            ]);
            $this->db->table('canal_paquete_auditoria')->insertBatch([
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'paquete_id' => $paqContador,
                    'actor_id' => $users['admin']['id'], 'accion' => 'CREATED',
                    'detalle' => 'Paquete liquidaciones — Contador', 'created_at' => $now,
                ],
                [
                    'id' => new_id(), 'empresa_id' => $empresaDemo, 'paquete_id' => $paqAbogado,
                    'actor_id' => $users['admin']['id'], 'accion' => 'CREATED',
                    'detalle' => 'Paquete contratos — Abogado', 'created_at' => $now,
                ],
            ]);
        }
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->rmTree($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
