<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EmpresaOnboarding;
use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Libraries\PromoverSucursalService;
use App\Models\PlanModel;
use RuntimeException;
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
            'title'      => 'Admin central',
            'memb'       => $memb,
            'empresas'   => $empresas,
            'planes'     => $planes,
            'success'    => session()->getFlashdata('success'),
            'inviteInfo' => session()->getFlashdata('inviteInfo'),
            'error'      => session()->getFlashdata('error'),
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

    /** Alta empresa + contacto Admin Master (invitación: verifica correo y autogestiona clave). */
    public function crearEmpresa()
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $tipo    = trim((string) $this->request->getPost('tipo')) ?: PlanEntitlement::TIPO_B;
        $planId  = trim((string) $this->request->getPost('plan_id')) ?: null;
        $alias   = (string) ($this->request->getPost('alias') ?: $this->request->getPost('slug'));
        $payload = [
            'nombre'         => (string) $this->request->getPost('nombre'),
            'alias'          => $alias,
            'rut'            => (string) $this->request->getPost('rut'),
            'tipo'           => $tipo,
            'plan_id'        => $tipo === PlanEntitlement::TIPO_A ? $planId : null,
            'admin_nombre'   => (string) $this->request->getPost('admin_nombre'),
            'admin_alias'    => (string) $this->request->getPost('admin_alias'),
            'admin_cargo'    => (string) $this->request->getPost('admin_cargo'),
            'admin_rut'      => (string) $this->request->getPost('admin_rut'),
            'admin_email'    => (string) $this->request->getPost('admin_email'),
            // El Superadmin no define la clave: el Admin la autogestiona tras verificar correo.
            'invite_admin'   => true,
            'equity_matriz'  => true,
        ];

        try {
            $result = (new EmpresaOnboarding())->createEmpresaWithAdmin($payload);
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/empresas')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'AdminCentral crearEmpresa: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/admin/empresas')->with('error', 'No se pudo crear la empresa.');
        }

        $planLabel = $result['plan_codigo'] ?? ($result['tipo'] === 'a' ? 'sin plan (requires_plan)' : '—');
        $email     = (string) ($result['admin_email'] ?? $this->request->getPost('admin_email'));
        $msg       = sprintf(
            'Empresa creada (tipo %s, plan %s). Se invitó al Admin %s: debe verificar correo, entrar con clave temporal y definir su propia clave. Luego en Grupo / Usuarios puede inscribir a los declarantes.',
            $result['tipo'],
            $planLabel,
            $email
        );

        $inviteBits = [];
        if (! empty($result['admin_verify_url'])) {
            $inviteBits[] = 'Verificación: ' . $result['admin_verify_url'];
        }
        if (! empty($result['admin_temp_password'])) {
            $inviteBits[] = 'Clave temporal: ' . $result['admin_temp_password'];
        }
        if ($inviteBits !== []) {
            session()->setFlashdata('inviteInfo', implode("\n", $inviteBits));
        }

        return redirect()->to('/admin/empresas')->with('success', $msg);
    }

    /** Ficha de empresa: editar datos + Admins Master + invitar. */
    public function editarEmpresa(string $id)
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $db      = db_connect();
        $empresa = $db->table('empresas e')
            ->select('e.*, p.codigo AS plan_codigo, p.nombre AS plan_nombre')
            ->join('planes p', 'p.id = e.plan_id', 'left')
            ->where('e.id', $id)
            ->get()
            ->getRowArray();
        if ($empresa === null) {
            return redirect()->to('/admin/empresas')->with('error', 'Empresa no encontrada.');
        }

        $onboarding = new EmpresaOnboarding();
        $admins     = $onboarding->listMasterAdmins($id);
        $snap       = (new PlanEntitlement($db))->snapshot($id);

        return view('admin/empresa_editar', [
            'title'      => 'Editar empresa',
            'memb'       => $memb,
            'empresa'    => $empresa,
            'admins'     => $admins,
            'snapshot'   => $snap,
            'planes'     => model(PlanModel::class)->activos(),
            'success'    => session()->getFlashdata('success'),
            'inviteInfo' => session()->getFlashdata('inviteInfo'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function actualizarEmpresa(string $id)
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $alias = (string) ($this->request->getPost('alias') ?: $this->request->getPost('slug'));
        $payload = [
            'nombre'        => (string) $this->request->getPost('nombre'),
            'alias'         => $alias,
            'rut'           => (string) $this->request->getPost('rut'),
            'activa'        => $this->request->getPost('activa') ? 1 : 0,
            'equity_matriz' => $this->request->getPost('equity_matriz') ? 1 : 0,
        ];

        try {
            $updated = (new EmpresaOnboarding())->updateEmpresa($id, $payload);
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'AdminCentral actualizarEmpresa: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', 'No se pudo guardar la empresa.');
        }

        return redirect()->to('/admin/empresas/' . rawurlencode($id))
            ->with('success', 'Empresa «' . ($updated['nombre'] ?? $id) . '» actualizada.');
    }

    /** Invita un Admin Master adicional (o nuevo) a la empresa. */
    public function invitarAdmin(string $id)
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $payload = [
            'admin_nombre' => (string) $this->request->getPost('admin_nombre'),
            'admin_alias'  => (string) $this->request->getPost('admin_alias'),
            'admin_cargo'  => (string) $this->request->getPost('admin_cargo'),
            'admin_rut'    => (string) $this->request->getPost('admin_rut'),
            'admin_email'  => (string) $this->request->getPost('admin_email'),
        ];

        try {
            $result = (new EmpresaOnboarding())->inviteAdminMaster($id, $payload);
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'AdminCentral invitarAdmin: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', 'No se pudo invitar al Admin.');
        }

        $msg = sprintf(
            'Se invitó al Admin %s. Debe verificar correo, entrar con clave temporal y definir su propia clave.',
            $result['admin_email']
        );
        $inviteBits = [
            'Verificación: ' . ($result['admin_verify_url'] ?? ''),
            'Clave temporal: ' . ($result['admin_temp_password'] ?? ''),
        ];
        session()->setFlashdata('inviteInfo', implode("\n", $inviteBits));

        return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('success', $msg);
    }

    /** Reenvía verificación + clave temporal a un Admin Master existente. */
    public function reinvitarAdmin(string $id)
    {
        $memb = $this->requireAdminCentral();
        if (! is_array($memb)) {
            return $memb;
        }

        $usuarioId = trim((string) $this->request->getPost('usuario_id'));
        if ($usuarioId === '') {
            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', 'Usuario obligatorio.');
        }

        try {
            $result = (new EmpresaOnboarding())->reinviteAdminMaster($id, $usuarioId);
        } catch (RuntimeException $e) {
            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'AdminCentral reinvitarAdmin: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('error', 'No se pudo reinvitar al Admin.');
        }

        $msg = sprintf('Reenviada invitación a %s.', $result['admin_email']);
        session()->setFlashdata('inviteInfo', "Verificación: {$result['admin_verify_url']}\nClave temporal: {$result['admin_temp_password']}");

        return redirect()->to('/admin/empresas/' . rawurlencode($id))->with('success', $msg);
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
