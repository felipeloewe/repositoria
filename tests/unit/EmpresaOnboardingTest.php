<?php

declare(strict_types=1);

use App\Libraries\EmpresaOnboarding;
use App\Libraries\PlanEntitlement;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class EmpresaOnboardingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private function seedFreePlan(): string
    {
        helper('id');
        $now    = date('Y-m-d H:i:s');
        $planId = new_id();
        db_connect()->table('planes')->insert([
            'id' => $planId, 'codigo' => PlanEntitlement::CODIGO_FREE, 'nombre' => 'Free',
            'tipo_aplicable' => 'b', 'max_usuarios' => PlanEntitlement::FREE_MAX_USERS,
            'max_storage_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
            'es_gratuito' => 1, 'precio_mensual' => 0, 'activo' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $planId;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function baseInput(array $extra = []): array
    {
        return array_merge([
            'nombre'         => 'Nueva Filial SpA',
            'alias'          => 'nueva-filial',
            'tipo'           => 'b',
            'admin_nombre'   => 'Ana Admin',
            'admin_alias'    => 'ana.admin',
            'admin_cargo'    => 'Gerente',
            'admin_rut'      => '11.111.111-1',
            'admin_email'    => 'ana@nueva.local',
            'admin_password' => 'secret12',
        ], $extra);
    }

    public function testSelfServeCreatesTipoBFreeWithMaster(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();

        $result = $svc->createEmpresaWithAdmin($this->baseInput());

        $this->assertNotEmpty($result['empresa_id']);
        $this->assertSame('b', $result['tipo']);
        $this->assertSame('FREE', $result['plan_codigo']);

        $db      = db_connect();
        $empresa = $db->table('empresas')->where('id', $result['empresa_id'])->get()->getRowArray();
        $this->assertSame('free', $empresa['subscription_status']);
        $this->assertSame(3, (int) $empresa['seats_limit']);

        $user = $db->table('usuarios')->where('id', $result['usuario_id'])->get()->getRowArray();
        $this->assertSame('ana.admin', $user['alias']);
        $this->assertSame('Gerente', $user['cargo']);
        $this->assertSame('11.111.111-1', $user['rut']);

        $memb = $db->table('membresias')->where('id', $result['membresia_id'])->get()->getRowArray();
        $this->assertSame(Roles::ADMINISTRADOR_MASTER, $memb['rol']);
        $this->assertSame(1, (int) $memb['activa']);

        $ent = new PlanEntitlement($db);
        $ent->assertCanAddUser($result['empresa_id']); // 1 of 3 used
    }

    public function testTipoAWithoutPlanRequiresPlan(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();

        $result = $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'       => 'Multi Corp',
            'alias'        => 'multi-corp',
            'tipo'         => 'a',
            'admin_nombre' => 'Boss',
            'admin_alias'  => 'boss.multi',
            'admin_email'  => 'boss@multi.local',
            'admin_cargo'  => null,
            'admin_rut'    => null,
        ]));

        $this->assertSame('a', $result['tipo']);
        $this->assertNull($result['plan_codigo']);

        $empresa = db_connect()->table('empresas')->where('id', $result['empresa_id'])->get()->getRowArray();
        $this->assertSame(PlanEntitlement::STATUS_REQUIRES_PLAN, $empresa['subscription_status']);

        $this->expectException(RuntimeException::class);
        (new PlanEntitlement())->assertCanAddUser($result['empresa_id']);
    }

    public function testRejectsDuplicateAdminEmail(): void
    {
        $this->seedFreePlan();
        helper('id');
        $now = date('Y-m-d H:i:s');
        db_connect()->table('usuarios')->insert([
            'id' => new_id(), 'email' => 'taken@test.local', 'nombre' => 'Taken',
            'password_hash' => 'x', 'activo' => 1, 'es_admin_central' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ya está registrado/');
        (new EmpresaOnboarding())->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Dup Co',
            'alias'       => 'dup-co',
            'admin_alias' => 'dup.admin',
            'admin_email' => 'taken@test.local',
        ]));
    }

    public function testRejectsMissingEmpresaAlias(): void
    {
        $this->seedFreePlan();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Alias de la empresa es obligatorio/');
        (new EmpresaOnboarding())->createEmpresaWithAdmin($this->baseInput([
            'nombre' => 'Sin Alias',
            'alias'  => '',
        ]));
    }

    public function testRejectsDuplicateEmpresaAlias(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();
        $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Primera',
            'alias'       => 'mi-alias',
            'admin_alias' => 'a.primera',
            'admin_email' => 'a@primera.local',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Alias de empresa ya está en uso/');
        $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Segunda',
            'alias'       => 'mi-alias',
            'admin_alias' => 'b.segunda',
            'admin_email' => 'b@segunda.local',
        ]));
    }

    public function testRejectsDuplicateUserAlias(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();
        $svc->createEmpresaWithAdmin($this->baseInput([
            'alias'       => 'emp-uno',
            'admin_alias' => 'mismo.alias',
            'admin_email' => 'uno@test.local',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Alias de usuario ya está en uso/');
        $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Otra',
            'alias'       => 'emp-dos',
            'admin_alias' => 'mismo.alias',
            'admin_email' => 'dos@test.local',
        ]));
    }

    public function testRejectsMissingUserAlias(): void
    {
        $this->seedFreePlan();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Alias del administrador es obligatorio/');
        (new EmpresaOnboarding())->createEmpresaWithAdmin($this->baseInput([
            'admin_alias' => '',
        ]));
    }
}
