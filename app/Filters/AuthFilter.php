<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\MembershipAuth;
use App\Libraries\Product;
use App\Models\UsuarioModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Session;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new MembershipAuth();
        $path = '/' . ltrim($request->getUri()->getPath(), '/');

        // Idle timeout (default 10 min): cierra sesión si no hubo actividad.
        if ($auth->usuarioId() !== null && $this->idleExpired()) {
            $auth->logout();

            return redirect()->to('/login')->with('error', 'Sesión cerrada por inactividad (10 minutos).');
        }
        if ($auth->usuarioId() !== null) {
            session()->set('auth_last_activity', time());
        }

        // Cambio de clave: basta con usuario en sesión (aún sin membresía completa).
        if ($path === '/cambiar-clave' || str_starts_with($path, '/cambiar-clave/')) {
            if ($auth->usuarioId() === null) {
                return redirect()->to('/login');
            }

            return null;
        }

        // Selector de persona representada (Equity).
        if ($path === '/login/persona' || str_starts_with($path, '/login/persona/')) {
            if (! $auth->check()) {
                return redirect()->to('/login');
            }

            return null;
        }

        if (! $auth->check()) {
            if ($auth->usuarioId() !== null && $auth->membresiaId() === null) {
                return redirect()->to('/login/empresa');
            }

            return redirect()->to('/login');
        }

        $uid  = $auth->usuarioId();
        $user = $uid !== null ? model(UsuarioModel::class)->find($uid) : null;
        if (is_array($user) && (int) ($user['must_change_password'] ?? 0) === 1) {
            return redirect()->to('/cambiar-clave');
        }

        // Host Equity: no exponer menús/flujos de Repositoria.
        if (! Product::isPathAllowed($path)) {
            return redirect()->to(Product::homePath())
                ->with('error', 'Esa sección pertenece a Repositoria. En Equity usa Activos, Pasivos, Ingresos y Respaldos.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /** True si la última actividad supera session.expiration (mín. 60s, default 600). */
    private function idleExpired(): bool
    {
        $ttl = (int) (config(Session::class)->expiration ?? 600);
        if ($ttl <= 0) {
            $ttl = 600;
        }
        $ttl  = max(60, $ttl);
        $last = (int) session()->get('auth_last_activity');
        if ($last <= 0) {
            return false;
        }

        return (time() - $last) > $ttl;
    }
}
