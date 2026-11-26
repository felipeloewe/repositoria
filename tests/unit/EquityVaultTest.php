<?php

declare(strict_types=1);

use App\Libraries\EquityVault;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EquityVaultTest extends CIUnitTestCase
{
    public function testValidarFormatoPin(): void
    {
        $this->assertNull(EquityVault::validarFormatoPin('1234'));
        $this->assertNull(EquityVault::validarFormatoPin('123456789012'));
        $this->assertNotNull(EquityVault::validarFormatoPin('12'));
        $this->assertNotNull(EquityVault::validarFormatoPin('12ab'));
        $this->assertNotNull(EquityVault::validarFormatoPin(''));
    }
}
