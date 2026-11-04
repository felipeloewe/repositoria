<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DepartamentoCatalogo;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;

/**
 * Administración → Departamentos: genéricos (10) on/off + custom.
 */
class Departamentos extends BaseController
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
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona departamentos.');
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
        DepartamentoCatalogo::ensureForEmpresa($empresaId);

        return view('departamentos/index', [
            'title'     => 'Departamentos',
            'memb'      => $memb,
            'genericos' => DepartamentoCatalogo::listGenericosForEmpresa($empresaId),
            'custom'    => DepartamentoCatalogo::listCustom($empresaId),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function toggle()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $clave = trim((string) $this->request->getPost('clave'));
        $raw   = $this->request->getPost('activo');
        $activo = $raw === '1' || $raw === 1 || $raw === true;

        if (! DepartamentoCatalogo::setActivo((string) $memb['empresa_id'], $clave, (bool) $activo)) {
            return redirect()->to('/departamentos')->with('error', 'Departamento genérico no válido.');
        }

        $label = $activo ? 'activado' : 'desactivado';

        return redirect()->to('/departamentos')->with('success', 'Departamento genérico ' . $label . '.');
    }

    public function storeCustom()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        if ($nombre === '') {
            return redirect()->to('/departamentos')->with('error', 'Nombre de departamento requerido.');
        }

        // Evitar colisión con nombres del catálogo genérico.
        foreach (DepartamentoCatalogo::catalogo() as $item) {
            if (mb_strtolower($item['nombre']) === mb_strtolower($nombre)) {
                return redirect()->to('/departamentos')
                    ->with('error', '«' . $item['nombre'] . '» es un departamento genérico; actívalo en la lista de genéricos.');
            }
        }

        $exists = model(DepartamentoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('nombre', $nombre)
            ->first();
        if ($exists !== null) {
            return redirect()->to('/departamentos')->with('error', 'Ya existe un departamento con ese nombre.');
        }

        model(DepartamentoModel::class)->insert([
            'id'            => new_id(),
            'empresa_id'    => $memb['empresa_id'],
            'nombre'        => $nombre,
            'clave_sistema' => null,
            'activo'        => 1,
        ]);

        return redirect()->to('/departamentos')->with('success', 'Departamento custom creado.');
    }
}
