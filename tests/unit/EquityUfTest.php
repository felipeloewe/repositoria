<?php

declare(strict_types=1);

use App\Libraries\EquityUf;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityUfTest extends CIUnitTestCase
{
    public function testParseDecimalPlainAndChileanPesos(): void
    {
        $this->assertSame(1500000.0, EquityUf::parseDecimal('1500000', 2));
        $this->assertSame(1500000.0, EquityUf::parseDecimal('1.500.000', 2));
        $this->assertSame(1500000.5, EquityUf::parseDecimal('1.500.000,50', 2));
        $this->assertSame(15000.0, EquityUf::parseDecimal('15.000', 2));
        $this->assertSame(1500000.0, EquityUf::parseDecimal('$1.500.000', 2));
        $this->assertSame(1500000.0, EquityUf::parseDecimal('CLP 1.500.000', 2));
    }

    public function testParseDecimalUsAndCommaDecimal(): void
    {
        $this->assertSame(1500000.5, EquityUf::parseDecimal('1,500,000.50', 2));
        $this->assertSame(41247.63, EquityUf::parseDecimal('41247,63', 2));
        $this->assertSame(41247.63, EquityUf::parseDecimal('41247.63', 2));
    }

    public function testParseDecimalUfKeepsSingleDotAsDecimal(): void
    {
        $this->assertSame(15.0, EquityUf::parseDecimal('15.000', 4));
        $this->assertSame(3245.6789, EquityUf::parseDecimal('3245,6789', 4));
        $this->assertSame(3245.6789, EquityUf::parseDecimal('3.245,6789', 4));
    }

    public function testResolverParClpPreferenceComputesUf(): void
    {
        $par = EquityUf::resolverPar(
            '',
            '1.500.000',
            'empresa-test',
            null,
            'CLP'
        );

        $this->assertSame('1500000.00', $par['clp']);
        $this->assertNotNull($par['uf']);
        $rate = EquityUf::DEFAULT_VALOR_CLP;
        $this->assertEqualsWithDelta(round(1500000 / $rate, 4), (float) $par['uf'], 0.0001);
    }
}
