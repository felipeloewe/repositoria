<?php

declare(strict_types=1);

use App\Libraries\EquityActivoBitacora;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityActivoBitacoraTest extends CIUnitTestCase
{
    public function testFechaCierreMes(): void
    {
        $ref = new DateTimeImmutable('2026-11-20');
        $this->assertSame('2026-11-30', EquityActivoBitacora::fechaCierreMes($ref));
        $this->assertSame('2026-10-31', EquityActivoBitacora::fechaCierreMesAnterior($ref));
    }

    public function testCamposPorSeccion(): void
    {
        $this->assertSame(
            [EquityActivoBitacora::CAMPO_VALOR_COMERCIAL, EquityActivoBitacora::CAMPO_AVALUO_FISCAL],
            EquityActivoBitacora::camposParaClave('bienes-raices')
        );
        $this->assertSame(
            [EquityActivoBitacora::CAMPO_VALOR_COMERCIAL],
            EquityActivoBitacora::camposParaClave('vehiculos')
        );
        $this->assertSame([], EquityActivoBitacora::camposParaClave('pasivos-inmuebles'));
    }

    public function testExtraerValores(): void
    {
        [$uf, $clp] = EquityActivoBitacora::extraerValores('bienes-raices', EquityActivoBitacora::CAMPO_VALOR_COMERCIAL, [
            'valor_uf'  => '100.5',
            'valor_clp' => '4000000',
        ]);
        $this->assertSame('100.5000', $uf);
        $this->assertSame('4000000.00', $clp);

        [$ufA, $clpA] = EquityActivoBitacora::extraerValores('bienes-raices', EquityActivoBitacora::CAMPO_AVALUO_FISCAL, [
            'avaluo_fiscal' => '41516722',
        ]);
        $this->assertNull($ufA);
        $this->assertSame('41516722.00', $clpA);

        [$ufO, $clpO] = EquityActivoBitacora::extraerValores('otros-activos', EquityActivoBitacora::CAMPO_VALOR_COMERCIAL, [
            'saldo_uf'  => '10',
            'saldo_clp' => '400000',
        ]);
        $this->assertSame('10.0000', $ufO);
        $this->assertSame('400000.00', $clpO);
    }

    public function testMismosValores(): void
    {
        $this->assertTrue(EquityActivoBitacora::mismosValores(null, null, null, null));
        $this->assertTrue(EquityActivoBitacora::mismosValores('10.0000', '100.00', '10', '100'));
        $this->assertFalse(EquityActivoBitacora::mismosValores('10', '100', '11', '100'));
    }
}
