<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\ActividadModel;
use App\Models\DepartamentoModel;
use App\Models\SucursalModel;
use App\Models\TemaInteresModel;

/**
 * Fase 1 — Organización CRUD (Master): Departamentos, Sucursales, Actividades, Temas.
 */
class Organizacion extends BaseController
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
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona la organización.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $deptos    = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $sucursales = model(SucursalModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $actividades = model(ActividadModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $temas = model(TemaInteresModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $deptoMap = [];
        foreach ($deptos as $d) {
            $deptoMap[$d['id']] = $d['nombre'];
        }

        return view('organizacion/index', [
            'title'       => 'Organización',
            'memb'        => $memb,
            'deptos'      => $deptos,
            'sucursales'  => $sucursales,
            'actividades' => $actividades,
            'temas'       => $temas,
            'deptoMap'    => $deptoMap,
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function storeDepartamento()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        if ($nombre === '') {
            return redirect()->to('/organizacion')->with('error', 'Nombre de departamento requerido.');
        }

        model(DepartamentoModel::class)->insert([
            'id'         => new_id(),
            'empresa_id' => $memb['empresa_id'],
            'nombre'     => $nombre,
        ]);

        return redirect()->to('/organizacion')->with('success', 'Departamento creado.');
    }

    public function storeSucursal()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $depto  = (string) $this->request->getPost('departamento_id');
        $codigo = trim((string) $this->request->getPost('codigo')) ?: null;

        $deptoRow = model(DepartamentoModel::class)
            ->where('id', $depto)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();

        if ($nombre === '' || $deptoRow === null) {
            return redirect()->to('/organizacion')->with('error', 'Sucursal inválida.');
        }

        model(SucursalModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $depto,
            'nombre'          => $nombre,
            'codigo'          => $codigo,
            'activa'          => 1,
        ]);

        return redirect()->to('/organizacion')->with('success', 'Sucursal creada.');
    }

    public function storeActividad()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $depto  = (string) $this->request->getPost('departamento_id');

        $deptoRow = model(DepartamentoModel::class)
            ->where('id', $depto)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();

        if ($nombre === '' || $deptoRow === null) {
            return redirect()->to('/organizacion')->with('error', 'Actividad inválida.');
        }

        model(ActividadModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $depto,
            'nombre'          => $nombre,
        ]);

        return redirect()->to('/organizacion')->with('success', 'Actividad creada.');
    }

    public function storeTema()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $depto  = (string) $this->request->getPost('departamento_id');

        $deptoRow = model(DepartamentoModel::class)
            ->where('id', $depto)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();

        if ($nombre === '' || $deptoRow === null) {
            return redirect()->to('/organizacion')->with('error', 'Tema inválido.');
        }

        model(TemaInteresModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $depto,
            'nombre'          => $nombre,
        ]);

        return redirect()->to('/organizacion')->with('success', 'Tema de interés creado.');
    }
}
