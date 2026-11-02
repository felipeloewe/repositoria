<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EmpresaOnboarding;
use App\Libraries\MembershipAuth;
use RuntimeException;
use Throwable;

/**
 * Self-serve alta: paso 1 empresa → paso 2 admin Master (tipo b FREE).
 */
class Registro extends BaseController
{
    private const DRAFT_KEY = 'registro_empresa_draft';

    public function index()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        return view('auth/registro_empresa', [
            'title'   => 'Crear empresa',
            'error'   => session()->getFlashdata('error'),
            'success' => session()->getFlashdata('success'),
            'old'     => session()->getFlashdata('old') ?? [],
        ]);
    }

    public function storeEmpresa()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        $nombre = (string) $this->request->getPost('nombre');
        $alias  = (string) ($this->request->getPost('alias') ?: $this->request->getPost('slug'));
        $rut    = (string) $this->request->getPost('rut');

        try {
            $draft = (new EmpresaOnboarding())->validateEmpresaDraft($nombre, $alias, $rut);
        } catch (RuntimeException $e) {
            return redirect()->to('/registro')->with('error', $e->getMessage())->with('old', [
                'nombre' => $nombre,
                'alias'  => $alias,
                'rut'    => $rut,
            ]);
        }

        session()->set(self::DRAFT_KEY, $draft);

        return redirect()->to('/registro/admin');
    }

    public function admin()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        $draft = session()->get(self::DRAFT_KEY);
        if (! is_array($draft) || empty($draft['nombre'])) {
            return redirect()->to('/registro')->with('error', 'Primero crea la empresa.');
        }

        return view('auth/registro_admin', [
            'title' => 'Administrador',
            'draft' => $draft,
            'error' => session()->getFlashdata('error'),
            'old'   => session()->getFlashdata('old') ?? [],
        ]);
    }

    public function storeAdmin()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        $draft = session()->get(self::DRAFT_KEY);
        if (! is_array($draft) || empty($draft['nombre'])) {
            return redirect()->to('/registro')->with('error', 'Primero crea la empresa.');
        }

        $adminNombre = (string) $this->request->getPost('nombre');
        $adminAlias  = (string) $this->request->getPost('admin_alias');
        $adminCargo  = (string) $this->request->getPost('admin_cargo');
        $adminRut    = (string) $this->request->getPost('admin_rut');
        $adminEmail  = (string) $this->request->getPost('email');
        $adminPass   = (string) $this->request->getPost('password');
        $adminPass2  = (string) $this->request->getPost('password_confirm');

        $oldAdmin = [
            'nombre'      => $adminNombre,
            'admin_alias' => $adminAlias,
            'admin_cargo' => $adminCargo,
            'admin_rut'   => $adminRut,
            'email'       => $adminEmail,
        ];

        if ($adminPass !== $adminPass2) {
            return redirect()->to('/registro/admin')->with('error', 'Las contraseñas no coinciden.')->with('old', $oldAdmin);
        }

        try {
            $result = (new EmpresaOnboarding())->createEmpresaWithAdmin([
                'nombre'         => (string) $draft['nombre'],
                'slug'           => (string) ($draft['slug'] ?? ''),
                'rut'            => $draft['rut'] ?? null,
                'tipo'           => 'b',
                'admin_nombre'   => $adminNombre,
                'admin_alias'    => $adminAlias,
                'admin_cargo'    => $adminCargo,
                'admin_rut'      => $adminRut,
                'admin_email'    => $adminEmail,
                'admin_password' => $adminPass,
            ]);
        } catch (RuntimeException $e) {
            return redirect()->to('/registro/admin')->with('error', $e->getMessage())->with('old', $oldAdmin);
        } catch (Throwable $e) {
            log_message('error', 'Registro storeAdmin: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/registro/admin')->with('error', 'No se pudo completar el registro. Intenta de nuevo.');
        }

        session()->remove(self::DRAFT_KEY);

        $login = $auth->establish($result['usuario_id'], $result['membresia_id']);
        if (! ($login['ok'] ?? false)) {
            return redirect()->to('/login')->with(
                'error',
                'Empresa creada, pero no se pudo iniciar sesión. Ingresa con tu correo.'
            );
        }

        return redirect()->to('/dashboard')->with(
            'success',
            'Empresa creada en plan FREE (3 usuarios / 10 GB). Ya eres Administrador Master.'
        );
    }

    public function cancel()
    {
        session()->remove(self::DRAFT_KEY);

        return redirect()->to('/registro');
    }
}
