<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Usage: ['filter' => 'auth']        — any logged-in user
 *        ['filter' => 'auth:admin']  — admin only
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('erp_user');
        if (! $user) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Not logged in']);
        }
        if ($arguments !== null && in_array('admin', $arguments, true) && $user['role'] !== 'admin') {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Admin access required']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
