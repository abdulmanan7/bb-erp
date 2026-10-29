<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Models\EmployeeModel;
use App\Models\MobileSessionModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Profile extends BaseApiController
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function show(): ResponseInterface
    {
        $user = $this->user();
        $employee = (new EmployeeModel())->find($user['id']);
        return $this->json(['profile' => [
            'id'          => $employee['id'],
            'name'        => $employee['name'],
            'username'    => $user['username'],
            'designation' => $employee['designation'] ?? '',
            'phone'       => $employee['phone'] ?? '',
        ]]);
    }

    /** Changing the password signs out every mobile session, including this device. */
    public function changePassword(): ResponseInterface
    {
        $b = $this->body();
        $users = new UserModel();
        $account = $users->find($this->user()['uid']);

        if (! UserModel::passwordMatches((string) ($b['currentPassword'] ?? ''), (string) $account['password'])) {
            return $this->fail('Your current password is incorrect.', 422, 'INVALID_PASSWORD');
        }
        $password = (string) ($b['newPassword'] ?? '');
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return $this->fail('The new password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.');
        }

        $users->update($account['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        (new MobileSessionModel())->revokeForUser($account['id']);
        return $this->json(['ok' => true]);
    }
}
