<?php

declare(strict_types=1);

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentStorage;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentAclContratoTest extends CIUnitTestCase
{
    public function testNormalizeAndListPaths(): void
    {
        $this->assertSame(DocumentAcl::AMBITO_CONTRATO, DocumentAcl::normalizeAmbito('contrato'));
        $this->assertSame(DocumentAcl::AMBITO_SOCIO_CONTRATO, DocumentAcl::normalizeAmbito('socio_contrato'));
        $this->assertSame(DocumentAcl::AMBITO_GENERAL, DocumentAcl::normalizeAmbito('otro'));
        $this->assertSame('/contratos', DocumentAcl::listPathForAmbito('contrato'));
        $this->assertSame('/socios/contratos', DocumentAcl::listPathForAmbito('socio_contrato'));
        $this->assertTrue(DocumentAcl::isIsolatedPool('contrato'));
        $this->assertTrue(DocumentAcl::isIsolatedPool('socio_contrato'));
        $this->assertFalse(DocumentAcl::isIsolatedPool('general'));
    }

    public function testContratoPoolAclMasterJefe(): void
    {
        $empresa = 'emp-1';
        $doc = [
            'empresa_id'             => $empresa,
            'departamento_id'        => 'd1',
            'sucursal_id'            => null,
            'ambito'                 => DocumentAcl::AMBITO_CONTRATO,
            'autorizado_usuario_ids' => [],
        ];
        $master = [
            'empresa_id' => $empresa,
            'usuario_id' => 'u1',
            'rol'        => Roles::ADMINISTRADOR_MASTER,
            'es_socio'   => 0,
        ];
        $jefe = [
            'empresa_id'      => $empresa,
            'usuario_id'      => 'u2',
            'rol'             => Roles::JEFE_SUCURSAL,
            'es_socio'        => 0,
            'sucursal_id'     => 's1',
            'departamento_id' => 'd1',
        ];
        $socioOnly = [
            'empresa_id'  => $empresa,
            'usuario_id'  => 'u3',
            'rol'         => Roles::ADMINISTRADOR_MASTER,
            'es_socio'    => 1,
        ];

        $this->assertTrue(DocumentAcl::canView($master, $doc));
        $this->assertTrue(DocumentAcl::canUpload($master, ['ambito' => DocumentAcl::AMBITO_CONTRATO]));
        $this->assertTrue(DocumentAcl::canDelete($master, $doc));

        $this->assertTrue(DocumentAcl::canView($jefe, $doc));
        $this->assertTrue(DocumentAcl::canUpload($jefe, ['ambito' => DocumentAcl::AMBITO_CONTRATO]));

        // Socio flag does not open socio_contrato unless checking that pool
        $this->assertTrue(DocumentAcl::canView($socioOnly, $doc)); // master role
    }

    public function testSocioContratoOnlyForEsSocio(): void
    {
        $empresa = 'emp-1';
        $doc = [
            'empresa_id'             => $empresa,
            'departamento_id'        => 'd1',
            'sucursal_id'            => null,
            'ambito'                 => DocumentAcl::AMBITO_SOCIO_CONTRATO,
            'autorizado_usuario_ids' => [],
        ];
        $socio = [
            'empresa_id' => $empresa,
            'usuario_id' => 'u1',
            'rol'        => Roles::ADMINISTRADOR_MASTER,
            'es_socio'   => 1,
        ];
        $masterNo = [
            'empresa_id' => $empresa,
            'usuario_id' => 'u2',
            'rol'        => Roles::ADMINISTRADOR_MASTER,
            'es_socio'   => 0,
        ];

        $this->assertTrue(DocumentAcl::canView($socio, $doc));
        $this->assertTrue(DocumentAcl::canUpload($socio, ['ambito' => DocumentAcl::AMBITO_SOCIO_CONTRATO]));
        $this->assertFalse(DocumentAcl::canView($masterNo, $doc));
        $this->assertFalse(DocumentAcl::canUpload($masterNo, ['ambito' => DocumentAcl::AMBITO_SOCIO_CONTRATO]));
    }

    public function testStoragePrefixes(): void
    {
        $this->assertSame(
            'emp1/contratos/doc1',
            DocumentStorage::docDirPrefix('emp1', 'doc1', DocumentAcl::AMBITO_CONTRATO)
        );
        $this->assertSame(
            'emp1/socios/contratos/doc1',
            DocumentStorage::docDirPrefix('emp1', 'doc1', DocumentAcl::AMBITO_SOCIO_CONTRATO)
        );
        $this->assertSame(
            'emp1/socios/doc1',
            DocumentStorage::docDirPrefix('emp1', 'doc1', DocumentAcl::AMBITO_SOCIO)
        );
    }
}
