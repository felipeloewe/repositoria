<?php

declare(strict_types=1);

use App\Libraries\EquityOcr;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityOcrTest extends CIUnitTestCase
{
    public function testEstadoTieneCamposEsperados(): void
    {
        $e = EquityOcr::estado();
        $this->assertArrayHasKey('proc_open', $e);
        $this->assertArrayHasKey('tesseract', $e);
        $this->assertArrayHasKey('pdftotext', $e);
        $this->assertArrayHasKey('listo', $e);
        $this->assertArrayHasKey('mensaje', $e);
        $this->assertIsBool($e['proc_open']);
        $this->assertIsBool($e['listo']);
        $this->assertNotSame('', $e['mensaje']);
    }
}
