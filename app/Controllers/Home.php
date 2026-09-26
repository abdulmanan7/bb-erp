<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    /**
     * Serves the SPA shell. app.html is a static file in public/, so
     * hosting on a sub-folder works without knowing the base URL.
     * Asset URLs get a ?v=filemtime suffix so deployments bust browser/LiteSpeed caches.
     */
    public function index(): ResponseInterface
    {
        $html = file_get_contents(FCPATH . 'app.html');
        $html = str_replace(
            ['{V_CSS}', '{V_JS}'],
            [filemtime(FCPATH . 'assets/css/style.css'), filemtime(FCPATH . 'assets/js/app.js')],
            $html
        );

        return $this->response
            ->setContentType('text/html')
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody($html);
    }
}
