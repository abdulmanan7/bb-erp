<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', static function (RouteCollection $routes) {
    // Session
    $routes->post('login', 'Api\Auth::login');
    $routes->post('logout', 'Api\Auth::logout');
    $routes->get('me', 'Api\Auth::me');

    // Aggregated app state for the SPA (any logged-in user)
    $routes->get('state', 'Api\State::index', ['filter' => 'auth']);

    // Account heads — admin only
    $routes->post('heads', 'Api\Heads::create', ['filter' => 'auth:admin']);
    $routes->put('heads/(:segment)', 'Api\Heads::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('heads/(:segment)', 'Api\Heads::delete/$1', ['filter' => 'auth:admin']);

    // Employees — admin only
    $routes->post('employees', 'Api\Employees::create', ['filter' => 'auth:admin']);
    $routes->put('employees/(:segment)', 'Api\Employees::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('employees/(:segment)', 'Api\Employees::delete/$1', ['filter' => 'auth:admin']);

    // Vouchers — employees can create, admin manages all
    $routes->get('vouchers/(:segment)/attachment', 'Api\Vouchers::attachment/$1', ['filter' => 'auth']);
    $routes->post('vouchers', 'Api\Vouchers::create', ['filter' => 'auth']);
    $routes->post('vouchers/(:segment)/payments', 'Api\Vouchers::addPayment/$1', ['filter' => 'auth']);
    $routes->delete('vouchers/payments/(:segment)', 'Api\Vouchers::deletePayment/$1', ['filter' => 'auth:admin']);
    $routes->put('vouchers/(:segment)', 'Api\Vouchers::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('vouchers/(:segment)', 'Api\Vouchers::delete/$1', ['filter' => 'auth:admin']);

    // Spending ledger — staff record advances and project spending, admin manages all
    $routes->get('spendings/(:segment)/attachment', 'Api\Spendings::attachment/$1', ['filter' => 'auth']);
    $routes->post('spendings', 'Api\Spendings::create', ['filter' => 'auth']);
    $routes->put('spendings/(:segment)', 'Api\Spendings::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('spendings/(:segment)', 'Api\Spendings::delete/$1', ['filter' => 'auth:admin']);

    // Invoices — admin only
    $routes->post('invoices', 'Api\Invoices::create', ['filter' => 'auth:admin']);
    $routes->put('invoices/(:segment)', 'Api\Invoices::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('invoices/(:segment)', 'Api\Invoices::delete/$1', ['filter' => 'auth:admin']);

    // Salary slips — admin only
    $routes->post('salary/generate', 'Api\Salary::generate', ['filter' => 'auth:admin']);
    $routes->post('salary/(:segment)/paid', 'Api\Salary::markPaid/$1', ['filter' => 'auth:admin']);
    $routes->post('salary', 'Api\Salary::create', ['filter' => 'auth:admin']);
    $routes->put('salary/(:segment)', 'Api\Salary::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('salary/(:segment)', 'Api\Salary::delete/$1', ['filter' => 'auth:admin']);

    // Projects & assignments — admin only
    $routes->get('projects', 'Api\Projects::index', ['filter' => 'auth:admin']);
    $routes->post('projects', 'Api\Projects::create', ['filter' => 'auth:admin']);
    $routes->put('projects/(:segment)', 'Api\Projects::update/$1', ['filter' => 'auth:admin']);
    $routes->put('projects/(:segment)/assignments', 'Api\Projects::assign/$1', ['filter' => 'auth:admin']);

    // Attendance monitoring — admin only
    $routes->get('attendance/roster', 'Api\AttendanceMonitor::roster', ['filter' => 'auth:admin']);
    $routes->get('attendance/sessions', 'Api\AttendanceMonitor::sessions', ['filter' => 'auth:admin']);
    $routes->get('attendance/sessions/(:segment)/track', 'Api\AttendanceMonitor::track/$1', ['filter' => 'auth:admin']);
    $routes->get('attendance/report', 'Api\AttendanceReports::employee', ['filter' => 'auth:admin']);
    $routes->get('attendance/report/summary', 'Api\AttendanceReports::summary', ['filter' => 'auth:admin']);

    // Leave requests & announcements — admin manages, employees read via the mobile API
    $routes->get('leaves', 'Api\Leaves::index', ['filter' => 'auth:admin']);
    $routes->post('leaves/(:segment)/review', 'Api\Leaves::review/$1', ['filter' => 'auth:admin']);
    $routes->get('announcements', 'Api\Announcements::index', ['filter' => 'auth:admin']);
    $routes->post('announcements', 'Api\Announcements::create', ['filter' => 'auth:admin']);
    $routes->put('announcements/(:segment)', 'Api\Announcements::update/$1', ['filter' => 'auth:admin']);
    $routes->post('announcements/(:segment)/publish', 'Api\Announcements::publish/$1', ['filter' => 'auth:admin']);

    // Mobile app — employee bearer tokens (login/refresh/logout are public)
    $routes->group('mobile', static function (RouteCollection $routes) {
        $routes->post('login', 'Api\Mobile\Session::login');
        $routes->post('refresh', 'Api\Mobile\Session::refresh');
        $routes->post('logout', 'Api\Mobile\Session::logout');

        $routes->get('config', 'Api\Mobile\Device::config', ['filter' => 'auth:employee']);
        $routes->post('push-tokens', 'Api\Mobile\Device::registerPushToken', ['filter' => 'auth:employee']);
        $routes->delete('push-tokens', 'Api\Mobile\Device::removePushToken', ['filter' => 'auth:employee']);

        $routes->get('projects', 'Api\Mobile\Attendance::projects', ['filter' => 'auth:employee']);
        $routes->get('attendance/status', 'Api\Mobile\Attendance::status', ['filter' => 'auth:employee']);
        $routes->post('attendance/check-in', 'Api\Mobile\Attendance::checkIn', ['filter' => 'auth:employee']);
        $routes->post('attendance/check-out', 'Api\Mobile\Attendance::checkOut', ['filter' => 'auth:employee']);
        $routes->post('attendance/locations', 'Api\Mobile\Attendance::locations', ['filter' => 'auth:employee']);
        $routes->get('attendance/history', 'Api\Mobile\Attendance::history', ['filter' => 'auth:employee']);
        $routes->get('attendance/history/(:segment)', 'Api\Mobile\Attendance::historyDetail/$1', ['filter' => 'auth:employee']);

        $routes->get('leave-types', 'Api\Mobile\Leaves::types', ['filter' => 'auth:employee']);
        $routes->get('leaves', 'Api\Mobile\Leaves::index', ['filter' => 'auth:employee']);
        $routes->post('leaves', 'Api\Mobile\Leaves::create', ['filter' => 'auth:employee']);
        $routes->get('leaves/(:segment)', 'Api\Mobile\Leaves::show/$1', ['filter' => 'auth:employee']);
        $routes->post('leaves/(:segment)/cancel', 'Api\Mobile\Leaves::cancel/$1', ['filter' => 'auth:employee']);

        $routes->get('announcements', 'Api\Mobile\Announcements::index', ['filter' => 'auth:employee']);
        $routes->get('announcements/(:segment)', 'Api\Mobile\Announcements::show/$1', ['filter' => 'auth:employee']);

        $routes->get('notifications', 'Api\Mobile\Notifications::index', ['filter' => 'auth:employee']);
        $routes->get('notifications/(:segment)', 'Api\Mobile\Notifications::show/$1', ['filter' => 'auth:employee']);
    });

    // Settings / backup — admin only
    $routes->put('settings', 'Api\Settings::update', ['filter' => 'auth:admin']);
    $routes->get('backup', 'Api\Backup::export', ['filter' => 'auth:admin']);
    $routes->post('backup/import', 'Api\Backup::import', ['filter' => 'auth:admin']);
    $routes->post('reset', 'Api\Backup::reset', ['filter' => 'auth:admin']);
});

// SPA page URLs (/vouchers, /reports, …) — serve the shell; assets are real
// files so Apache/nginx never routes them here. Kept after the api group.
$routes->get('(:segment)', 'Home::index');
