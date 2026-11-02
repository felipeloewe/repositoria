<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Libraries\PromoverSucursalService;
use App\Models\PlanModel;
use Throwable;

/**
 * Admin central — empresas/planes + promoción reiterable Filial (b) → Empresa (a).
 * Acceso: usuarios.es_admin_central = 1 (no basta Master de una empresa).
 */
class AdminCentral extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireAdminCentral()
    {
        $auth = new MembershipAuth();
        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! (int) ($memb['es_admin_central'] ?? 0)) {
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador central puede gestionar planes y promociones.');
        }

        return $memb;
    }

    /** Lista empresas + uso vs límites + asignación de plan. */
    public function empresas()
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $db     = db_connect();
        $planes = model(PlanModel::class)->activos();
        $ent    = new PlanEntitlement($db);

        $rows = $db->table('empresas e')
            ->select('e.*, p.codigo AS plan_codigo, p.nombre AS plan_nombre, p.es_gratuito AS plan_gratuito')
            ->join('planes p', 'p.id = e.plan_id', 'left')
            ->orderBy('e.nombre', 'ASC')
            ->get()
            ->getResultArray();

        $empresas = [];
        foreach ($rows as $row) {
            $snap = $ent->snapshot($row['id']);
            $empresas[] = [
                'row'      => $row,
                'snapshot' => $snap,
            ];
        }

        return view('admin/empresas', [
            'title'    => 'Admin central',
            'memb'     => $memb,
            'empresas' => $empresas,
            'planes'   => $planes,
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function assignPlan()
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = trim((string) $this->request->getPost('empresa_id'));
        $planId    = trim((string) $this->request->getPost('plan_id'));

        if ($empresaId === '' || $planId === '') {
            return redirect()->to('/admin/empresas')->with('error', 'Empresa y plan son obligatorios.');
        }

        try {
            $updated = (new PlanEntitlement())->assignPlan($empresaId, $planId);
        } catch (Throwable $e) {
            return redirect()->to('/admin/empresas')->with('error', $e->getMessage());
        }

        $plan = model(PlanModel::class)->find($planId);
        $msg  = sprintf(
            'Plan %s asignado a %s (estado: %s).',
            $plan['codigo'] ?? $planId,
            $updated['nombre'] ?? $empresaId,
            $updated['subscription_status'] ?? '—'
        );

        return redirect()->to('/admin/empresas')->with('success', $msg);
    }

    public function index()
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $db = db_connect();

        $candidatas = $db->table('sucursales s')
            ->select('s.*, e.nombre AS empresa_nombre, d.nombre AS departamento_nombre')
            ->join('empresas e', 'e.id = s.empresa_id')
            ->join('departamentos d', 'd.id = s.departamento_id')
            ->where('s.activa', 1)
            ->groupStart()
                ->where('s.estado', PromoverSucursalService::ESTADO_ACTIVA)
                ->orWhere('s.estado', null)
                ->orWhere('s.estado', '')
            ->groupEnd()
            ->where('s.promoted_to_empresa_id', null)
            ->orderBy('e.nombre', 'ASC')
            ->orderBy('s.nombre', 'ASC')
            ->get()
            ->getResultArray();

        $historial = $db->table('promocion_sucursal_auditoria a')
            ->select('a.*, u.email AS actor_email, eo.nombre AS origen_empresa_nombre, en.nombre AS nueva_empresa_nombre, s.nombre AS origen_sucursal_nombre')
            ->join('usuarios u', 'u.id = a.actor_id', 'left')
            ->join('empresas eo', 'eo.id = a.origen_empresa_id')
            ->join('empresas en', 'en.id = a.nueva_empresa_id')
            ->join('sucursales s', 's.id = a.origen_sucursal_id')
            ->orderBy('a.created_at', 'DESC')
            ->limit(25)
            ->get()
            ->getResultArray();

        return view('admin/promociones', [
            'title'      => 'Admin central',
            'memb'       => $memb,
            'candidatas' => $candidatas,
            'historial'  => $historial,
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function promover()
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $sucursalId = trim((string) $this->request->getPost('sucursal_id'));
        $nombre     = trim((string) $this->request->getPost('nombre'));
        $slug       = trim((string) $this->request->getPost('slug'));

        if ($sucursalId === '') {
            return redirect()->to('/admin/promociones')->with('error', 'Selecciona una sucursal.');
        }

        $options = [
            'actor_id' => $memb['usuario_id'] ?? null,
        ];
        if ($nombre !== '') {
            $options['nombre'] = $nombre;
        }
        if ($slug !== '') {
            $options['slug'] = $slug;
        }

        try {
            $result = (new PromoverSucursalService())->promover($sucursalId, $options);
        } catch (Throwable $e) {
            return redirect()->to('/admin/promociones')->with('error', $e->getMessage());
        }

        $msg = sprintf(
            'Filial promovida a empresa %s (tipo a, requiere Plan de pago). Docs: %d, membresías: %d.',
            $result['nueva_empresa_id'],
            $result['documentos_movidos'],
            $result['membresias_creadas']
        );

        return redirect()->to('/admin/empresas')->with('success', $msg);
    }
}
