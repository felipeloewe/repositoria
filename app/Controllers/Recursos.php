<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\RecursoAgrupacionCatalogo;
use App\Libraries\Roles;

/**
 * Administración → Recursos: agrupaciones de servicios y entidades (checkboxes por empresa).
 */
class Recursos extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireMaster()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! Roles::canManageOrg($memb['rol'])) {
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona Recursos.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        RecursoAgrupacionCatalogo::ensureForEmpresa($empresaId);

        return view('recursos/index', [
            'title'       => 'Recursos',
            'memb'        => $memb,
            'agrupaciones'=> RecursoAgrupacionCatalogo::treeForEmpresa($empresaId),
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function toggleAgrupacion()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $agrupacionId = trim((string) $this->request->getPost('agrupacion_id'));
        $raw          = $this->request->getPost('marcado');
        $marcado      = $raw === '1' || $raw === 1 || $raw === true;

        if ($agrupacionId === '' || ! RecursoAgrupacionCatalogo::setAgrupacionMarcada((string) $memb['empresa_id'], $agrupacionId, (bool) $marcado)) {
            return redirect()->to('/recursos')->with('error', 'Agrupación no válida.');
        }

        return redirect()->to('/recursos')->with(
            'success',
            $marcado ? 'Agrupación marcada para esta empresa.' : 'Agrupación desmarcada.'
        );
    }

    public function toggleEntidad()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $entidadId = trim((string) $this->request->getPost('entidad_id'));
        $raw       = $this->request->getPost('marcado');
        $marcado   = $raw === '1' || $raw === 1 || $raw === true;

        if ($entidadId === '' || ! RecursoAgrupacionCatalogo::setEntidadMarcada((string) $memb['empresa_id'], $entidadId, (bool) $marcado)) {
            return redirect()->to('/recursos')->with('error', 'Entidad no válida.');
        }

        return redirect()->to('/recursos')->with(
            'success',
            $marcado ? 'Entidad marcada (contratada / vigente).' : 'Entidad desmarcada.'
        );
    }
}
