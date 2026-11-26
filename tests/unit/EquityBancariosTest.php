<?php

declare(strict_types=1);

use App\Libraries\EquityBancarios;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityBancariosTest extends CIUnitTestCase
{
    public function testMarcaSoportaUsdSoloVisaMastercard(): void
    {
        $this->assertTrue(EquityBancarios::marcaSoportaUsd('visa'));
        $this->assertTrue(EquityBancarios::marcaSoportaUsd('Mastercard'));
        $this->assertFalse(EquityBancarios::marcaSoportaUsd('american_express'));
        $this->assertFalse(EquityBancarios::marcaSoportaUsd('otra'));
    }

    public function testNormalizarVencimiento(): void
    {
        $this->assertSame('03/2028', EquityBancarios::normalizarVencimiento('3/2028'));
        $this->assertSame('12/2030', EquityBancarios::normalizarVencimiento('2030-12'));
        $this->assertNull(EquityBancarios::normalizarVencimiento('13/2028'));
        $this->assertNull(EquityBancarios::normalizarVencimiento(''));
    }

    public function testNormalizarUltimos4(): void
    {
        $this->assertSame('4321', EquityBancarios::normalizarUltimos4('4321'));
        $this->assertSame('9876', EquityBancarios::normalizarUltimos4('****9876'));
        $this->assertNull(EquityBancarios::normalizarUltimos4('12'));
    }

    public function testMoneyOrNull(): void
    {
        $this->assertSame('1500000.00', EquityBancarios::moneyOrNull('1.500.000'));
        $this->assertSame('2000.50', EquityBancarios::moneyOrNull('2.000,50'));
        $this->assertNull(EquityBancarios::moneyOrNull(''));
    }

    public function testLabels(): void
    {
        $this->assertSame('Visa', EquityBancarios::labelMarca('visa'));
        $this->assertSame('Cuenta corriente', EquityBancarios::labelTipoCuenta('corriente'));
    }

    public function testAppLinks(): void
    {
        $this->assertSame('https://wallet.google/', EquityBancarios::appUrl('google_wallet'));
        $this->assertSame('https://pay.google.com/', EquityBancarios::appUrl('gpay'));
        $this->assertSame('https://link.com/', EquityBancarios::appUrl('link'));
        $this->assertSame('Google Wallet', EquityBancarios::appLabel('google_wallet'));
        $this->assertSame('LINK', EquityBancarios::appLabel('link'));
        $this->assertNull(EquityBancarios::appUrl('unknown'));
    }
}
