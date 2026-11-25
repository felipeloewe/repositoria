<?php

declare(strict_types=1);

use App\Libraries\EquityCierre;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityCierreTest extends CIUnitTestCase
{
    public function testLabelMes(): void
    {
        $this->assertSame('Noviembre', EquityCierre::labelMes(11));
        $this->assertSame('Enero', EquityCierre::labelMes(1));
        $this->assertSame('13', EquityCierre::labelMes(13));
    }

    public function testTiposReporteCompletos(): void
    {
        $tipos = EquityCierre::TIPOS_REPORTE;
        foreach (['balance', 'ingresos', 'egresos', 'pasivos', 'activos', 'situacion'] as $t) {
            $this->assertArrayHasKey($t, $tipos);
        }
    }

    public function testFilaPasivoMesUf(): void
    {
        $row = [
            'id'                => 'p1',
            'categoria'         => 'inmueble',
            'banco_descripcion' => 'Banco Test',
            'numero_credito'    => '123',
            'moneda'            => 'UF',
            'dividendo_uf'      => 12.5,
            'amortizacion_uf'   => 8.0,
            'interes_uf'        => 4.5,
            'saldo_uf'          => 1000.0,
            'dividendo_n'       => 10,
            'fecha_registro'    => '2026-11-05',
        ];
        $fila = EquityCierre::filaPasivoMes($row, 40000.0, 2026, 11);
        $this->assertSame('UF', $fila['moneda']);
        $this->assertSame(12.5, $fila['cuota_uf']);
        $this->assertEqualsWithDelta(500000.0, (float) $fila['cuota_clp'], 0.01);
        $this->assertSame(8.0, $fila['amortizacion_uf']);
        $this->assertSame(4.5, $fila['interes_uf']);
    }
}
