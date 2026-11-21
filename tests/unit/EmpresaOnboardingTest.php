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

    public function testInviteAdminMintsTempPasswordAndRequiresChange(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();

        $result = $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'        => 'Invitada SpA',
            'alias'         => 'invitada-spa',
            'admin_alias'   => 'admin.invitada',
            'admin_email'   => 'admin@invitada.local',
            'invite_admin'  => true,
            'equity_matriz' => true,
            // admin_password omitido a propósito
        ]));

        $this->assertNotEmpty($result['admin_temp_password']);
        $this->assertNotEmpty($result['admin_verify_url']);
        $this->assertStringContainsString('verificar-email', (string) $result['admin_verify_url']);

        $user = db_connect()->table('usuarios')->where('id', $result['usuario_id'])->get()->getRowArray();
        $this->assertSame(1, (int) ($user['must_change_password'] ?? 0));
        $this->assertNotEmpty($user['email_verify_token'] ?? null);
        $this->assertEmpty($user['email_verified_at'] ?? null);
        $this->assertTrue(password_verify((string) $result['admin_temp_password'], (string) $user['password_hash']));

        $empresa = db_connect()->table('empresas')->where('id', $result['empresa_id'])->get()->getRowArray();
        if (array_key_exists('equity_es_matriz', $empresa)) {
            $this->assertSame(1, (int) $empresa['equity_es_matriz']);
        }
    }

    public function testUpdateEmpresaKeepsAliasAndRenames(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();
        $created = $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Original SA',
            'alias'       => 'original-sa',
            'admin_alias' => 'admin.original',
            'admin_email' => 'admin@original.local',
        ]));

        $updated = $svc->updateEmpresa($created['empresa_id'], [
            'nombre' => 'Renombrada SA',
            'alias'  => 'original-sa',
            'rut'    => '76.123.456-7',
            'activa' => 1,
        ]);

        $this->assertSame('Renombrada SA', $updated['nombre']);
        $this->assertSame('original-sa', $updated['slug']);
        $this->assertSame('76.123.456-7', $updated['rut']);
    }

    public function testInviteAndReinviteAdminMaster(): void
    {
        $this->seedFreePlan();
        $svc = new EmpresaOnboarding();
        $created = $svc->createEmpresaWithAdmin($this->baseInput([
            'nombre'      => 'Con Admin',
            'alias'       => 'con-admin',
            'admin_alias' => 'primero.admin',
            'admin_email' => 'primero@con.local',
        ]));

        $invite = $svc->inviteAdminMaster($created['empresa_id'], [
            'admin_nombre' => 'Segundo Admin',
            'admin_alias'  => 'segundo.admin',
            'admin_email'  => 'segundo@con.local',
        ]);

        $this->assertTrue($invite['created_user']);
        $this->assertNotEmpty($invite['admin_temp_password']);
        $masters = $svc->listMasterAdmins($created['empresa_id']);
        $this->assertCount(2, $masters);

        $re = $svc->reinviteAdminMaster($created['empresa_id'], $invite['usuario_id']);
        $this->assertSame('segundo@con.local', $re['admin_email']);
        $user = db_connect()->table('usuarios')->where('id', $invite['usuario_id'])->get()->getRowArray();
        $this->assertSame(1, (int) $user['must_change_password']);
        $this->assertTrue(password_verify($re['admin_temp_password'], (string) $user['password_hash']));
    }
}
