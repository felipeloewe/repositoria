<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->get('login/empresa', 'Auth::selectEmpresa');
$routes->post('login/empresa', 'Auth::chooseEmpresa');
$routes->get('logout', 'Auth::logout');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('empresa/cambiar', 'Auth::switchEmpresa');
    $routes->post('empresa/cambiar', 'Auth::doSwitchEmpresa');

    $routes->get('organizacion', 'Organizacion::index');
    $routes->post('organizacion/departamento', 'Organizacion::storeDepartamento');
    $routes->post('organizacion/sucursal', 'Organizacion::storeSucursal');
    $routes->post('organizacion/actividad', 'Organizacion::storeActividad');
    $routes->post('organizacion/tema', 'Organizacion::storeTema');
});
