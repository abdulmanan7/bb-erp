<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    /**
     * Serves the SPA shell. app.html is a static file in public/, so
     * hosting on a sub-folder works without knowing the base URL.
     */
    public function index(): ResponseInterface
    {
        return $this->response
            ->setContentType('text/html')
            ->setBody(file_get_contents(FCPATH . 'app.html'));
    }
}
