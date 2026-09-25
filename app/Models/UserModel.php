<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'employee_id', 'name', 'username', 'password', 'type', 'created_at',
    ];

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    public function findByEmployee(string $employeeId): ?array
    {
        return $this->where('employee_id', $employeeId)->first();
    }

    public function usernameTaken(string $username, string $exceptId = ''): bool
    {
        $q = $this->where('username', $username);
        if ($exceptId !== '') {
            $q->where('id !=', $exceptId);
        }
        return $q->first() !== null;
    }

    /** Public shape — password never leaves the API layer. */
    public static function map(array $r): array
    {
        return [
            'id'         => $r['id'],
            'employeeId' => $r['employee_id'] ?? '',
            'name'       => $r['name'],
            'username'   => $r['username'],
            'type'       => $r['type'],
            'createdAt'  => $r['created_at'] ?? null,
        ];
    }

    /** Includes the password hash — used only for backup export. */
    public static function exportMap(array $r): array
    {
        return self::map($r) + ['password' => $r['password']];
    }
}
