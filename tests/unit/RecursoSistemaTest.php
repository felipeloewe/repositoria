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
        $this->assertContains(RecursoSistema::CLAVE_SOLICITUD_INTERNA_EMPRESA, $claves);
        $this->assertContains(RecursoSistema::CLAVE_DJ_BENEFICIARIOS_FINALES, $claves);
        $this->assertContains(RecursoSistema::CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS, $claves);
    }

    public function testTiposConocidosIncluyeEmpresa(): void
    {
        $this->assertContains(RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA, RecursoSistema::tiposConocidos());
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

    public function testDjEmpresaCamposSeedFilesAreValid(): void
    {
        $files = [
            RecursoSistema::CLAVE_DJ_BENEFICIARIOS_FINALES => [
                'file'  => 'dj_beneficiarios_finales_circular_57_campos.php',
                'count' => 145,
            ],
            RecursoSistema::CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS => [
                'file'  => 'dj_art_85_ley_general_bancos_campos.php',
                'count' => 49,
            ],
        ];

        foreach ($files as $clave => $meta) {
            $path = APPPATH . 'Database/Seeds/data/' . $meta['file'];
            $this->assertFileExists($path, $clave);
            /** @var list<array{clave: string, etiqueta: string, tipo: string, obligatorio: int, orden: int}> $campos */
            $campos = require $path;
            $this->assertCount($meta['count'], $campos, $clave);
            $claves = [];
            foreach ($campos as $c) {
                $this->assertArrayHasKey('clave', $c);
                $this->assertArrayHasKey('etiqueta', $c);
                $this->assertArrayHasKey('tipo', $c);
                $this->assertContains($c['tipo'], ['text', 'date', 'file']);
                $claves[] = $c['clave'];
            }
            $this->assertSameSize($claves, array_unique($claves), $clave . ' claves únicas');
            $this->assertContains('anexo', $claves, $clave);
            $this->assertSame('file', $campos[array_key_last($campos)]['tipo'], $clave . ' anexo final');
        }
    }
}
