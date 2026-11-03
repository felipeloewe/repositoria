<?php

declare(strict_types=1);

use App\Libraries\EmpresaFormService;
use App\Libraries\FormEngine;
use App\Libraries\RecursoSistema;
use App\Libraries\Roles;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EmpresaFormServiceTest extends CIUnitTestCase
{
    public function testSlugifyDelegatesToFormEngine(): void
    {
        $this->assertSame(
            FormEngine::slugifyClave('Área / departamento'),
            EmpresaFormService::slugifyClave('Área / departamento')
        );
    }

    public function testCanAccessMatchesUploadDocsRoles(): void
    {
        $this->assertTrue(EmpresaFormService::canAccess(['rol' => Roles::ADMINISTRADOR_MASTER]));
        $this->assertTrue(EmpresaFormService::canAccess(['rol' => Roles::JEFE_SUCURSAL]));
        $this->assertFalse(EmpresaFormService::canAccess(['rol' => Roles::CONTADOR]));
        $this->assertFalse(EmpresaFormService::canAccess(['rol' => Roles::ABOGADO]));
        $this->assertFalse(EmpresaFormService::canAccess([]));
    }

    public function testEmpresaGenericClaveDistinctFromSocio(): void
    {
        $this->assertNotSame(
            RecursoSistema::CLAVE_ESTADO_SITUACION,
            RecursoSistema::CLAVE_SOLICITUD_INTERNA_EMPRESA
        );
        $this->assertNotSame(
            RecursoSistema::TIPO_FORM_PLANTILLA,
            RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA
        );
        $tipos = array_column(RecursoSistema::catalogo(), 'tipo');
        $this->assertContains(RecursoSistema::TIPO_FORM_PLANTILLA, $tipos);
        $this->assertContains(RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA, $tipos);
    }

    public function testLabelTipoSeparatesScopes(): void
    {
        $this->assertStringContainsString('socios', strtolower(RecursoSistema::labelTipo(RecursoSistema::TIPO_FORM_PLANTILLA)));
        $this->assertStringContainsString('empresa', strtolower(RecursoSistema::labelTipo(RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA)));
    }
}
