<?php

declare(strict_types=1);

use App\Libraries\IdentityVault;
use App\Libraries\PiiCatalog;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PiiCatalogTest extends CIUnitTestCase
{
    public function testInventoryHasIdentityAndCollateral(): void
    {
        $inv = PiiCatalog::inventory();
        $this->assertNotEmpty($inv);
        $layers = array_unique(array_column($inv, 'layer'));
        $this->assertContains(PiiCatalog::LAYER_IDENTITY, $layers);
        $this->assertContains(PiiCatalog::LAYER_COLLATERAL, $layers);
        $this->assertArrayHasKey('usuarios', PiiCatalog::identityFields());
        $this->assertContains('email', PiiCatalog::identityFields()['usuarios']);
        $this->assertContains('rut', PiiCatalog::identityFields()['equity_personas']);
    }

    public function testLabelPersonaScrubbed(): void
    {
        $label = IdentityVault::labelPersona([
            'id'              => 'cmwabcdefghijklmnopq',
            'nombre'          => 'ANONIMIZADO',
            'pii_scrubbed_at' => '2026-11-20 12:00:00',
        ]);
        $this->assertStringContainsString('anonimizada', mb_strtolower($label));
        $this->assertStringContainsString('cmwabcde', $label);
    }

    public function testIsUsuarioActivo(): void
    {
        $this->assertFalse(IdentityVault::isUsuarioActivo(null));
        $this->assertFalse(IdentityVault::isUsuarioActivo(['activo' => 1, 'deactivated_at' => '2026-01-01']));
        $this->assertFalse(IdentityVault::isUsuarioActivo(['activo' => 1, 'pii_scrubbed_at' => '2026-01-01']));
        $this->assertTrue(IdentityVault::isUsuarioActivo(['activo' => 1]));
    }
}
