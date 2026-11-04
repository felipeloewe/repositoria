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

// Self-serve alta empresa FREE + admin Master
$routes->get('registro', 'Registro::index');
$routes->post('registro/empresa', 'Registro::storeEmpresa');
$routes->get('registro/admin', 'Registro::admin');
$routes->post('registro/admin', 'Registro::storeAdmin');
$routes->get('registro/cancelar', 'Registro::cancel');

// Fase 3 webhook (no session auth; optional shared secret)
$routes->post('api/webhooks/imap-mail', 'Webhook::imapMail');

// Formularios de socios — fill público (token + password; sin login)
$routes->get('f/(:segment)', 'FormularioPublico::gate/$1');
$routes->post('f/(:segment)/unlock', 'FormularioPublico::unlock/$1');
$routes->post('f/(:segment)', 'FormularioPublico::submit/$1');

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('empresa/cambiar', 'Auth::switchEmpresa');
    $routes->post('empresa/cambiar', 'Auth::doSwitchEmpresa');

    $routes->get('organizacion', 'Organizacion::index');
    $routes->post('organizacion/departamento', 'Organizacion::storeDepartamento'); // legacy → redirige a /departamentos
    $routes->post('organizacion/sucursal', 'Organizacion::storeSucursal');
    $routes->post('organizacion/actividad', 'Organizacion::storeActividad'); // legacy → /departamentos/actividades
    $routes->post('organizacion/tema', 'Organizacion::storeTema'); // legacy → /departamentos/temas
    $routes->post('organizacion/membresia', 'Organizacion::storeMembresia');
    $routes->post('organizacion/membresia/socio', 'Organizacion::updateMembresiaSocio');
    $routes->post('organizacion/recursos-sistema/toggle', 'Organizacion::toggleRecursoSistema');

    // Administración → Departamentos (menú exclusivo + pestañas)
    $routes->get('departamentos', 'Departamentos::index');
    $routes->post('departamentos/toggle', 'Departamentos::toggle');
    $routes->post('departamentos/custom', 'Departamentos::storeCustom');
    $routes->get('departamentos/actividades', 'Departamentos::actividades');
    $routes->post('departamentos/actividades', 'Departamentos::storeActividad');
    $routes->get('departamentos/temas', 'Departamentos::temas');
    $routes->post('departamentos/temas', 'Departamentos::storeTema');

    // Menú Formulario (empresa) — plantillas → PDF ambito=general + shares públicos
    $routes->get('formularios', 'EmpresaFormularios::index');
    $routes->get('formularios/nueva', 'EmpresaFormularios::createForm');
    $routes->post('formularios', 'EmpresaFormularios::store');
    $routes->get('formularios/instancias/(:segment)', 'EmpresaFormularios::showInstancia/$1');
    $routes->get('formularios/(:segment)', 'EmpresaFormularios::show/$1');
    $routes->post('formularios/(:segment)', 'EmpresaFormularios::update/$1');
    $routes->post('formularios/(:segment)/toggle', 'EmpresaFormularios::toggle/$1');
    $routes->post('formularios/(:segment)/campos', 'EmpresaFormularios::storeCampo/$1');
    $routes->post('formularios/(:segment)/campos/(:segment)/delete', 'EmpresaFormularios::deleteCampo/$1/$2');
    $routes->get('formularios/(:segment)/rellenar', 'EmpresaFormularios::fillForm/$1');
    $routes->post('formularios/(:segment)/rellenar', 'EmpresaFormularios::fillSubmit/$1');
    $routes->post('formularios/(:segment)/compartir', 'EmpresaFormularios::share/$1');
    $routes->post('formularios/(:segment)/shares/(:segment)/revoke', 'EmpresaFormularios::revokeShare/$1/$2');

    // Menú Contratos (Principal) — clon inicial de Documentos (ambito=contrato)
    $routes->get('contratos', 'Contratos::index');
    $routes->post('contratos/upload', 'Contratos::upload');

    // Menú Socios — documentos exclusivos (ambito=socio) + formularios + contratos
    $routes->get('socios', 'DocumentosSocios::index');
    $routes->post('socios/upload', 'DocumentosSocios::upload');
    $routes->get('socios/formularios', 'SociosFormularios::index');
    $routes->get('socios/formularios/nueva', 'SociosFormularios::createForm');
    $routes->post('socios/formularios', 'SociosFormularios::store');
    $routes->get('socios/formularios/instancias/(:segment)', 'SociosFormularios::showInstancia/$1');
    $routes->get('socios/formularios/(:segment)', 'SociosFormularios::show/$1');
    $routes->post('socios/formularios/(:segment)', 'SociosFormularios::update/$1');
    $routes->post('socios/formularios/(:segment)/toggle', 'SociosFormularios::toggle/$1');
    $routes->post('socios/formularios/(:segment)/campos', 'SociosFormularios::storeCampo/$1');
    $routes->post('socios/formularios/(:segment)/campos/(:segment)/delete', 'SociosFormularios::deleteCampo/$1/$2');
    $routes->get('socios/formularios/(:segment)/rellenar', 'SociosFormularios::fillForm/$1');
    $routes->post('socios/formularios/(:segment)/rellenar', 'SociosFormularios::fillSubmit/$1');
    $routes->post('socios/formularios/(:segment)/compartir', 'SociosFormularios::share/$1');
    $routes->post('socios/formularios/(:segment)/shares/(:segment)/revoke', 'SociosFormularios::revokeShare/$1/$2');
    $routes->get('socios/contratos', 'ContratosSocios::index');
    $routes->post('socios/contratos/upload', 'ContratosSocios::upload');

    // Admin central — empresas/planes + promoción filial (b) → empresa (a)
    $routes->get('admin/empresas', 'AdminCentral::empresas');
    $routes->post('admin/empresas/asignar-plan', 'AdminCentral::assignPlan');
    $routes->post('admin/empresas/crear', 'AdminCentral::crearEmpresa');
    $routes->get('admin/promociones', 'AdminCentral::index');
    $routes->post('admin/promociones/promover', 'AdminCentral::promover');

    $routes->get('documentos', 'Documentos::index');
    $routes->post('documentos/seccion', 'Documentos::storeSeccion');
    $routes->post('documentos/upload', 'Documentos::upload');
    $routes->post('documentos/authorize', 'Documentos::authorize');
    $routes->get('documentos/tipos', 'TiposDocumento::index');
    $routes->post('documentos/tipos', 'TiposDocumento::store');
    $routes->post('documentos/tipos/(:segment)/toggle', 'TiposDocumento::toggle/$1');
    $routes->get('documentos/archivados', 'DocumentosArchivados::index');
    $routes->get('documentos/archivados/respaldo', 'DocumentosArchivados::respaldo');
    $routes->post('documentos/archivados/respaldo', 'DocumentosArchivados::generarRespaldo');
    $routes->get('documentos/archivados/respaldo/(:segment)/download', 'DocumentosArchivados::downloadRespaldo/$1');
    $routes->get('documentos/(:segment)', 'Documentos::show/$1');
    $routes->get('documentos/(:segment)/download', 'Documentos::download/$1');
    $routes->get('documentos/(:segment)/versiones/(:segment)/download', 'Documentos::downloadVersion/$1/$2');
    $routes->post('documentos/(:segment)/estado', 'Documentos::transition/$1');
    $routes->post('documentos/(:segment)/version', 'Documentos::newVersion/$1');
    $routes->post('documentos/(:segment)/vencimiento', 'Documentos::setVencimiento/$1');
    $routes->post('documentos/(:segment)/cabecera', 'Documentos::setCabecera/$1');
    $routes->post('documentos/(:segment)/termino', 'Documentos::setTermino/$1');
    $routes->post('documentos/(:segment)/cambio', 'Documentos::addCambio/$1');
    $routes->post('documentos/(:segment)/renovar', 'Documentos::renovar/$1');
    $routes->post('documentos/(:segment)/archivar', 'Documentos::archivar/$1');
    $routes->post('documentos/(:segment)/delete', 'Documentos::delete/$1');

    // Fase 3 — Correo
    $routes->get('correo', 'Correo::index');
    $routes->get('correo/configuracion', 'Correo::configuracion');
    $routes->post('correo/configuracion', 'Correo::linkMailbox');
    $routes->post('correo/preferencia', 'Correo::savePreferencia');
    $routes->post('correo/sincronizar', 'Correo::syncAccount');
    $routes->post('correo/guardar-adjunto', 'Correo::saveAttachment');
    $routes->post('correo/guardar-certificado', 'Correo::saveCertificate');
    $routes->get('correo/(:segment)', 'Correo::thread/$1');

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
