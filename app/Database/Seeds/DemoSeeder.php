<?php

declare(strict_types=1);

namespace App\Database\Seeds;

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
            // multi: Contador in Demo + Master in Beta
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

        // Documento sección stub (Fase 2 schema smoke)
        $secId = new_id();
        $this->db->table('documento_secciones')->insert([
            'id' => $secId, 'empresa_id' => $empresaDemo, 'departamento_id' => $deptoOps,
            'sucursal_id' => null, 'nombre' => 'General', 'codigo' => 'GENERAL',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
