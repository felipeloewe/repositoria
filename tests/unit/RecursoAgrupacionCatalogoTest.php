<?php

declare(strict_types=1);

use App\Libraries\RecursoAgrupacionCatalogo;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RecursoAgrupacionCatalogoTest extends CIUnitTestCase
{
    public function testCatalogoTieneCincoAgrupaciones(): void
    {
        $cat = RecursoAgrupacionCatalogo::catalogo();
        $this->assertCount(5, $cat);
        $claves = array_column($cat, 'clave');
        $this->assertSameSize($claves, array_unique($claves));
        $this->assertContains('basicos_sanitarios', $claves);
        $this->assertContains('telecomunicaciones', $claves);
        $this->assertContains('transporte_movilidad', $claves);
        $this->assertContains('bancos_financieras', $claves);
        $this->assertContains('seguros_educacion_otros', $claves);
    }

    public function testEntidadesUnicasYServiciosConEntidades(): void
    {
        $clavesEnt = [];
        foreach (RecursoAgrupacionCatalogo::catalogo() as $agr) {
            $this->assertNotSame('', trim($agr['nombre']));
            $this->assertNotEmpty($agr['servicios']);
            foreach ($agr['servicios'] as $svc) {
                $this->assertNotEmpty($svc['entidades']);
                foreach ($svc['entidades'] as $ent) {
                    $this->assertMatchesRegularExpression('/^[a-z0-9_]{1,64}$/', $ent['clave']);
                    $clavesEnt[] = $ent['clave'];
                }
            }
        }
        $this->assertSameSize($clavesEnt, array_unique($clavesEnt));
        $this->assertGreaterThan(40, count($clavesEnt));
    }
}
