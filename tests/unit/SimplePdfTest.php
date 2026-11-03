<?php

declare(strict_types=1);

use App\Libraries\SimplePdf;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SimplePdfTest extends CIUnitTestCase
{
    public function testGeneratesPdfHeaderAndTitle(): void
    {
        $bin = SimplePdf::fromKeyValues('Ficha demo', [
            ['label' => 'Nombre', 'value' => 'Ana Pérez'],
            ['label' => 'Fecha', 'value' => '2026-11-03'],
        ], 'Empresa Demo', 'Generado por test');

        $this->assertStringStartsWith('%PDF-1.4', $bin);
        $this->assertStringContainsString('%%EOF', $bin);
        $this->assertGreaterThan(200, strlen($bin));
    }
}
