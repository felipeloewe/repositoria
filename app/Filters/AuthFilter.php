<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\MembershipAuth;
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

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
