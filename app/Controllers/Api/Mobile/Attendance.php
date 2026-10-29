<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Attendance as AttendanceService;
use App\Libraries\AttendanceAutoClose;
use App\Libraries\AttendanceException;
use App\Libraries\Clock;
use App\Models\AttendanceSessionModel;
use App\Models\ProjectAssignmentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Attendance extends BaseApiController
{
    public function status(): ResponseInterface
    {
        AttendanceAutoClose::closeExpired();
        $session = (new AttendanceSessionModel())->activeFor($this->user()['id']);
        $detailed = $session ? (new AttendanceSessionModel())->detailed($session['id']) : null;
        return $this->json([
            'session'    => $detailed ? AttendanceSessionModel::present($detailed, false) : null,
            'serverTime' => Clock::iso(Clock::now()),
        ]);
    }

    public function projects(): ResponseInterface
    {
        $projects = (new ProjectAssignmentModel())->activeProjectsFor($this->user()['id']);
        return $this->json(['projects' => array_map(fn ($p) => [
            'id'       => $p['id'],
            'name'     => $p['name'],
            'address'  => $p['address'],
            'timezone' => $p['timezone'],
        ], $projects)]);
    }

    public function checkIn(): ResponseInterface
    {
        return $this->sessionAction(fn (AttendanceService $service) => $service->checkIn($this->user(), $this->body()));
    }

    public function checkOut(): ResponseInterface
    {
        return $this->sessionAction(fn (AttendanceService $service) => $service->checkOut($this->user(), $this->body()));
    }

    public function locations(): ResponseInterface
    {
        try {
            return $this->json((new AttendanceService())->recordLocations($this->user(), $this->body()));
        } catch (AttendanceException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->errorCode);
        }
    }

    public function history(): ResponseInterface
    {
        $filters = [
            'employeeId' => $this->user()['id'],
            'projectId'  => (string) $this->request->getGet('projectId'),
            'from'       => $this->date($this->request->getGet('from')),
            'to'         => $this->date($this->request->getGet('to')),
        ];
        return $this->paginate(
            (new AttendanceSessionModel())->listQuery($filters),
            fn ($row) => AttendanceSessionModel::present($row, false)
        );
    }

    public function historyDetail(string $id): ResponseInterface
    {
        $row = (new AttendanceSessionModel())->detailed($id, ['employeeId' => $this->user()['id']]);
        return $row
            ? $this->json(['session' => AttendanceSessionModel::present($row, false)])
            : $this->fail('Attendance record not found.', 404);
    }

    private function sessionAction(callable $action): ResponseInterface
    {
        try {
            $session = $action(new AttendanceService());
        } catch (AttendanceException $e) {
            return $this->fail($e->getMessage(), $e->status, $e->errorCode);
        }
        $row = (new AttendanceSessionModel())->detailed($session['id']);
        return $this->json(['session' => AttendanceSessionModel::present($row, false)]);
    }
}
