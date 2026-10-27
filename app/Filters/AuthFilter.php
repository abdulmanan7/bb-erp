<?php

namespace App\Filters;

use App\Libraries\ApiUser;
use App\Models\MobileSessionModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Usage: ['filter' => 'auth']           — any logged-in user (cookie session or mobile bearer token)
 *        ['filter' => 'auth:admin']     — admin only
 *        ['filter' => 'auth:employee']  — employee accounts only (mobile app)
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return service('response')->setStatusCode(401)->setJSON(['error' => 'Not logged in']);
        }
        if ($arguments !== null && in_array('admin', $arguments, true) && $user['role'] !== 'admin') {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Admin access required']);
        }
        if ($arguments !== null && in_array('employee', $arguments, true) && $user['role'] !== 'employee') {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Employee access required']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }

    private function resolveUser(RequestInterface $request): ?array
    {
        $header = $request->getHeaderLine('Authorization');
        if (! str_starts_with($header, 'Bearer ')) {
            return session()->get('erp_user');
        }

        $user = (new MobileSessionModel())->userForAccessToken(substr($header, 7));
        if ($user) {
            ApiUser::set($user);
        }
        return $user;
    }
}
