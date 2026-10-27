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

    public static function isHashed(string $stored): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
    }

    /** Upgrades a legacy plain-text password to a hash on first successful login. */
    public function rehashIfPlain(array $user, string $password): void
    {
        if (! self::isHashed((string) $user['password'])) {
            $this->update($user['id'], ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }
    }

    public static function passwordMatches(string $input, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }
        return self::isHashed($stored)
            ? password_verify($input, $stored)
            : hash_equals($stored, $input);
    }

    /** Session/token payload shared by cookie login and mobile login. */
    public static function sessionPayload(array $user): array
    {
        $payload = [
            'role'     => $user['type'] === 'admin' ? 'admin' : 'employee',
            'uid'      => $user['id'],
            'name'     => $user['name'],
            'username' => $user['username'],
        ];
        if ($payload['role'] === 'employee') {
            $payload['id'] = $user['employee_id'];
        }
        return $payload;
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
