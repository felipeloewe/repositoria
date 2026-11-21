<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\MembershipAuth;
use App\Libraries\Product;

class Home extends BaseController
{
    public function index()
    {
        $auth = new MembershipAuth();
        if ($auth->check()) {
            return redirect()->to(Product::homePath());
        }

        return redirect()->to('/login');
    }
}
