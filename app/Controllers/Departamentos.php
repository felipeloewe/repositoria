<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DepartamentoCatalogo;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\ActividadModel;
use App\Models\DepartamentoModel;
use App\Models\TemaInteresModel;

/**
 * Administración → Departamentos (menú exclusivo con pestañas):
 * Departamentos | Actividades | Temas de Interés.
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
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona departamentos y tags.');
        }

        return $memb;
    }

    /** @return array<string, string> */
    private function deptoMap(string $empresaId): array
    {
        $map = [];
        foreach (DepartamentoCatalogo::listActivos($empresaId) as $d) {
            $map[(string) $d['id']] = (string) $d['nombre'];
        }

        return $map;
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

        $clave  = trim((string) $this->request->getPost('clave'));
        $raw    = $this->request->getPost('activo');
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

    public function actividades()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        DepartamentoCatalogo::ensureForEmpresa($empresaId);
        $deptos = DepartamentoCatalogo::listActivos($empresaId);

        $actividades = model(ActividadModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return view('departamentos/actividades', [
            'title'       => 'Actividades',
            'memb'        => $memb,
            'deptos'      => $deptos,
            'deptoMap'    => $this->deptoMap($empresaId),
            'actividades' => $actividades,
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function storeActividad()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $empresaId = (string) $memb['empresa_id'];
        $nombre    = trim((string) $this->request->getPost('nombre'));
        $deptoRaw  = trim((string) $this->request->getPost('departamento_id'));
        $deptoId   = $deptoRaw !== '' ? $deptoRaw : null;

        if ($nombre === '') {
            return redirect()->to('/departamentos/actividades')->with('error', 'Nombre de actividad requerido.');
        }

        if ($deptoId !== null) {
            $deptoRow = model(DepartamentoModel::class)
                ->where('id', $deptoId)
                ->where('empresa_id', $empresaId)
                ->first();
            if ($deptoRow === null) {
                return redirect()->to('/departamentos/actividades')->with('error', 'Departamento no válido.');
            }
        }

        $dup = model(ActividadModel::class)
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nombre)
            ->first();
        if ($dup !== null) {
            return redirect()->to('/departamentos/actividades')->with('error', 'Ya existe una actividad con ese nombre.');
        }

        model(ActividadModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $empresaId,
            'departamento_id' => $deptoId,
            'nombre'          => $nombre,
        ]);

        return redirect()->to('/departamentos/actividades')->with('success', 'Actividad creada.');
    }

    public function temas()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        DepartamentoCatalogo::ensureForEmpresa($empresaId);
        $deptos = DepartamentoCatalogo::listActivos($empresaId);

        $temas = model(TemaInteresModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return view('departamentos/temas', [
            'title'    => 'Temas de Interés',
            'memb'     => $memb,
            'deptos'   => $deptos,
            'deptoMap' => $this->deptoMap($empresaId),
            'temas'    => $temas,
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function storeTema()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $empresaId = (string) $memb['empresa_id'];
        $nombre    = trim((string) $this->request->getPost('nombre'));
        $deptoRaw  = trim((string) $this->request->getPost('departamento_id'));
        $deptoId   = $deptoRaw !== '' ? $deptoRaw : null;

        if ($nombre === '') {
            return redirect()->to('/departamentos/temas')->with('error', 'Nombre de tema requerido.');
        }

        if ($deptoId !== null) {
            $deptoRow = model(DepartamentoModel::class)
                ->where('id', $deptoId)
                ->where('empresa_id', $empresaId)
                ->first();
            if ($deptoRow === null) {
                return redirect()->to('/departamentos/temas')->with('error', 'Departamento no válido.');
            }
        }

        $dup = model(TemaInteresModel::class)
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nombre)
            ->first();
        if ($dup !== null) {
            return redirect()->to('/departamentos/temas')->with('error', 'Ya existe un tema con ese nombre.');
        }

        model(TemaInteresModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $empresaId,
            'departamento_id' => $deptoId,
            'nombre'          => $nombre,
        ]);

        return redirect()->to('/departamentos/temas')->with('success', 'Tema de interés creado.');
    }
}
