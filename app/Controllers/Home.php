<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;

class Home extends BaseController
{
    public function index()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to('/dashboard');
        }

        return redirect()->to('/login');
    }
}
