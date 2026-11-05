<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\PerfilCatalogo;
use App\Libraries\PerfilPermisos;
use App\Libraries\Roles;

/**
 * Administración → Perfiles (pestañas Perfiles | Asignación).
 */
class Perfiles extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireMaster()
    {
        $auth = new MembershipAuth();
        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! Roles::canManageOrg($memb['rol'])) {
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona perfiles.');
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
        $perfiles  = PerfilCatalogo::listForEmpresa($empresaId);

        return view('perfiles/index', [
            'title'         => 'Perfiles',
            'memb'          => $memb,
            'tab'           => 'perfiles',
            'perfiles'      => $perfiles,
            'estereotipos'  => PerfilPermisos::ESTEREOTIPOS,
            'success'       => session()->getFlashdata('success'),
            'error'         => session()->getFlashdata('error'),
        ]);
    }

    public function edit(string $id)
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $perfil = PerfilCatalogo::find($id);
        if ($perfil === null || (string) $perfil['empresa_id'] !== (string) $memb['empresa_id']) {
            return redirect()->to('/perfiles')->with('error', 'Perfil no encontrado.');
        }

        return view('perfiles/edit', [
            'title'    => 'Perfiles',
            'memb'     => $memb,
            'tab'      => 'perfiles',
            'perfil'   => $perfil,
            'permisos' => PerfilCatalogo::permisosMap($id),
            'catalogo' => PerfilPermisos::catalogo(),
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function update(string $id)
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $perfil = PerfilCatalogo::find($id);
        if ($perfil === null || (string) $perfil['empresa_id'] !== (string) $memb['empresa_id']) {
            return redirect()->to('/perfiles')->with('error', 'Perfil no encontrado.');
        }

        $posted = $this->request->getPost('permisos');
        if (! is_array($posted)) {
            $posted = [];
        }

        // Unchecked boxes are absent from POST — treat catalog keys as off unless present.
        $normalized = [];
        foreach (PerfilPermisos::allClaves() as $clave) {
            $normalized[$clave] = isset($posted[$clave]);
        }

        try {
            PerfilCatalogo::updatePermisos(
                $id,
                $normalized,
                $this->request->getPost('calificativo'),
                $this->request->getPost('nombre')
            );
        } catch (\Throwable $e) {
            return redirect()->to('/perfiles/' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('/perfiles/' . $id)->with('success', 'Perfil actualizado.');
    }

    public function store()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $est = trim((string) $this->request->getPost('estereotipo'));
        $cal = trim((string) $this->request->getPost('calificativo'));
        $nom = trim((string) $this->request->getPost('nombre'));

        if (! in_array($est, PerfilPermisos::ESTEREOTIPOS, true)) {
            return redirect()->to('/perfiles')->with('error', 'Estereotipo inválido.');
        }

        try {
            $id = PerfilCatalogo::createPerfil(
                (string) $memb['empresa_id'],
                $est,
                $cal !== '' ? $cal : null,
                false,
                $nom !== '' ? $nom : null
            );
        } catch (\Throwable $e) {
            return redirect()->to('/perfiles')->with('error', $e->getMessage());
        }

        return redirect()->to('/perfiles/' . $id)->with('success', 'Variante creada. Ajusta los checkboxes si hace falta.');
    }

    public function asignacion()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        $perfiles  = PerfilCatalogo::listForEmpresa($empresaId);

        $miembros = db_connect()->table('membresias m')
            ->select('m.*, u.nombre AS usuario_nombre, u.email AS usuario_email, s.nombre AS sucursal_nombre, p.nombre AS perfil_nombre, p.estereotipo AS perfil_estereotipo, p.calificativo AS perfil_calificativo')
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->join('perfiles p', 'p.id = m.perfil_id', 'left')
            ->where('m.empresa_id', $empresaId)
            ->where('m.activa', 1)
            ->orderBy('u.nombre', 'ASC')
            ->get()
            ->getResultArray();

        return view('perfiles/asignacion', [
            'title'    => 'Perfiles',
            'memb'     => $memb,
            'tab'      => 'asignacion',
            'perfiles' => $perfiles,
            'miembros' => $miembros,
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function assign()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $membresiaId = trim((string) $this->request->getPost('membresia_id'));
        $perfilId    = trim((string) $this->request->getPost('perfil_id'));

        if ($membresiaId === '' || $perfilId === '') {
            return redirect()->to('/perfiles/asignacion')->with('error', 'Elige usuario y perfil.');
        }

        try {
            PerfilCatalogo::assignToMembresia($membresiaId, $perfilId);
        } catch (\Throwable $e) {
            return redirect()->to('/perfiles/asignacion')->with('error', $e->getMessage());
        }

        return redirect()->to('/perfiles/asignacion')->with('success', 'Perfil asignado. Rol y atributo Socio se alinearon al estereotipo.');
    }
}
