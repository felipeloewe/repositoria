<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\EquityAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\Product;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoAutorizacionModel;
use App\Models\DocumentoModel;
use App\Models\EquityFichaModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityRespaldoModel;
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

        if (Product::isEquity()) {
            return $this->equityDashboard($auth, $memb);
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
                'ambito'                 => $doc['ambito'] ?? DocumentAcl::AMBITO_GENERAL,
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

    /**
     * @param array<string, mixed> $memb
     */
    private function equityDashboard(MembershipAuth $auth, array $memb)
    {
        $empresaId = $memb['empresa_id'];
        $personas  = model(EquityPersonaModel::class)->where('empresa_id', $empresaId)->findAll();
        $socios     = 0;
        $gerentes   = 0;
        $familiares = 0;
        $activos    = 0;
        foreach ($personas as $p) {
            if ((int) ($p['activo'] ?? 1) === 1) {
                $activos++;
            }
            $t = (string) ($p['tipo'] ?? '');
            if ($t === EquityAcl::TIPO_GERENTE) {
                $gerentes++;
            } elseif ($t === EquityAcl::TIPO_FAMILIAR) {
                $familiares++;
            } else {
                $socios++;
            }
        }

        $fichas = model(EquityFichaModel::class)->where('empresa_id', $empresaId)->countAllResults();
        $respaldos = model(EquityRespaldoModel::class)->where('empresa_id', $empresaId)->countAllResults();

        return view('dashboard/equity', [
            'title'     => 'Inicio',
            'memb'      => $memb,
            'notice'    => $auth->pullEmpresaNotice(),
            'rolLabel'  => Roles::label($memb['rol']),
            'canEquity' => EquityAcl::canAccess($memb),
            'canManage' => EquityAcl::canManage($memb),
            'stats'     => [
                'personas'   => count($personas),
                'activos'    => $activos,
                'socios'     => $socios,
                'gerentes'   => $gerentes,
                'familiares' => $familiares,
                'fichas'     => $fichas,
                'respaldos'  => $respaldos,
            ],

            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }
}
