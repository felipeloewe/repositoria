<?php

declare(strict_types=1);

use App\Libraries\DocumentStorage;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentStorageRelocateTest extends CIUnitTestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/repositoria-storage-test-' . bin2hex(random_bytes(4));
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

    public function testRelocateMovesBlobAndUpdatesPath(): void
    {
        $oldEmpresa = 'emp-old';
        $newEmpresa = 'emp-new';
        $docId      = 'doc-1';

        $saved = DocumentStorage::save($oldEmpresa, $docId, 'nota.txt', "hola\n");
        $this->assertFileExists($this->tmpRoot . '/' . $saved['ruta_relativa']);

        $newRel = DocumentStorage::relocateToEmpresa($saved['ruta_relativa'], $newEmpresa, $docId);

        $this->assertSame($newEmpresa . '/' . $docId . '/nota.txt', $newRel);
        $this->assertFileExists($this->tmpRoot . '/' . $newRel);
        $this->assertFileDoesNotExist($this->tmpRoot . '/' . $saved['ruta_relativa']);
        $this->assertSame("hola\n", DocumentStorage::read($newRel));
    }

    public function testSlugify(): void
    {
        $this->assertSame(
            'sucursal-norte',
            \App\Libraries\PromoverSucursalService::slugify('Sucursal Norte')
        );
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
