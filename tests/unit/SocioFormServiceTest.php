<?php

declare(strict_types=1);

use App\Libraries\FormEngine;
use App\Libraries\SocioFormService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SocioFormServiceTest extends CIUnitTestCase
{
    public function testSlugifyClave(): void
    {
        $this->assertSame('nombre_completo', SocioFormService::slugifyClave('Nombre completo'));
        $this->assertSame('fecha_del_poder', SocioFormService::slugifyClave('Fecha del poder'));
        $this->assertSame('campo', SocioFormService::slugifyClave('!!!'));
        $this->assertSame(
            FormEngine::slugifyClave('Nombre completo'),
            SocioFormService::slugifyClave('Nombre completo')
        );
    }

    public function testShareIsUsableRespectsRevokeExpiryAndUses(): void
    {
        $base = [
            'revoked_at' => null,
            'expira_at'  => null,
            'max_usos'   => 1,
            'usos'       => 0,
        ];
        $this->assertTrue(SocioFormService::shareIsUsable($base)['ok']);

        $revoked = $base;
        $revoked['revoked_at'] = '2026-11-01 00:00:00';
        $this->assertFalse(SocioFormService::shareIsUsable($revoked)['ok']);

        $expired = $base;
        $expired['expira_at'] = '2020-01-01 00:00:00';
        $this->assertFalse(SocioFormService::shareIsUsable($expired)['ok']);

        $spent = $base;
        $spent['usos'] = 1;
        $this->assertFalse(SocioFormService::shareIsUsable($spent)['ok']);

        $multi = $base;
        $multi['max_usos'] = 3;
        $multi['usos']     = 2;
        $this->assertTrue(SocioFormService::shareIsUsable($multi)['ok']);
    }
}
