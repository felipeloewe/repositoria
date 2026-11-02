<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Ensures AddPromocionSucursalEmpresa can run twice without Duplicate column.
 *
 * @internal
 */
final class PromocionMigrationIdempotentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testPromocionMigrationUpIsIdempotent(): void
    {
        $migration = new \App\Database\Migrations\AddPromocionSucursalEmpresa(
            \Config\Database::forge()
        );

        // Schema already applied by DatabaseTestTrait; second up() must no-op.
        $migration->up();
        $migration->up();

        $db = db_connect();
        $this->assertTrue($db->fieldExists('promoted_from_sucursal_id', 'empresas'));
        $this->assertTrue($db->fieldExists('origen_empresa_id', 'empresas'));
        $this->assertTrue($db->fieldExists('estado', 'sucursales'));
        $this->assertTrue($db->fieldExists('promoted_to_empresa_id', 'sucursales'));
        $this->assertTrue($db->fieldExists('promoted_at', 'sucursales'));
        $this->assertTrue($db->fieldExists('es_admin_central', 'usuarios'));
        $this->assertTrue($db->tableExists('promocion_sucursal_auditoria'));
    }
}
