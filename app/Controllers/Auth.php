<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\MembresiaModel;

class Auth extends BaseController
{
    public function login()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }
        if ($auth->usuarioId() !== null) {
            return redirect()->to('/login/empresa');
        }

        return view('auth/login', [
            'title' => 'Ingresar',
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function attempt()
    {
        $email    = (string) $this->request->getPost('email');
        $password = (string) $this->request->getPost('password');
        $auth     = new MembershipAuth();
        $result   = $auth->attempt($email, $password);

        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/login')->with('error', $result['error'] ?? 'Error de autenticación.');
        }

        if ($result['need_empresa'] ?? false) {
            return redirect()->to('/login/empresa');
        }

        return redirect()->to('/dashboard');
    }

    public function selectEmpresa()
    {
        $auth = new MembershipAuth();
        $uid  = $auth->usuarioId();
        if ($uid === null) {
            return redirect()->to('/login');
        }
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        $membresias = model(MembresiaModel::class)->forUsuario($uid);

        return view('auth/select_empresa', [
            'title'      => 'Elegir empresa',
            'membresias' => $membresias,
            'roles'      => Roles::class,
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function chooseEmpresa()
    {
        $auth = new MembershipAuth();
        if ($auth->usuarioId() === null) {
            return redirect()->to('/login');
        }

        $membresiaId = (string) $this->request->getPost('membresia_id');
        $result      = $auth->selectMembresia($membresiaId);

        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/login/empresa')->with('error', $result['error'] ?? 'No se pudo seleccionar.');
        }

        return redirect()->to('/dashboard');
    }

    public function switchEmpresa()
    {
        $auth = new MembershipAuth();
        if (! $auth->check()) {
            return redirect()->to('/login');
        }

        $membresias = model(MembresiaModel::class)->forUsuario((string) $auth->usuarioId());
        if (count($membresias) < 2) {
            return redirect()->to('/dashboard');
        }

        return view('auth/select_empresa', [
            'title'      => 'Cambiar empresa',
            'membresias' => $membresias,
            'roles'      => Roles::class,
            'switching'  => true,
            'currentId'  => $auth->membresiaId(),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function doSwitchEmpresa()
    {
        $auth = new MembershipAuth();
        if (! $auth->check()) {
            return redirect()->to('/login');
        }

        $membresiaId = (string) $this->request->getPost('membresia_id');
        $result      = $auth->selectMembresia($membresiaId);

        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/empresa/cambiar')->with('error', $result['error'] ?? 'No se pudo cambiar.');
        }

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        (new MembershipAuth())->logout();

        return redirect()->to('/login');
    }
}
