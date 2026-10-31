<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoModel;
use App\Models\SucursalModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $auth = new MembershipAuth();
        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }

        $empresaId = $memb['empresa_id'];
        $deptos    = model(DepartamentoModel::class)->where('empresa_id', $empresaId)->countAllResults();
        $sucursales = model(SucursalModel::class)->where('empresa_id', $empresaId)->countAllResults();
        $docs       = model(DocumentoModel::class)->where('empresa_id', $empresaId)->countAllResults();

        return view('dashboard/index', [
            'title'      => 'Inicio',
            'memb'       => $memb,
            'notice'     => $auth->pullEmpresaNotice(),
            'stats'      => [
                'departamentos' => $deptos,
                'sucursales'    => $sucursales,
                'documentos'    => $docs,
            ],
            'rolLabel'   => Roles::label($memb['rol']),
            'canOrg'     => Roles::canManageOrg($memb['rol']),
        ]);
    }
}
