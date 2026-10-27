<?php

namespace App\Controllers\Api;

use App\Libraries\Clock;
use App\Libraries\TrackingGaps;
use App\Models\AttendanceLocationModel;
use App\Models\AttendanceSessionModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Attendance;

class AttendanceMonitor extends BaseApiController
{
    public function sessions(): ResponseInterface
    {
        $filters = [
            'employeeId' => (string) $this->request->getGet('employeeId'),
            'projectId'  => (string) $this->request->getGet('projectId'),
            'status'     => (string) $this->request->getGet('status'),
            'from'       => $this->date($this->request->getGet('from')),
            'to'         => $this->date($this->request->getGet('to')),
        ];
        return $this->paginate(
            (new AttendanceSessionModel())->listQuery($filters),
            fn ($row) => AttendanceSessionModel::present($row, true)
        );
    }

    /** Route for one session: recorded points, out-of-boundary events and tracking interruptions. */
    public function track(string $id): ResponseInterface
    {
        $row = (new AttendanceSessionModel())->detailed($id);
        if (! $row) {
            return $this->fail('Attendance session not found.', 404);
        }

        $points = (new AttendanceLocationModel())->forSession($id);
        $end = $row['check_out_at'] ?? Clock::now();
        $gaps = TrackingGaps::between(
            $row['check_in_at'],
            array_column($points, 'captured_at'),
            $end,
            config(Attendance::class)->gapThresholdSeconds
        );
        $mapped = array_map(fn ($p) => $this->point($p), $points);

        return $this->json([
            'session'       => AttendanceSessionModel::present($row, true),
            'points'        => $mapped,
            'outOfBoundary' => array_values(array_filter($mapped, fn ($p) => ! $p['inside'])),
            'gaps'          => $gaps,
        ]);
    }

    private function point(array $p): array
    {
        return [
            'at'         => Clock::iso($p['captured_at']),
            'receivedAt' => Clock::iso($p['received_at']),
            'latitude'   => (float) $p['latitude'],
            'longitude'  => (float) $p['longitude'],
            'accuracy'   => (float) $p['accuracy'],
            'distanceM'  => (float) $p['distance_m'],
            'inside'     => (bool) $p['inside_boundary'],
            'mocked'     => (bool) $p['is_mocked'],
            'delayed'    => (bool) $p['is_delayed'],
        ];
    }
}
