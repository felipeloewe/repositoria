<?php

declare(strict_types=1);

use App\Libraries\EquityOrg;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityOrgPersonasTest extends CIUnitTestCase
{
    public function testEsMatrizWhenSinMatrizId(): void
    {
        $org = new EquityOrg();
        $this->assertTrue($org->esMatriz(['id' => 'a', 'equity_es_matriz' => 0, 'equity_matriz_id' => null]));
        $this->assertTrue($org->esMatriz(['id' => 'a', 'equity_es_matriz' => 1, 'equity_matriz_id' => null]));
        $this->assertFalse($org->esMatriz(['id' => 'b', 'equity_es_matriz' => 0, 'equity_matriz_id' => 'a']));
    }

    public function testMatrizIdDe(): void
    {
        $org = new EquityOrg();
        $this->assertSame('a', $org->matrizIdDe(['id' => 'b', 'equity_matriz_id' => 'a']));
        $this->assertSame('a', $org->matrizIdDe(['id' => 'a', 'equity_matriz_id' => null]));
    }
}
