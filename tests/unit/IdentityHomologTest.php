<?php

declare(strict_types=1);

use App\Libraries\EmpresaOnboarding;
use App\Libraries\IdentityHomolog;
use App\Libraries\PatrimonioRelink;
use App\Libraries\PlanEntitlement;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class IdentityHomologTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private function seedFreePlan(): void
    {
        helper('id');
        $now = date('Y-m-d H:i:s');
        db_connect()->table('planes')->insert([
            'id' => new_id(), 'codigo' => PlanEntitlement::CODIGO_FREE, 'nombre' => 'Free',
            'tipo_aplicable' => 'b', 'max_usuarios' => PlanEntitlement::FREE_MAX_USERS,
            'max_storage_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
            'es_gratuito' => 1, 'precio_mensual' => 0, 'activo' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function testAdminDeclaranteRequiresRut(): void
    {
        $this->seedFreePlan();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/RUT es obligatorio/');
        (new EmpresaOnboarding())->createEmpresaWithAdmin([
            'nombre'              => 'Rut Co',
            'alias'               => 'rut-co',
            'tipo'                => 'b',
            'admin_nombre'        => 'Ana Admin',
            'admin_alias'         => 'ana.rut',
            'admin_email'         => 'ana.rut@test.local',
            'admin_password'      => 'secret12',
            'admin_es_declarante' => true,
            'admin_rut'           => null,
        ]);
    }

    public function testAdminDeclaranteHomologatesRutAndReusesPersona(): void
    {
        $this->seedFreePlan();
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $svc = new EmpresaOnboarding();
        $created = $svc->createEmpresaWithAdmin([
            'nombre'              => 'Homolog Co',
            'alias'               => 'homolog-co',
            'tipo'                => 'b',
            'admin_nombre'        => 'Felipe Videla',
            'admin_alias'         => 'felipe.v',
            'admin_email'         => 'felipe.homolog@test.local',
            'admin_password'      => 'secret12',
            'admin_rut'           => '13.672.926-8',
            'admin_es_declarante' => true,
        ]);

        $empresaId = (string) $created['empresa_id'];
        $usuarioId = (string) $created['usuario_id'];
        $personaId = (string) $created['persona_id'];

        $user    = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
        $persona = $db->table('equity_personas')->where('id', $personaId)->get()->getRowArray();
        $this->assertSame(IdentityHomolog::normalizeRut('13.672.926-8'), IdentityHomolog::normalizeRut($user['rut'] ?? null));
        $this->assertSame(IdentityHomolog::normalizeRut($user['rut'] ?? null), IdentityHomolog::normalizeRut($persona['rut'] ?? null));
        $this->assertSame($usuarioId, (string) $persona['usuario_id']);

        // Patrimonio seed under another empresa_id → align to persona.
        if ($db->tableExists('equity_pasivos')) {
            $pasivoId = new_id();
            $wrongEmp = new_id();
            $db->table('empresas')->insert([
                'id' => $wrongEmp, 'nombre' => 'Wrong', 'slug' => 'wrong-' . substr($wrongEmp, -6),
                'activa' => 1, 'tipo' => 'b', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $db->table('equity_pasivos')->insert([
                'id' => $pasivoId, 'empresa_id' => $wrongEmp, 'persona_id' => $personaId,
                'categoria' => 'hipotecario', 'banco_descripcion' => 'Test',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $upd = (new PatrimonioRelink($db))->alignEmpresaToPersona($personaId);
            $this->assertSame(1, $upd['equity_pasivos'] ?? 0);
            $row = $db->table('equity_pasivos')->where('id', $pasivoId)->get()->getRowArray();
            $this->assertSame($empresaId, (string) $row['empresa_id']);
        }

        // Marcar de nuevo no duplica: reutiliza la misma persona por RUT/usuario.
        $again = $svc->setAdminEsDeclarante($empresaId, $usuarioId, true);
        $this->assertSame($personaId, (string) $again['persona_id']);
        $count = $db->table('equity_personas')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->countAllResults();
        $this->assertSame(1, $count);
        $this->assertSame(Roles::ADMINISTRADOR_MASTER, $db->table('membresias')
            ->where('empresa_id', $empresaId)->where('usuario_id', $usuarioId)
            ->get()->getRowArray()['rol'] ?? null);
    }
}
