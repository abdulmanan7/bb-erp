<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Settings extends BaseApiController
{
    /**
     * Partial update — only provided fields are changed.
     * adminUser/adminPass update the logged-in admin's users row.
     */
    public function update(): ResponseInterface
    {
        $b = $this->body();
        $map = [
            'companyName'   => 'company_name',
            'tagline'       => 'tagline',
            'address'       => 'address',
            'phone'         => 'phone',
            'email'         => 'email',
            'logo'          => 'logo',
            'primary'       => 'primary_color',
            'secondary'     => 'secondary_color',
            'currency'      => 'currency',
            'invoiceFooter' => 'invoice_footer',
        ];
        $fields = [];
        foreach ($map as $key => $col) {
            if (array_key_exists($key, $b)) {
                $fields[$col] = is_string($b[$key]) ? trim($b[$key]) : $b[$key];
            }
        }
        if (isset($b['taxRate'])) {
            $fields['tax_rate'] = (float) $b['taxRate'];
        }
        if ($fields !== []) {
            (new SettingModel())->update(1, $fields);
        }

        // Admin credentials → current admin's users row
        $users = new UserModel();
        $uid = $this->user()['uid'] ?? '';
        $userFields = [];
        if (! empty($b['adminUser'])) {
            $username = trim((string) $b['adminUser']);
            if ($users->usernameTaken($username, $uid)) {
                return $this->fail('Username already taken.');
            }
            $userFields['username'] = $username;
        }
        if (! empty($b['adminPass'])) {
            $userFields['password'] = password_hash((string) $b['adminPass'], PASSWORD_DEFAULT);
        }
        if ($userFields !== [] && $uid !== '') {
            $users->update($uid, $userFields);
            if (isset($userFields['username'])) {
                session()->set('erp_user.username', $userFields['username']);
            }
        }

        return $this->json(['ok' => true]);
    }
}
