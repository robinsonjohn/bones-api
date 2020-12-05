<?php

namespace App\Controllers;

use Bayfront\Bones\Controller;

class Home extends Controller
{

    /**
     * Homepage.
     */

    public function index()
    {
        $this->response->setBody('<h1>Bones v' . BONES_VERSION . ' is successfully installed</h1>')->send();
    }

}