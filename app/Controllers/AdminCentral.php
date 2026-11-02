<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\PromoverSucursalService;
use Throwable;

/**
 * Admin central — promoción reiterable de Filial/Sucursal (b) → Empresa (a).
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
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador central puede promover filiales.');
        }

        return $memb;
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
            'Filial promovida a empresa %s (docs: %d, membresías: %d).',
            $result['nueva_empresa_id'],
            $result['documentos_movidos'],
            $result['membresias_creadas']
        );

        return redirect()->to('/admin/promociones')->with('success', $msg);
    }
}
