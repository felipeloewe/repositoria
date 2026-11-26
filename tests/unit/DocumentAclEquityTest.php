<?php

declare(strict_types=1);

use App\Libraries\DocumentAcl;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentAclEquityTest extends CIUnitTestCase
{
    public function testMasterSeesEquityDocsAndJefeDoesNot(): void
    {
        $empresa = 'emp-1';
        $docEquity = [
            'empresa_id'             => $empresa,
            'departamento_id'        => 'd1',
            'sucursal_id'            => null,
            'ambito'                 => DocumentAcl::AMBITO_EQUITY,
            'autorizado_usuario_ids' => [],
        ];

        $master = [
            'empresa_id'  => $empresa,
            'usuario_id'  => 'u1',
            'rol'         => Roles::ADMINISTRADOR_MASTER,
            'es_socio'    => 0,
            'sucursal_id' => null,
        ];
        $jefe = [
            'empresa_id'      => $empresa,
            'usuario_id'      => 'u2',
            'rol'             => Roles::JEFE_SUCURSAL,
            'es_socio'        => 0,
            'sucursal_id'     => 's1',
            'departamento_id' => 'd1',
        ];
        $contador = [
            'empresa_id' => $empresa,
            'usuario_id' => 'u3',
            'rol'        => Roles::CONTADOR,
            'es_socio'   => 0,
        ];

        $this->assertTrue(DocumentAcl::canView($master, $docEquity));
        $this->assertTrue(DocumentAcl::canUpload($master, ['ambito' => DocumentAcl::AMBITO_EQUITY]));
        $this->assertTrue(DocumentAcl::canDelete($master, $docEquity));

        $this->assertFalse(DocumentAcl::canView($jefe, $docEquity));
        $this->assertFalse(DocumentAcl::canUpload($jefe, ['ambito' => DocumentAcl::AMBITO_EQUITY]));
        $this->assertFalse(DocumentAcl::canView($contador, $docEquity));
    }

    public function testEquityIsIsolatedPoolAndHasOwnListPath(): void
    {
        $this->assertTrue(DocumentAcl::isIsolatedPool(DocumentAcl::AMBITO_EQUITY));
        $this->assertSame('/equity', DocumentAcl::listPathForAmbito(DocumentAcl::AMBITO_EQUITY));
        $this->assertSame(
            DocumentAcl::AMBITO_EQUITY,
            DocumentAcl::normalizeAmbito(DocumentAcl::AMBITO_EQUITY)
        );
        $this->assertSame(
            'emp1/equity/doc1',
            \App\Libraries\DocumentStorage::docDirPrefix('emp1', 'doc1', DocumentAcl::AMBITO_EQUITY)
        );
    }
}
