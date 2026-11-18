<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\MembershipAuth;
use App\Libraries\Product;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new MembershipAuth();

        if (! $auth->check()) {
            if ($auth->usuarioId() !== null && $auth->membresiaId() === null) {
                return redirect()->to('/login/empresa');
            }

            return redirect()->to('/login');
        }

        // Host Equity: no exponer menús/flujos de Repositoria.
        $path = '/' . ltrim($request->getUri()->getPath(), '/');
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
}
