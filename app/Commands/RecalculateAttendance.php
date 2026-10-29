<?php

namespace App\Commands;

use App\Libraries\AttendanceReport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RecalculateAttendance extends BaseCommand
{
    protected $group = 'Attendance';
    protected $name = 'attendance:recalculate';
    protected $description = 'Recalculates stored on-site seconds for every attendance session.';

    public function run(array $params)
    {
        $ids = array_column(db_connect()->table('attendance_sessions')->select('id')->get()->getResultArray(), 'id');
        foreach ($ids as $id) {
            AttendanceReport::refreshDuration($id);
        }
        CLI::write('Recalculated ' . count($ids) . ' session(s).', 'green');
    }
}
