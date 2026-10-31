<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\DocumentStorage;
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
            'canal_paquete_auditoria', 'canal_paquete_documentos', 'canal_paquetes',
            'fiscalizacion_auditoria', 'fiscalizacion_documentos', 'fiscalizacion_solicitudes',
            'documento_autorizaciones', 'documentos', 'documento_secciones',
            'membresias', 'temas_interes', 'actividades', 'sucursales',
            'departamentos', 'usuarios', 'empresas',
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

        $empresaDemo = new_id();
        $empresaBeta = new_id();

        $this->db->table('empresas')->insertBatch([
            [
                'id' => $empresaDemo, 'nombre' => 'Empresa Demo', 'slug' => 'empresa-demo',
                'rut' => '76.123.456-7', 'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $empresaBeta, 'nombre' => 'Empresa Beta', 'slug' => 'empresa-beta',
                'rut' => '77.987.654-3', 'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
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
                'nombre' => 'Sucursal Norte', 'codigo' => 'NTE', 'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $sucSur, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
                'nombre' => 'Sucursal Sur', 'codigo' => 'SUR', 'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => $sucBeta, 'empresa_id' => $empresaBeta, 'departamento_id' => $deptoBeta,
                'nombre' => 'Casa Matriz', 'codigo' => 'CM', 'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
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
        foreach ($users as $u) {
            $userRows[] = [
                'id' => $u['id'], 'email' => $u['email'], 'nombre' => $u['nombre'],
                'rut' => null, 'password_hash' => $hash, 'activo' => 1,
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

        $docManual = new_id();
        $docLiqNorte = new_id();
        $docContratoSur = new_id();
        $docPolitica = new_id();

        $samples = [
            [
                'id' => $docManual,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsGeneral,
                'sucursal_id' => null,
                'titulo' => 'Manual de operaciones — nivel departamento',
                'filename' => 'manual-operaciones.txt',
                'body' => "Documento a nivel departamento Operaciones.\nVisible a Master y a Jefes de sucursales de Operaciones.\n",
                'authorize' => [],
            ],
            [
                'id' => $docLiqNorte,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsNorte,
                'sucursal_id' => $sucNorte,
                'titulo' => 'Liquidaciones Norte — marzo',
                'filename' => 'liquidaciones-norte-marzo.txt',
                'body' => "Liquidaciones Sucursal Norte — ejemplo seed.\nAutorizado a Contador.\n",
                'authorize' => [$users['contador']['id'], $users['multi']['id']],
            ],
            [
                'id' => $docContratoSur,
                'departamento_id' => $deptoOps,
                'seccion_id' => $secOpsSur,
                'sucursal_id' => $sucSur,
                'titulo' => 'Contrato proveedor Sur',
                'filename' => 'contrato-sur.txt',
                'body' => "Contrato Sucursal Sur — ejemplo seed.\nAutorizado a Abogado.\n",
                'authorize' => [$users['abogado']['id']],
            ],
            [
                'id' => $docPolitica,
                'departamento_id' => $deptoAdmin,
                'seccion_id' => $secAdminCorp,
                'sucursal_id' => null,
                'titulo' => 'Política de contratos — Administración',
                'filename' => 'politica-contratos.txt',
                'body' => "Documento a nivel departamento Administración.\nVisible a Master (Jefes de Ops no lo ven: otro depto).\n",
                'authorize' => [],
            ],
        ];

        foreach ($samples as $d) {
            $saved = DocumentStorage::save($empresaDemo, $d['id'], $d['filename'], $d['body']);
            $this->db->table('documentos')->insert([
                'id'              => $d['id'],
                'empresa_id'      => $empresaDemo,
                'departamento_id' => $d['departamento_id'],
                'seccion_id'      => $d['seccion_id'],
                'sucursal_id'     => $d['sucursal_id'],
                'titulo'          => $d['titulo'],
                'nombre_archivo'  => $d['filename'],
                'ruta_relativa'   => $saved['ruta_relativa'],
                'mime'            => 'text/plain',
                'tamano_bytes'    => $saved['tamano_bytes'],
                'subido_por_id'   => $users['admin']['id'],
                'created_at'      => $now,
                'updated_at'      => $now,
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
