<?php

declare(strict_types=1);

use App\Libraries\DepartamentoCatalogo;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DepartamentoCatalogoTest extends CIUnitTestCase
{
    public function testCatalogoTieneDiezClavesUnicas(): void
    {
        $cat = DepartamentoCatalogo::catalogo();
        $this->assertCount(10, $cat);
        $claves = array_column($cat, 'clave');
        $this->assertSameSize($claves, array_unique($claves));
        $this->assertContains('operaciones', $claves);
        $this->assertContains('administracion', $claves);
        $this->assertContains('atencion_clientes', $claves);
        foreach ($cat as $item) {
            $this->assertNotSame('', trim($item['nombre']));
            $this->assertNotSame('', trim($item['descripcion']));
            $this->assertMatchesRegularExpression('/^[a-z0-9_]{1,64}$/', $item['clave']);
        }
    }

    public function testIsGenerico(): void
    {
        $this->assertTrue(DepartamentoCatalogo::isGenerico('finanzas'));
        $this->assertFalse(DepartamentoCatalogo::isGenerico(null));
        $this->assertFalse(DepartamentoCatalogo::isGenerico(''));
        $this->assertFalse(DepartamentoCatalogo::isGenerico('otro'));
    }
}
