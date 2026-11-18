<?php

declare(strict_types=1);

use App\Libraries\Product;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ProductTest extends CIUnitTestCase
{
    public function testDefaultIsRepositoria(): void
    {
        putenv('PRODUCT_KEY');
        unset($_SERVER['HTTP_HOST'], $_ENV['PRODUCT_KEY']);

        $this->assertSame(Product::KEY_REPOSITORIA, Product::key());
        $this->assertSame('Repositoria', Product::name());
        $this->assertFalse(Product::isEquity());
    }

    public function testEnvProductKeyEquity(): void
    {
        putenv('PRODUCT_KEY=equity');
        $_ENV['PRODUCT_KEY'] = 'equity';

        $this->assertSame(Product::KEY_EQUITY, Product::key());
        $this->assertSame('Equity', Product::name());
        $this->assertSame('Información financiera', Product::tagline());
        $this->assertTrue(Product::isEquity());

        putenv('PRODUCT_KEY');
        unset($_ENV['PRODUCT_KEY']);
    }

    public function testHostEquityBooklet(): void
    {
        putenv('PRODUCT_KEY');
        unset($_ENV['PRODUCT_KEY']);
        $_SERVER['HTTP_HOST'] = 'equity.booklet.cl';

        $this->assertSame(Product::KEY_EQUITY, Product::key());
        $this->assertTrue(Product::isPathAllowed('/equity'));
        $this->assertTrue(Product::isPathAllowed('/equity/abc'));
        $this->assertTrue(Product::isPathAllowed('/documentos/abc123'));
        $this->assertTrue(Product::isPathAllowed('/documentos/abc123/download'));
        $this->assertFalse(Product::isPathAllowed('/documentos'));
        $this->assertFalse(Product::isPathAllowed('/correo'));
        $this->assertFalse(Product::isPathAllowed('/socios'));

        unset($_SERVER['HTTP_HOST']);
    }
}
