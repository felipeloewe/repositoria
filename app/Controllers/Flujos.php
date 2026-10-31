<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\FlujosAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;

class Flujos extends BaseController
{
    public function index()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! FlujosAcl::canBrowseFlujos($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin acceso a flujos.');
        }

        $db = db_connect();
        $fisCount = $db->table('fiscalizacion_solicitudes')
            ->where('empresa_id', $memb['empresa_id'])->countAllResults();
        $pendientes = $db->table('fiscalizacion_solicitudes')
            ->where('empresa_id', $memb['empresa_id'])
            ->where('estado', 'PENDIENTE')->countAllResults();

        $canalQ = $db->table('canal_paquetes')->where('empresa_id', $memb['empresa_id']);
        if (Roles::isExternal($memb['rol'])) {
            $canalQ->where('destinatario_id', $memb['usuario_id'])->where('estado', 'ACTIVO');
        }
        $canalCount = $canalQ->countAllResults();

        return view('flujos/index', [
            'title'      => 'Flujos',
            'memb'       => $memb,
            'fisCount'   => $fisCount,
            'pendientes' => $pendientes,
            'canalCount' => $canalCount,
            'canRequest' => FlujosAcl::canRequestFiscalizacion($memb),
            'canReview'  => FlujosAcl::canReviewFiscalizacion($memb),
            'canCanales' => FlujosAcl::canManageCanalPaquetes($memb) || Roles::isExternal($memb['rol']),
            'isExternal' => Roles::isExternal($memb['rol']),
        ]);
    }
}
