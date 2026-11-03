<?php

declare(strict_types=1);

use App\Libraries\DocumentAcl;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentAclSocioTest extends CIUnitTestCase
{
    public function testSocioSeesSocioDocsAndMasterWithoutFlagDoesNot(): void
    {
        $empresa = 'emp-1';
        $docSocio = [
            'empresa_id'             => $empresa,
            'departamento_id'        => 'd1',
            'sucursal_id'            => null,
            'ambito'                 => DocumentAcl::AMBITO_SOCIO,
            'autorizado_usuario_ids' => [],
        ];

        $socio = [
            'empresa_id'  => $empresa,
            'usuario_id'  => 'u1',
            'rol'         => Roles::ADMINISTRADOR_MASTER,
            'es_socio'    => 1,
            'sucursal_id' => null,
        ];
        $masterNoSocio = [
            'empresa_id'  => $empresa,
            'usuario_id'  => 'u2',
            'rol'         => Roles::ADMINISTRADOR_MASTER,
            'es_socio'    => 0,
            'sucursal_id' => null,
        ];
        $jefe = [
            'empresa_id'      => $empresa,
            'usuario_id'      => 'u3',
            'rol'             => Roles::JEFE_SUCURSAL,
            'es_socio'        => 0,
            'sucursal_id'     => 's1',
            'departamento_id' => 'd1',
        ];

        $this->assertTrue(DocumentAcl::canView($socio, $docSocio));
        $this->assertTrue(DocumentAcl::canUpload($socio, ['ambito' => DocumentAcl::AMBITO_SOCIO]));
        $this->assertTrue(DocumentAcl::canDelete($socio, $docSocio));

        $this->assertFalse(DocumentAcl::canView($masterNoSocio, $docSocio));
        $this->assertFalse(DocumentAcl::canUpload($masterNoSocio, ['ambito' => DocumentAcl::AMBITO_SOCIO]));
        $this->assertFalse(DocumentAcl::canView($jefe, $docSocio));
    }

    public function testGeneralDocsStillFollowMasterAclAndIgnoreSocioFlag(): void
    {
        $empresa = 'emp-1';
        $doc = [
            'empresa_id'             => $empresa,
            'departamento_id'        => 'd1',
            'sucursal_id'            => null,
            'ambito'                 => DocumentAcl::AMBITO_GENERAL,
            'autorizado_usuario_ids' => [],
        ];
        $master = [
            'empresa_id' => $empresa,
            'usuario_id' => 'u1',
            'rol'        => Roles::ADMINISTRADOR_MASTER,
            'es_socio'   => 0,
        ];
        $jefeOther = [
            'empresa_id'      => $empresa,
            'usuario_id'      => 'u2',
            'rol'             => Roles::JEFE_SUCURSAL,
            'sucursal_id'     => 's2',
            'departamento_id' => 'd2',
        ];

        $this->assertTrue(DocumentAcl::canView($master, $doc));
        $this->assertFalse(DocumentAcl::canView($jefeOther, $doc));
    }

    public function testStorageSocioPrefix(): void
    {
        $prefix = \App\Libraries\DocumentStorage::docDirPrefix('empA', 'docB', 'socio');
        $this->assertSame('empA/socios/docB', $prefix);
        $general = \App\Libraries\DocumentStorage::docDirPrefix('empA', 'docB', 'general');
        $this->assertSame('empA/docB', $general);
    }
}
