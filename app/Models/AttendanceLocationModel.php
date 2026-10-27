<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceLocationModel extends Model
{
    protected $table = 'attendance_location_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'event_id', 'session_id', 'employee_id', 'project_id', 'latitude', 'longitude',
        'accuracy', 'distance_m', 'inside_boundary', 'is_mocked', 'captured_at', 'received_at', 'is_delayed',
    ];

    public function existingEventIds(string $employeeId, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }
        return array_column(
            $this->select('event_id')->where('employee_id', $employeeId)->whereIn('event_id', $eventIds)->findAll(),
            'event_id'
        );
    }

    public function forSession(string $sessionId): array
    {
        return $this->where('session_id', $sessionId)->orderBy('captured_at', 'ASC')->findAll();
    }
}
