<?php

declare(strict_types=1);

use App\Libraries\FormEngine;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormEngineSectionsTest extends CIUnitTestCase
{
    public function testGroupCamposBySeccionPreservesOrder(): void
    {
        $campos = [
            ['clave' => 'a', 'etiqueta' => 'A', 'seccion' => 'Uno'],
            ['clave' => 'b', 'etiqueta' => 'B', 'seccion' => 'Uno'],
            ['clave' => 'c', 'etiqueta' => 'C', 'seccion' => 'Dos'],
            ['clave' => 'd', 'etiqueta' => 'D', 'seccion' => ''],
        ];
        $groups = FormEngine::groupCamposBySeccion($campos);
        $this->assertCount(3, $groups);
        $this->assertSame('Uno', $groups[0]['seccion']);
        $this->assertCount(2, $groups[0]['campos']);
        $this->assertSame('Dos', $groups[1]['seccion']);
        $this->assertSame('', $groups[2]['seccion']);
        $this->assertSame('d', $groups[2]['campos'][0]['clave']);
    }

    public function testMarkDuplicateByExactEtiqueta(): void
    {
        $campos = [
            ['clave' => 'cantidadrow1', 'etiqueta' => 'Cantidad (fila 1)'],
            ['clave' => 'cantidadrow1_2', 'etiqueta' => 'Cantidad (fila 1)'],
            ['clave' => 'sucursal', 'etiqueta' => 'SUCURSAL'],
        ];
        $marked = FormEngine::markDuplicateCampos($campos);
        $this->assertTrue($marked[0]['es_duplicado']);
        $this->assertTrue($marked[1]['es_duplicado']);
        $this->assertFalse($marked[2]['es_duplicado']);
    }

    public function testMarkDuplicateByNormalizedClaveStem(): void
    {
        $campos = [
            ['clave' => 'nombres', 'etiqueta' => 'Nombres'],
            ['clave' => 'nombres_2', 'etiqueta' => 'Nombres (cónyuge/conviviente)'],
            ['clave' => 'email', 'etiqueta' => 'E-mail'],
        ];
        $marked = FormEngine::markDuplicateCampos($campos);
        $this->assertTrue($marked[0]['es_duplicado']);
        $this->assertTrue($marked[1]['es_duplicado']);
        $this->assertFalse($marked[2]['es_duplicado']);
    }

    public function testMarkDuplicateByNormalizedEtiquetaIndex(): void
    {
        $campos = [
            ['clave' => 'bf_cni_1', 'etiqueta' => 'Beneficiario final [1] — CNI / ID'],
            ['clave' => 'bf_cni_2', 'etiqueta' => 'Beneficiario final [2] — CNI / ID'],
            ['clave' => 'razon_social', 'etiqueta' => 'Razón social declarante'],
        ];
        $marked = FormEngine::markDuplicateCampos($campos);
        $this->assertTrue($marked[0]['es_duplicado']);
        $this->assertTrue($marked[1]['es_duplicado']);
        $this->assertFalse($marked[2]['es_duplicado']);
    }

    public function testEstadoSeedHasSecciones(): void
    {
        $path = APPPATH . 'Database/Seeds/data/estado_situacion_solicitud_productos_campos.php';
        $this->assertFileExists($path);
        /** @var list<array{clave: string, seccion?: string}> $campos */
        $campos = require $path;
        $this->assertCount(167, $campos);
        $secciones = [];
        foreach ($campos as $c) {
            $this->assertArrayHasKey('seccion', $c);
            $this->assertNotSame('', trim((string) $c['seccion']));
            $secciones[$c['seccion']] = true;
        }
        $this->assertArrayHasKey('ANTECEDENTES PERSONALES', $secciones);
        $this->assertArrayHasKey('SOLICITUD DE PRODUCTOS', $secciones);
        $this->assertArrayHasKey('Anexo', $secciones);
    }

    public function testDjSeedsHaveSecciones(): void
    {
        $files = [
            'dj_beneficiarios_finales_circular_57_campos.php' => 145,
            'dj_art_85_ley_general_bancos_campos.php'         => 49,
        ];
        foreach ($files as $file => $count) {
            $path = APPPATH . 'Database/Seeds/data/' . $file;
            $this->assertFileExists($path, $file);
            /** @var list<array{clave: string, seccion?: string}> $campos */
            $campos = require $path;
            $this->assertCount($count, $campos, $file);
            foreach ($campos as $c) {
                $this->assertArrayHasKey('seccion', $c, $file . ' ' . $c['clave']);
                $this->assertNotSame('', trim((string) $c['seccion']), $file . ' ' . $c['clave']);
            }
        }
    }
}
