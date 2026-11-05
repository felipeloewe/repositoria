<?php

declare(strict_types=1);

use App\Libraries\PerfilPermisos;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PerfilPermisosTest extends CIUnitTestCase
{
    public function testMasterDefaultsIncludePerfilesAndSocios(): void
    {
        $d = PerfilPermisos::defaultsFor(PerfilPermisos::ESTEREOTIPO_MASTER);
        $this->assertTrue($d['menu.perfiles']);
        $this->assertTrue($d['menu.socios']);
        $this->assertTrue($d['cap.docs_upload']);
        $this->assertTrue($d['cap.contratos_delete']);
    }

    public function testSocioDefaultsAreFullAccess(): void
    {
        $d = PerfilPermisos::defaultsFor(PerfilPermisos::ESTEREOTIPO_SOCIO);
        foreach (PerfilPermisos::allClaves() as $clave) {
            $this->assertTrue($d[$clave], $clave);
        }
    }

    public function testJefeCannotDeleteContratosByDefault(): void
    {
        $d = PerfilPermisos::defaultsFor(PerfilPermisos::ESTEREOTIPO_JEFE);
        $this->assertTrue($d['cap.contratos_upload']);
        $this->assertFalse($d['cap.contratos_delete']);
        $this->assertFalse($d['menu.perfiles']);
        $this->assertFalse($d['menu.socios']);
    }

    public function testExternoIsReadOnlyCaps(): void
    {
        $d = PerfilPermisos::defaultsFor(PerfilPermisos::ESTEREOTIPO_EXTERNO);
        $this->assertTrue($d['menu.documentos']);
        $this->assertFalse($d['cap.docs_upload']);
        $this->assertFalse($d['cap.docs_delete']);
    }

    public function testDisplayNameWithCalificativo(): void
    {
        $this->assertSame(
            'Socio · Minoritario',
            PerfilPermisos::displayName(PerfilPermisos::ESTEREOTIPO_SOCIO, 'Minoritario')
        );
    }

    public function testMembershipMapping(): void
    {
        $socio = PerfilPermisos::membershipFor(PerfilPermisos::ESTEREOTIPO_SOCIO);
        $this->assertSame(1, $socio['es_socio']);
        $jefe = PerfilPermisos::membershipFor(PerfilPermisos::ESTEREOTIPO_JEFE);
        $this->assertSame(0, $jefe['es_socio']);
    }
}
