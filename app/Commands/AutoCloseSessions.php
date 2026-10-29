<?php

namespace App\Commands;

use App\Libraries\AttendanceAutoClose;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AutoCloseSessions extends BaseCommand
{
    protected $group = 'Attendance';
    protected $name = 'attendance:autoclose';
    protected $description = 'Closes attendance sessions whose shift length has elapsed.';

    public function run(array $params)
    {
        CLI::write('Closed ' . AttendanceAutoClose::closeExpired() . ' session(s).', 'green');
    }
}
