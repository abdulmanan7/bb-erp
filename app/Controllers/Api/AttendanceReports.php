<?php

namespace App\Controllers\Api;

use App\Libraries\AttendanceReport;
use App\Models\EmployeeModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Attendance;

class AttendanceReports extends BaseApiController
{
    /** Daily breakdown and monthly totals for one employee. */
    public function employee(): ResponseInterface
    {
        $employee = (new EmployeeModel())->find((string) $this->request->getGet('employeeId'));
        if (! $employee) {
            return $this->fail('Employee not found.', 404);
        }

        $range = $this->range();
        $days = AttendanceReport::days(AttendanceReport::sessions($range['from'], $range['to'], $employee['id']));
        return $this->json([
            'employee' => ['id' => $employee['id'], 'name' => $employee['name'], 'designation' => $employee['designation'], 'shiftHours' => (float) $employee['shift_hours']],
            'month'    => $range['month'],
            'timezone' => config(Attendance::class)->defaultTimezone,
            'days'     => $days,
            'summary'  => AttendanceReport::summary($days),
        ]);
    }

    /** Monthly totals for every employee, including those with no attendance (for salary sheets). */
    public function summary(): ResponseInterface
    {
        $range = $this->range();
        $byEmployee = [];
        foreach (AttendanceReport::sessions($range['from'], $range['to']) as $session) {
            $byEmployee[$session['employeeId']][] = $session;
        }

        $employees = (new EmployeeModel())->orderBy('name', 'ASC')->findAll();
        $rows = array_map(function ($employee) use ($byEmployee) {
            $days = AttendanceReport::days($byEmployee[$employee['id']] ?? []);
            return [
                'employeeId'  => $employee['id'],
                'name'        => $employee['name'],
                'designation' => $employee['designation'],
            ] + AttendanceReport::summary($days);
        }, $employees);

        return $this->json([
            'month'     => $range['month'],
            'timezone'  => config(Attendance::class)->defaultTimezone,
            'employees' => $rows,
        ]);
    }

    /** @return array{from: string, to: string, month: string} */
    private function range(): array
    {
        $month = (string) $this->request->getGet('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        return AttendanceReport::monthRange($month) + ['month' => $month];
    }
}
