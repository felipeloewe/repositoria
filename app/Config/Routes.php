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

// Fase 3 webhook (no session auth; optional shared secret)
$routes->post('api/webhooks/imap-mail', 'Webhook::imapMail');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('empresa/cambiar', 'Auth::switchEmpresa');
    $routes->post('empresa/cambiar', 'Auth::doSwitchEmpresa');

    $routes->get('organizacion', 'Organizacion::index');
    $routes->post('organizacion/departamento', 'Organizacion::storeDepartamento');
    $routes->post('organizacion/sucursal', 'Organizacion::storeSucursal');
    $routes->post('organizacion/actividad', 'Organizacion::storeActividad');
    $routes->post('organizacion/tema', 'Organizacion::storeTema');

    $routes->get('documentos', 'Documentos::index');
    $routes->post('documentos/seccion', 'Documentos::storeSeccion');
    $routes->post('documentos/upload', 'Documentos::upload');
    $routes->post('documentos/authorize', 'Documentos::authorize');
    $routes->get('documentos/(:segment)/download', 'Documentos::download/$1');
    $routes->post('documentos/(:segment)/delete', 'Documentos::delete/$1');

    // Fase 3 — Correo
    $routes->get('correo', 'Correo::index');
    $routes->get('correo/configuracion', 'Correo::configuracion');
    $routes->post('correo/configuracion', 'Correo::linkMailbox');
    $routes->get('correo/(:segment)', 'Correo::thread/$1');
    $routes->post('correo/guardar-adjunto', 'Correo::saveAttachment');
    $routes->post('correo/guardar-certificado', 'Correo::saveCertificate');

    // Fase 4 — Flujos
    $routes->get('flujos', 'Flujos::index');
    $routes->get('flujos/fiscalizacion', 'Fiscalizacion::index');
    $routes->get('flujos/fiscalizacion/nueva', 'Fiscalizacion::createForm');
    $routes->post('flujos/fiscalizacion', 'Fiscalizacion::store');
    $routes->get('flujos/fiscalizacion/(:segment)', 'Fiscalizacion::show/$1');
    $routes->post('flujos/fiscalizacion/(:segment)/submit', 'Fiscalizacion::submit/$1');
    $routes->post('flujos/fiscalizacion/(:segment)/review', 'Fiscalizacion::review/$1');
    $routes->post('flujos/fiscalizacion/(:segment)/send', 'Fiscalizacion::send/$1');

    $routes->get('flujos/canales', 'Canales::index');
    $routes->get('flujos/canales/nuevo', 'Canales::createForm');
    $routes->post('flujos/canales', 'Canales::store');
    $routes->get('flujos/canales/(:segment)', 'Canales::show/$1');
    $routes->post('flujos/canales/(:segment)/revoke', 'Canales::revoke/$1');
    $routes->get('flujos/canales/(:segment)/download/(:segment)', 'Canales::download/$1/$2');
});
