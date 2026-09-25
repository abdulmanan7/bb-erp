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
    $routes->post('vouchers', 'Api\Vouchers::create', ['filter' => 'auth']);
    $routes->put('vouchers/(:segment)', 'Api\Vouchers::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('vouchers/(:segment)', 'Api\Vouchers::delete/$1', ['filter' => 'auth:admin']);

    // Invoices — admin only
    $routes->post('invoices', 'Api\Invoices::create', ['filter' => 'auth:admin']);
    $routes->put('invoices/(:segment)', 'Api\Invoices::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('invoices/(:segment)', 'Api\Invoices::delete/$1', ['filter' => 'auth:admin']);

    // Salary slips — admin only
    $routes->post('salary', 'Api\Salary::create', ['filter' => 'auth:admin']);
    $routes->put('salary/(:segment)', 'Api\Salary::update/$1', ['filter' => 'auth:admin']);
    $routes->delete('salary/(:segment)', 'Api\Salary::delete/$1', ['filter' => 'auth:admin']);

    // Settings / backup — admin only
    $routes->put('settings', 'Api\Settings::update', ['filter' => 'auth:admin']);
    $routes->get('backup', 'Api\Backup::export', ['filter' => 'auth:admin']);
    $routes->post('backup/import', 'Api\Backup::import', ['filter' => 'auth:admin']);
    $routes->post('reset', 'Api\Backup::reset', ['filter' => 'auth:admin']);
});
