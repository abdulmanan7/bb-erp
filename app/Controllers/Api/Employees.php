<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Employees extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Name required.');
        }
        if ($err = $this->validateLogin($b, null)) {
            return $this->fail($err);
        }

        $m = new EmployeeModel();
        $id = $this->newId();
        $m->insert([
            'id'          => $id,
            'name'        => $name,
            'phone'       => trim((string) ($b['phone'] ?? '')),
            'designation' => trim((string) ($b['designation'] ?? '')),
            'created_at'  => $this->now(),
        ]);
        $m->setAssignedHeads($id, (array) ($b['assignedHeads'] ?? []));
        $this->syncUser($id, $name, $b);
        return $this->json(['employee' => $m->map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new EmployeeModel();
        $emp = $m->find($id);
        if (! $emp) {
            return $this->fail('Employee not found.', 404);
        }
        $b = $this->body();
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Name required.');
        }
        $users = new UserModel();
        $existing = $users->findByEmployee($id);
        if ($err = $this->validateLogin($b, $existing)) {
            return $this->fail($err);
        }

        $m->update($id, [
            'name'        => $name,
            'phone'       => trim((string) ($b['phone'] ?? '')),
            'designation' => trim((string) ($b['designation'] ?? '')),
        ]);
        $m->setAssignedHeads($id, (array) ($b['assignedHeads'] ?? []));
        $this->syncUser($id, $name, $b);
        return $this->json(['employee' => $m->map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new EmployeeModel();
        if (! $m->find($id)) {
            return $this->fail('Employee not found.', 404);
        }
        $m->delete($id);
        db_connect()->table('employee_heads')->where('employee_id', $id)->delete();
        (new UserModel())->where('employee_id', $id)->delete();
        return $this->json(['ok' => true]);
    }

    /**
     * Returns an error string, or null when the login fields are valid.
     * $existingUser: the employee's users row if an account already exists.
     */
    private function validateLogin(array $b, ?array $existingUser): ?string
    {
        if (empty($b['loginEnabled'])) {
            return null;
        }
        $username = trim((string) ($b['username'] ?? ''));
        if ($username === '') {
            return 'Username required when login is enabled.';
        }
        if (! $existingUser && trim((string) ($b['password'] ?? '')) === '') {
            return 'Password required when login is enabled.';
        }
        if ((new UserModel())->usernameTaken($username, $existingUser['id'] ?? '')) {
            return 'Username already taken.';
        }
        return null;
    }

    /**
     * Creates/updates/removes the staff user account tied to an employee.
     */
    private function syncUser(string $employeeId, string $name, array $b): void
    {
        $users = new UserModel();
        $existing = $users->findByEmployee($employeeId);
        $enabled = ! empty($b['loginEnabled']);

        if (! $enabled) {
            if ($existing) {
                $users->delete($existing['id']);
            }
            return;
        }

        $username = trim((string) ($b['username'] ?? ''));
        $data = ['name' => $name, 'username' => $username, 'type' => 'staff'];
        $password = (string) ($b['password'] ?? '');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($existing) {
            $users->update($existing['id'], $data);
        } else {
            $users->insert($data + [
                'id'          => $this->newId(),
                'employee_id' => $employeeId,
                'created_at'  => $this->now(),
            ]);
        }
    }
}
