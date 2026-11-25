<?php

declare(strict_types=1);

use App\Libraries\EquityDesarrolloImport;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityDesarrolloImportTest extends CIUnitTestCase
{
    public function testParseCsvTomaSoloColumnasPresentes(): void
    {
        $csv = "Fecha;N° Dividendo;Cuota UF;Amortización UF;Saldo UF\n"
            . "05-11-2026;10;12,5000;8,0000;950,0000\n"
            . "05-12-2026;11;12,5000;8,1000;941,9000\n";

        $res = EquityDesarrolloImport::parse('desarrollo.csv', $csv, 'text/csv');
        $this->assertTrue($res['ok'] ?? false, $res['error'] ?? 'fail');
        $this->assertCount(2, $res['filas'] ?? []);
        $this->assertContains('fecha', $res['columnas'] ?? []);
        $this->assertContains('cuota_uf', $res['columnas'] ?? []);
        $this->assertArrayNotHasKey('interes_clp', $res['filas'][0]);
        $this->assertSame(10, $res['filas'][0]['dividendo_n']);
        $this->assertEqualsWithDelta(12.5, (float) $res['filas'][0]['cuota_uf'], 0.0001);
    }

    public function testParseCsvSinEncabezadosFalla(): void
    {
        $res = EquityDesarrolloImport::parse('x.csv', "foo;bar\n1;2\n", 'text/csv');
        $this->assertFalse($res['ok'] ?? true);
    }
}
