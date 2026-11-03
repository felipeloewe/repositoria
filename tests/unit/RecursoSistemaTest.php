<?php

declare(strict_types=1);

use App\Libraries\RecursoSistema;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class RecursoSistemaTest extends CIUnitTestCase
{
    public function testCatalogoIncluyeEstadoSituacion(): void
    {
        $claves = array_column(RecursoSistema::catalogo(), 'clave');
        $this->assertContains(RecursoSistema::CLAVE_ESTADO_SITUACION, $claves);
    }

    public function testIsGenerica(): void
    {
        $this->assertTrue(RecursoSistema::isGenerica([
            'clave_sistema' => RecursoSistema::CLAVE_ESTADO_SITUACION,
        ]));
        $this->assertFalse(RecursoSistema::isGenerica([
            'clave_sistema' => null,
        ]));
        $this->assertFalse(RecursoSistema::isGenerica([
            'clave_sistema' => '',
        ]));
        $this->assertFalse(RecursoSistema::isGenerica([]));
    }

    public function testCamposSeedFileIsValid(): void
    {
        $path = APPPATH . 'Database/Seeds/data/estado_situacion_solicitud_productos_campos.php';
        $this->assertFileExists($path);
        /** @var list<array{clave: string, etiqueta: string, tipo: string, obligatorio: int, orden: int}> $campos */
        $campos = require $path;
        $this->assertNotEmpty($campos);
        $claves = [];
        foreach ($campos as $c) {
            $this->assertArrayHasKey('clave', $c);
            $this->assertArrayHasKey('etiqueta', $c);
            $this->assertArrayHasKey('tipo', $c);
            $this->assertContains($c['tipo'], ['text', 'date', 'file']);
            $claves[] = $c['clave'];
        }
        $this->assertSameSize($claves, array_unique($claves), 'claves de campos deben ser únicas');
        $this->assertContains('fecha_solicitud', $claves);
        $this->assertContains('nombres', $claves);
        $this->assertContains('rut', $claves);
        $this->assertContains('anexo', $claves); // file opcional
    }
}
