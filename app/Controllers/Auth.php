<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityOrg;
use App\Libraries\EquityPassword;
use App\Libraries\MembershipAuth;
use App\Libraries\Product;
use App\Libraries\Roles;
use App\Models\MembresiaModel;
use App\Models\UsuarioModel;

class Auth extends BaseController
{
    public function login()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return $this->postLoginRedirect($auth);
        }
        if ($auth->usuarioId() !== null) {
            return redirect()->to('/login/empresa');
        }

        return view('auth/login', [
            'title'   => 'Ingresar',
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
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

        return $this->postLoginRedirect($auth);
    }

    public function selectEmpresa()
    {
        $auth = new MembershipAuth();
        $uid  = $auth->usuarioId();
        if ($uid === null) {
            return redirect()->to('/login');
        }
        if ($auth->check()) {
            return $this->postLoginRedirect($auth);
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

        $auth->clearEquityPersona();

        return $this->postLoginRedirect($auth);
    }

    public function verificarEmail()
    {
        $token = trim((string) $this->request->getGet('t'));
        if ($token === '') {
            return redirect()->to('/bienvenida')->with('error', 'Enlace de verificación inválido.');
        }
        $user = model(UsuarioModel::class)->where('email_verify_token', $token)->first();
        if (! is_array($user)) {
            return redirect()->to('/bienvenida')->with('error', 'Enlace de verificación inválido o ya usado.');
        }
        model(UsuarioModel::class)->update($user['id'], [
            'email_verified_at'  => date('Y-m-d H:i:s'),
            'email_verify_token' => null,
        ]);

        return redirect()->to('/bienvenida');
    }

    /** Página pública post-verificación (mismo look auth / Equity). */
    public function bienvenida()
    {
        return view('auth/bienvenida', [
            'title' => 'Bienvenido',
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function cambiarClaveForm()
    {
        $auth = new MembershipAuth();
        if ($auth->usuarioId() === null) {
            return redirect()->to('/login');
        }

        return view('auth/cambiar_clave', [
            'title' => 'Cambiar clave',
            'error' => session()->getFlashdata('error'),
        ]);
    }

    public function cambiarClave()
    {
        $auth = new MembershipAuth();
        $uid  = $auth->usuarioId();
        if ($uid === null) {
            return redirect()->to('/login');
        }

        $pass  = (string) $this->request->getPost('password');
        $pass2 = (string) $this->request->getPost('password_confirm');
        if ($pass !== $pass2) {
            return redirect()->to('/cambiar-clave')->with('error', 'Las claves no coinciden.');
        }
        $val = EquityPassword::validate($pass);
        if (! ($val['ok'] ?? false)) {
            return redirect()->to('/cambiar-clave')->with('error', $val['error'] ?? 'Clave inválida.');
        }

        model(UsuarioModel::class)->update($uid, [
            'password_hash'        => password_hash($pass, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        return $this->postLoginRedirect($auth);
    }

    public function selectPersona()
    {
        $auth = new MembershipAuth();
        if (! $auth->check()) {
            return redirect()->to('/login');
        }
        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login/empresa');
        }

        $personas = (new EquityOrg())->personasDeclarables((string) $auth->usuarioId(), $memb['empresa_id']);
        if ($personas === []) {
            return redirect()->to(Product::homePath())
                ->with('error', 'No tienes personas asignadas para declarar. Contacta a la matriz.');
        }
        if (count($personas) === 1) {
            $auth->setEquityPersonaId($personas[0]['id']);

            return redirect()->to(Product::homePath());
        }

        return view('auth/select_persona', [
            'title'    => '¿A quién representas?',
            'personas' => $personas,
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function choosePersona()
    {
        $auth = new MembershipAuth();
        if (! $auth->check()) {
            return redirect()->to('/login');
        }
        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login/empresa');
        }

        $personaId = trim((string) $this->request->getPost('persona_id'));
        $personas  = (new EquityOrg())->personasDeclarables((string) $auth->usuarioId(), $memb['empresa_id']);
        $ok        = false;
        foreach ($personas as $p) {
            if (($p['id'] ?? '') === $personaId) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            return redirect()->to('/login/persona')->with('error', 'Persona no válida.');
        }
        $auth->setEquityPersonaId($personaId);

        return redirect()->to(Product::homePath());
    }

    /**
     * Tras login/membresía: verificación → cambio clave → selector persona (Equity).
     */
    private function postLoginRedirect(MembershipAuth $auth)
    {
        $uid = $auth->usuarioId();
        if ($uid === null) {
            return redirect()->to('/login');
        }

        $user = model(UsuarioModel::class)->find($uid);
        if (is_array($user) && empty($user['email_verified_at']) && ! empty($user['email_verify_token'])) {
            return redirect()->to('/login')->with('error', 'Debes verificar tu correo antes de continuar. Revisa tu bandeja o el enlace de invitación.');
        }
        // Usuarios legacy (sin token y sin verified): marcar verificados al vuelo
        if (is_array($user) && empty($user['email_verified_at']) && empty($user['email_verify_token'])) {
            model(UsuarioModel::class)->update($uid, ['email_verified_at' => date('Y-m-d H:i:s')]);
        }

        if (is_array($user) && (int) ($user['must_change_password'] ?? 0) === 1) {
            return redirect()->to('/cambiar-clave');
        }

        if (! Product::isEquity()) {
            return redirect()->to(Product::homePath());
        }

        if (! $auth->check()) {
            return redirect()->to('/login/empresa');
        }

        $memb = $auth->membresia();
        if ($memb === null) {
            return redirect()->to('/login/empresa');
        }

        $personas = (new EquityOrg())->personasDeclarables((string) $uid, $memb['empresa_id']);
        if ($personas === []) {
            // Master/admin sin persona propia: entra sin selector
            $auth->clearEquityPersona();

            return redirect()->to(Product::homePath());
        }
        if (count($personas) === 1) {
            $auth->setEquityPersonaId($personas[0]['id']);

            return redirect()->to(Product::homePath());
        }
        // Más de una (representados): elegir
        $current = $auth->equityPersonaId();
        $stillOk = false;
        foreach ($personas as $p) {
            if (($p['id'] ?? '') === $current) {
                $stillOk = true;
                break;
            }
        }
        if ($stillOk) {
            return redirect()->to(Product::homePath());
        }

        return redirect()->to('/login/persona');
    }

    public function switchEmpresa()
    {
        $auth = new MembershipAuth();
        if (! $auth->check()) {
            return redirect()->to('/login');
        }

        $membresias = model(MembresiaModel::class)->forUsuario((string) $auth->usuarioId());
        if (count($membresias) < 2) {
            return redirect()->to(Product::homePath());
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

        $auth->clearEquityPersona();

        return $this->postLoginRedirect($auth);
    }

    public function logout()
    {
        (new MembershipAuth())->logout();

        return redirect()->to('/login');
    }
}
