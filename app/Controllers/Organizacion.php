<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Libraries\Roles;
use App\Models\ActividadModel;
use App\Models\DepartamentoModel;
use App\Models\MembresiaModel;
use App\Models\SucursalModel;
use App\Models\TemaInteresModel;
use App\Models\UsuarioModel;
use RuntimeException;
use Throwable;

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

        $miembros = db_connect()->table('membresias m')
            ->select('m.*, u.nombre AS usuario_nombre, u.email AS usuario_email, s.nombre AS sucursal_nombre')
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->join('sucursales s', 's.id = m.sucursal_id', 'left')
            ->where('m.empresa_id', $empresaId)
            ->where('m.activa', 1)
            ->orderBy('u.nombre', 'ASC')
            ->get()
            ->getResultArray();

        $planSnap = (new PlanEntitlement())->snapshot($empresaId);

        return view('organizacion/index', [
            'title'       => 'Organización',
            'memb'        => $memb,
            'deptos'      => $deptos,
            'sucursales'  => $sucursales,
            'actividades' => $actividades,
            'temas'       => $temas,
            'deptoMap'    => $deptoMap,
            'miembros'    => $miembros,
            'roles'       => Roles::ALL,
            'planSnap'    => $planSnap,
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function storeMembresia()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');

        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $nombre   = trim((string) $this->request->getPost('nombre'));
        $rol      = trim((string) $this->request->getPost('rol'));
        $sucursal = trim((string) $this->request->getPost('sucursal_id')) ?: null;
        $password = (string) $this->request->getPost('password');
        $esSocio  = $this->resolveEsSocio($rol, $this->request->getPost('es_socio'));

        if ($email === '' || $nombre === '' || ! in_array($rol, Roles::ALL, true)) {
            return redirect()->to('/organizacion')->with('error', 'Datos de usuario incompletos o rol inválido.');
        }

        if ($rol === Roles::JEFE_SUCURSAL && ($sucursal === null || $sucursal === '')) {
            return redirect()->to('/organizacion')->with('error', 'Jefe de Sucursal requiere una sucursal.');
        }

        if ($sucursal !== null) {
            $sucRow = model(SucursalModel::class)
                ->where('id', $sucursal)
                ->where('empresa_id', $memb['empresa_id'])
                ->first();
            if ($sucRow === null) {
                return redirect()->to('/organizacion')->with('error', 'Sucursal no válida para esta empresa.');
            }
        }

        try {
            (new PlanEntitlement())->assertCanAddUser($memb['empresa_id']);
        } catch (RuntimeException $e) {
            return redirect()->to('/organizacion')->with('error', $e->getMessage());
        }

        $usuarios = model(UsuarioModel::class);
        $user     = $usuarios->findByEmail($email);

        try {
            if ($user === null) {
                if (strlen($password) < 6) {
                    return redirect()->to('/organizacion')->with('error', 'Para un usuario nuevo indica una contraseña (mín. 6 caracteres).');
                }
                $userId = new_id();
                $usuarios->insert([
                    'id'               => $userId,
                    'email'            => $email,
                    'nombre'           => $nombre,
                    'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
                    'activo'           => 1,
                    'es_admin_central' => 0,
                ]);
            } else {
                $userId = $user['id'];
                $exists = model(MembresiaModel::class)
                    ->where('empresa_id', $memb['empresa_id'])
                    ->where('usuario_id', $userId)
                    ->first();
                if ($exists !== null) {
                    if ((int) ($exists['activa'] ?? 0) === 1) {
                        return redirect()->to('/organizacion')->with('error', 'Ese usuario ya tiene membresía en esta empresa.');
                    }
                    model(MembresiaModel::class)->update($exists['id'], [
                        'rol'         => $rol,
                        'sucursal_id' => $rol === Roles::JEFE_SUCURSAL ? $sucursal : null,
                        'es_socio'    => $esSocio,
                        'activa'      => 1,
                    ]);

                    return redirect()->to('/organizacion')->with('success', 'Membresía reactivada.');
                }
            }

            model(MembresiaModel::class)->insert([
                'id'          => new_id(),
                'empresa_id'  => $memb['empresa_id'],
                'usuario_id'  => $userId,
                'rol'         => $rol,
                'sucursal_id' => $rol === Roles::JEFE_SUCURSAL ? $sucursal : null,
                'es_socio'    => $esSocio,
                'activa'      => 1,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'storeMembresia failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/organizacion')->with('error', 'No se pudo crear la membresía.');
        }

        return redirect()->to('/organizacion')->with('success', 'Usuario agregado a la empresa.');
    }

    /**
     * Toggle atributo Socio en una membresía Master de la empresa.
     */
    public function updateMembresiaSocio()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $membresiaId = trim((string) $this->request->getPost('membresia_id'));
        $esSocioRaw  = $this->request->getPost('es_socio');

        $row = model(MembresiaModel::class)
            ->where('id', $membresiaId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('activa', 1)
            ->first();

        if ($row === null) {
            return redirect()->to('/organizacion')->with('error', 'Membresía no encontrada.');
        }

        if (! Roles::isMaster((string) $row['rol'])) {
            return redirect()->to('/organizacion')->with(
                'error',
                'El atributo Socio solo aplica a Administrador Master.'
            );
        }

        $esSocio = $this->resolveEsSocio((string) $row['rol'], $esSocioRaw);
        model(MembresiaModel::class)->update($row['id'], ['es_socio' => $esSocio]);

        return redirect()->to('/organizacion')->with(
            'success',
            $esSocio ? 'Atributo Socio activado (Menú Socios).' : 'Atributo Socio desactivado.'
        );
    }

    /**
     * Solo Master puede ser socio; checkbox/post se ignora en otros roles.
     */
    private function resolveEsSocio(string $rol, mixed $raw): int
    {
        if (! Roles::isMaster($rol)) {
            return 0;
        }

        if ($raw === null || $raw === false || $raw === '' || $raw === '0' || $raw === 0) {
            return 0;
        }

        return 1;
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
