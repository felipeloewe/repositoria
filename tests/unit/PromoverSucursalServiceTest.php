<?php

declare(strict_types=1);

use App\Libraries\DocumentStorage;
use App\Libraries\PromoverSucursalService;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration path for promotion (b)→(a) against SQLite test DB.
 *
 * @internal
 */
final class PromoverSucursalServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        helper('id');

        $this->tmpRoot = sys_get_temp_dir() . '/repositoria-promo-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0750, true);
        putenv('STORAGE_ROOT=' . $this->tmpRoot);
        $_ENV['STORAGE_ROOT']    = $this->tmpRoot;
        $_SERVER['STORAGE_ROOT'] = $this->tmpRoot;
    }

    protected function tearDown(): void
    {
        $this->rmTree($this->tmpRoot);
        putenv('STORAGE_ROOT');
        unset($_ENV['STORAGE_ROOT'], $_SERVER['STORAGE_ROOT']);
        parent::tearDown();
    }

    public function testPromueveSucursalRemapeaDocsYArchivaOrigen(): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $empresaId = new_id();
        $deptoId   = new_id();
        $sucId     = new_id();
        $userId    = new_id();
        $docId     = new_id();
        $secId     = new_id();
        $membId    = new_id();

        $db->table('empresas')->insert([
            'id' => $empresaId, 'nombre' => 'Parent Co', 'slug' => 'parent-co',
            'activa' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('departamentos')->insert([
            'id' => $deptoId, 'empresa_id' => $empresaId, 'nombre' => 'Ops',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('sucursales')->insert([
            'id' => $sucId, 'empresa_id' => $empresaId, 'departamento_id' => $deptoId,
            'nombre' => 'Filial Sur', 'codigo' => 'SUR', 'activa' => 1, 'estado' => 'ACTIVA',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('usuarios')->insert([
            'id' => $userId, 'email' => 'jefe@test.local', 'nombre' => 'Jefe',
            'password_hash' => password_hash('x', PASSWORD_DEFAULT), 'activo' => 1,
            'es_admin_central' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('membresias')->insert([
            'id' => $membId, 'empresa_id' => $empresaId, 'usuario_id' => $userId,
            'rol' => Roles::JEFE_SUCURSAL, 'sucursal_id' => $sucId, 'activa' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $db->table('documento_secciones')->insert([
            'id' => $secId, 'empresa_id' => $empresaId, 'departamento_id' => $deptoId,
            'sucursal_id' => $sucId, 'nombre' => 'Contratos', 'codigo' => 'CTR',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $saved = DocumentStorage::save($empresaId, $docId, 'contrato.txt', "contenido-filial\n");
        $db->table('documentos')->insert([
            'id' => $docId, 'empresa_id' => $empresaId, 'departamento_id' => $deptoId,
            'sucursal_id' => $sucId, 'seccion_id' => $secId,
            'titulo' => 'Contrato Sur', 'nombre_archivo' => 'contrato.txt',
            'ruta_relativa' => $saved['ruta_relativa'], 'mime' => 'text/plain',
            'tamano_bytes' => $saved['tamano_bytes'], 'subido_por_id' => $userId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $result = (new PromoverSucursalService($db))->promover($sucId, [
            'actor_id' => $userId,
            'nombre'   => 'Empresa Filial Sur',
            'slug'     => 'empresa-filial-sur',
        ]);

        $this->assertNotSame($empresaId, $result['nueva_empresa_id']);
        $this->assertSame(1, $result['documentos_movidos']);
        $this->assertSame(1, $result['secciones_movidas']);
        $this->assertGreaterThanOrEqual(1, $result['membresias_creadas']);

        $origen = $db->table('sucursales')->where('id', $sucId)->get()->getRowArray();
        $this->assertSame(PromoverSucursalService::ESTADO_PROMOVIDA, $origen['estado']);
        $this->assertSame(0, (int) $origen['activa']);
        $this->assertSame($result['nueva_empresa_id'], $origen['promoted_to_empresa_id']);

        $doc = $db->table('documentos')->where('id', $docId)->get()->getRowArray();
        $this->assertSame($result['nueva_empresa_id'], $doc['empresa_id']);
        $this->assertStringStartsWith($result['nueva_empresa_id'] . '/', (string) $doc['ruta_relativa']);
        $this->assertFileExists($this->tmpRoot . '/' . $doc['ruta_relativa']);
        $this->assertSame("contenido-filial\n", DocumentStorage::read((string) $doc['ruta_relativa']));

        $newMemb = $db->table('membresias')
            ->where('usuario_id', $userId)
            ->where('empresa_id', $result['nueva_empresa_id'])
            ->where('activa', 1)
            ->get()
            ->getRowArray();
        $this->assertNotNull($newMemb);
        $this->assertSame(Roles::ADMINISTRADOR_MASTER, $newMemb['rol']);

        $audit = $db->table('promocion_sucursal_auditoria')->where('id', $result['audit_id'])->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame($sucId, $audit['origen_sucursal_id']);
        $this->assertSame($result['nueva_empresa_id'], $audit['nueva_empresa_id']);
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
