<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoAutorizacionModel;
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

        $empresaId  = $memb['empresa_id'];
        $deptos     = model(DepartamentoModel::class)->where('empresa_id', $empresaId)->countAllResults();
        $sucursales = model(SucursalModel::class)->where('empresa_id', $empresaId)->countAllResults();

        $docs = model(DocumentoModel::class)->where('empresa_id', $empresaId)->findAll();
        $authRows = model(DocumentoAutorizacionModel::class)->where('empresa_id', $empresaId)->findAll();
        $authByDoc = [];
        foreach ($authRows as $a) {
            $authByDoc[$a['documento_id']][] = $a['usuario_id'];
        }

        $visible = 0;
        foreach ($docs as $doc) {
            if (DocumentAcl::canView($memb, [
                'empresa_id'             => $doc['empresa_id'],
                'departamento_id'        => $doc['departamento_id'],
                'sucursal_id'            => $doc['sucursal_id'],
                'autorizado_usuario_ids' => $authByDoc[$doc['id']] ?? [],
            ])) {
                $visible++;
            }
        }

        return view('dashboard/index', [
            'title'    => 'Inicio',
            'memb'     => $memb,
            'notice'   => $auth->pullEmpresaNotice(),
            'stats'    => [
                'departamentos' => $deptos,
                'sucursales'    => $sucursales,
                'documentos'    => $visible,
            ],
            'rolLabel' => Roles::label($memb['rol']),
            'canOrg'   => Roles::canManageOrg($memb['rol']),
        ]);
    }
}
