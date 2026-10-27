<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Model;

class DevicePushTokenModel extends Model
{
    protected $table = 'device_push_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'user_id', 'employee_id', 'platform', 'device_id', 'device_info', 'token', 'last_seen_at', 'created_at',
    ];

    /** Registers or re-binds a push token (a token that moves to another account is handed over). */
    public function register(array $user, array $data): void
    {
        $now = Clock::now();
        $row = [
            'user_id'      => $user['uid'],
            'employee_id'  => $user['id'],
            'platform'     => $data['platform'],
            'device_id'    => $data['deviceId'],
            'device_info'  => $data['deviceInfo'],
            'last_seen_at' => $now,
        ];
        $existing = $this->where('token', $data['token'])->first();
        if ($existing) {
            $this->update($existing['id'], $row);
            return;
        }
        $this->insert($row + ['id' => bin2hex(random_bytes(16)), 'token' => $data['token'], 'created_at' => $now]);
    }

    public function removeForDevice(string $userId, string $deviceId): void
    {
        if ($deviceId !== '') {
            $this->where('user_id', $userId)->where('device_id', $deviceId)->delete();
        }
    }

    public function removeToken(string $token, string $userId): void
    {
        $this->where(['token' => $token, 'user_id' => $userId])->delete();
    }

    /** @return array<string, list<array>> tokens keyed by employee id */
    public function groupedByEmployees(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $grouped = [];
        foreach ($this->whereIn('employee_id', $employeeIds)->findAll() as $row) {
            $grouped[$row['employee_id']][] = $row;
        }
        return $grouped;
    }
}
