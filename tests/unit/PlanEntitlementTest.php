<?php

declare(strict_types=1);

use App\Libraries\DocumentStorage;
use App\Libraries\PlanEntitlement;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PlanEntitlementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testTipoBFreeBlocksFourthUser(): void
    {
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $planId = new_id();
        $db->table('planes')->insert([
            'id' => $planId, 'codigo' => 'FREE', 'nombre' => 'Free',
            'tipo_aplicable' => 'b', 'max_usuarios' => 3,
            'max_storage_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
            'es_gratuito' => 1, 'precio_mensual' => 0, 'activo' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $empresaId = new_id();
        $db->table('empresas')->insert([
            'id' => $empresaId, 'nombre' => 'Filial Free', 'slug' => 'filial-free-' . substr($empresaId, -6),
            'activa' => 1, 'tipo' => 'b', 'plan_id' => $planId,
            'subscription_status' => 'free',
            'seats_limit' => 3, 'storage_limit_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $uid = new_id();
            $db->table('usuarios')->insert([
                'id' => $uid, 'email' => "u{$i}@test.local", 'nombre' => "U{$i}",
                'password_hash' => 'x', 'activo' => 1, 'es_admin_central' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $db->table('membresias')->insert([
                'id' => new_id(), 'empresa_id' => $empresaId, 'usuario_id' => $uid,
                'rol' => 'CONTADOR', 'sucursal_id' => null, 'activa' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $ent = new PlanEntitlement($db);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Límite de usuarios/');
        $ent->assertCanAddUser($empresaId);
    }

    public function testTipoARequiresPaidPlan(): void
    {
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $empresaId = new_id();
        $db->table('empresas')->insert([
            'id' => $empresaId, 'nombre' => 'Multi Co', 'slug' => 'multi-' . substr($empresaId, -6),
            'activa' => 1, 'tipo' => 'a', 'plan_id' => null,
            'subscription_status' => PlanEntitlement::STATUS_REQUIRES_PLAN,
            'seats_limit' => 0, 'storage_limit_bytes' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $ent = new PlanEntitlement($db);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/multiempresa|Plan de pago/i');
        $ent->assertCanUpload($empresaId, 100);
    }

    public function testAssignPaidPlanActivatesTipoA(): void
    {
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $planId = new_id();
        $db->table('planes')->insert([
            'id' => $planId, 'codigo' => 'STARTER', 'nombre' => 'Starter',
            'tipo_aplicable' => 'ambos', 'max_usuarios' => 10,
            'max_storage_bytes' => 50 * 1024 * 1024 * 1024,
            'es_gratuito' => 0, 'precio_mensual' => 29.9, 'activo' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $empresaId = new_id();
        $db->table('empresas')->insert([
            'id' => $empresaId, 'nombre' => 'Multi Paid', 'slug' => 'multi-paid-' . substr($empresaId, -6),
            'activa' => 1, 'tipo' => 'a', 'plan_id' => null,
            'subscription_status' => PlanEntitlement::STATUS_REQUIRES_PLAN,
            'seats_limit' => 0, 'storage_limit_bytes' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $ent = new PlanEntitlement($db);
        $updated = $ent->assignPlan($empresaId, $planId);

        $this->assertSame(PlanEntitlement::STATUS_ACTIVE, $updated['subscription_status']);
        $this->assertSame($planId, $updated['plan_id']);
        $this->assertSame(10, (int) $updated['seats_limit']);

        $ent->assertCanAddUser($empresaId);
        $ent->assertCanUpload($empresaId, 1024);
        $this->assertTrue(true);
    }

    public function testRejectsFreePlanForTipoA(): void
    {
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $planId = new_id();
        $db->table('planes')->insert([
            'id' => $planId, 'codigo' => 'FREE', 'nombre' => 'Free',
            'tipo_aplicable' => 'b', 'max_usuarios' => 3,
            'max_storage_bytes' => PlanEntitlement::FREE_MAX_STORAGE,
            'es_gratuito' => 1, 'precio_mensual' => 0, 'activo' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $empresaId = new_id();
        $db->table('empresas')->insert([
            'id' => $empresaId, 'nombre' => 'Multi NoFree', 'slug' => 'multi-nofree-' . substr($empresaId, -6),
            'activa' => 1, 'tipo' => 'a', 'plan_id' => null,
            'subscription_status' => PlanEntitlement::STATUS_REQUIRES_PLAN,
            'seats_limit' => 0, 'storage_limit_bytes' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->expectException(\RuntimeException::class);
        (new PlanEntitlement($db))->assignPlan($empresaId, $planId);
    }

    public function testUploadUsesMaxOfDiskAndDbSum(): void
    {
        helper('id');
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $tmpRoot = sys_get_temp_dir() . '/repositoria-plan-disk-' . bin2hex(random_bytes(4));
        mkdir($tmpRoot, 0750, true);
        putenv('STORAGE_ROOT=' . $tmpRoot);
        $_ENV['STORAGE_ROOT']    = $tmpRoot;
        $_SERVER['STORAGE_ROOT'] = $tmpRoot;

        try {
            $planId = new_id();
            $db->table('planes')->insert([
                'id' => $planId, 'codigo' => 'TINY', 'nombre' => 'Tiny',
                'tipo_aplicable' => 'ambos', 'max_usuarios' => 10,
                'max_storage_bytes' => 200,
                'es_gratuito' => 0, 'precio_mensual' => 1, 'activo' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $empresaId = new_id();
            $db->table('empresas')->insert([
                'id' => $empresaId, 'nombre' => 'Disk Heavy', 'slug' => 'disk-' . substr($empresaId, -6),
                'activa' => 1, 'tipo' => 'a', 'plan_id' => $planId,
                'subscription_status' => PlanEntitlement::STATUS_ACTIVE,
                'seats_limit' => 10, 'storage_limit_bytes' => 200,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            // Disco real grande; SUM BD = 0 → facturable = disco
            DocumentStorage::save($empresaId, 'orphan', 'blob.bin', str_repeat('x', 180));

            $ent  = new PlanEntitlement($db);
            $snap = $ent->snapshot($empresaId);
            $this->assertGreaterThanOrEqual(180, $snap['disk_used_bytes']);
            $this->assertSame(0, $snap['db_storage_bytes']);
            $this->assertSame($snap['disk_used_bytes'], $snap['storage_used_bytes']);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/almacenamiento|disco/i');
            $ent->assertCanUpload($empresaId, 50);
        } finally {
            $this->rmTree($tmpRoot);
            putenv('STORAGE_ROOT');
            unset($_ENV['STORAGE_ROOT'], $_SERVER['STORAGE_ROOT']);
        }
    }

    private function rmTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
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
        @rmdir($dir);
    }
}
