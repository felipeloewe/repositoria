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
        $this->assertContains('gestion_personas', $claves);
        $this->assertContains('admin_finanzas_contabilidad', $claves);
        $this->assertContains('investigacion_desarrollo', $claves);
        foreach ($cat as $item) {
            $this->assertNotSame('', trim($item['nombre']));
            $this->assertNotSame('', trim($item['descripcion']));
            $this->assertMatchesRegularExpression('/^[a-z0-9_]{1,64}$/', $item['clave']);
        }
    }

    public function testIsGenerico(): void
    {
        $this->assertTrue(DepartamentoCatalogo::isGenerico('ventas_desarrollo_negocios'));
        $this->assertFalse(DepartamentoCatalogo::isGenerico('finanzas')); // clave legacy
        $this->assertFalse(DepartamentoCatalogo::isGenerico(null));
        $this->assertFalse(DepartamentoCatalogo::isGenerico(''));
        $this->assertFalse(DepartamentoCatalogo::isGenerico('otro'));
    }

    public function testLegacyClaveMapCubreCatalogoAnterior(): void
    {
        $map = DepartamentoCatalogo::legacyClaveMap();
        $this->assertSame('gestion_personas', $map['recursos_humanos']);
        $this->assertSame('operaciones_cadena_suministro', $map['operaciones']);
        foreach ($map as $new) {
            $this->assertTrue(DepartamentoCatalogo::isGenerico($new));
        }
    }
}
