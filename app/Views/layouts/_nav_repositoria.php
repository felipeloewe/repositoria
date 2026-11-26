<?php
/** @var array<string, mixed> $memb */
$nav = static fn (string $key): bool => \App\Libraries\PerfilAcl::canMenu($memb, $key);
$showAdminSection = $nav('menu.organizacion') || $nav('menu.departamentos') || $nav('menu.recursos') || $nav('menu.perfiles') || ! empty($memb['es_admin_central']);
$title = $title ?? '';
?>
<p class="nav-section">Principal</p>
<?php if ($nav('menu.inicio')): ?>
<a href="<?= site_url('dashboard') ?>" class="<?= $title === 'Inicio' ? 'active' : '' ?>" data-nav="inicio"><span class="nav-label">Inicio</span></a>
<?php endif; ?>
<?php if ($nav('menu.documentos')): ?>
<a href="<?= site_url('documentos') ?>" class="<?= in_array($title, ['Documentos', 'Tipos de documento', 'Archivados', 'Respaldo archivados'], true) ? 'active' : '' ?>" data-nav="docs"><span class="nav-label">Documentos</span></a>
<?php endif; ?>
<?php if ($nav('menu.documentos_tipos')): ?>
    <a href="<?= site_url('documentos/tipos') ?>" class="nav-sub <?= $title === 'Tipos de documento' ? 'active' : '' ?>" data-nav="tipos"><span class="nav-label">Tipos</span></a>
<?php endif; ?>
<?php if ($nav('menu.documentos_archivados')): ?>
    <a href="<?= site_url('documentos/archivados') ?>" class="nav-sub <?= $title === 'Archivados' ? 'active' : '' ?>" data-nav="archivados"><span class="nav-label">Archivados</span></a>
<?php endif; ?>
<?php if ($nav('menu.documentos_respaldo')): ?>
    <a href="<?= site_url('documentos/archivados/respaldo') ?>" class="nav-sub <?= $title === 'Respaldo archivados' ? 'active' : '' ?>" data-nav="respaldo"><span class="nav-label">Respaldo</span></a>
<?php endif; ?>
<?php if ($nav('menu.documentos') && ! \App\Libraries\Roles::isMaster($memb['rol'])): ?>
    <a href="<?= site_url('documentos/archivado') ?>" class="nav-sub <?= $title === 'Archivado' ? 'active' : '' ?>" data-nav="cola-archivo"><span class="nav-label">Archivado</span></a>
    <a href="<?= site_url('documentos/en-baja') ?>" class="nav-sub <?= $title === 'En Baja' ? 'active' : '' ?>" data-nav="cola-baja"><span class="nav-label">En Baja</span></a>
<?php elseif ($nav('menu.documentos_archivados') || \App\Libraries\DocumentoSolicitudService::canValidateArchivo($memb)): ?>
    <a href="<?= site_url('documentos/archivado') ?>" class="nav-sub <?= $title === 'Archivado' ? 'active' : '' ?>" data-nav="cola-archivo"><span class="nav-label">Cola archivo</span></a>
    <a href="<?= site_url('documentos/en-baja') ?>" class="nav-sub <?= $title === 'En Baja' ? 'active' : '' ?>" data-nav="cola-baja"><span class="nav-label">Cola baja</span></a>
<?php endif; ?>
<?php if ($nav('menu.formularios')): ?>
    <a href="<?= site_url('formularios') ?>" class="<?= $title === 'Formulario' ? 'active' : '' ?>" data-nav="formularios"><span class="nav-label">Formulario</span></a>
<?php endif; ?>
<?php if ($nav('menu.contratos')): ?>
<a href="<?= site_url('contratos') ?>" class="<?= $title === 'Contratos' ? 'active' : '' ?>" data-nav="contratos"><span class="nav-label">Contratos</span></a>
<?php endif; ?>
<?php if ($nav('menu.socios')): ?>
    <a href="<?= site_url('socios') ?>" class="<?= in_array($title, ['Socios', 'Formularios socios'], true) ? 'active' : '' ?>" data-nav="socios"><span class="nav-label">Socios</span></a>
<?php endif; ?>

<p class="nav-section">Canales</p>
<?php if ($nav('menu.correo')): ?>
<a href="<?= site_url('correo') ?>" class="<?= $title === 'Correo' ? 'active' : '' ?>" data-nav="correo"><span class="nav-label">Correo</span></a>
<?php endif; ?>
<?php if ($nav('menu.flujos')): ?>
    <a href="<?= site_url('flujos') ?>" class="<?= $title === 'Flujos' ? 'active' : '' ?>" data-nav="flujos"><span class="nav-label">Flujos</span></a>
<?php endif; ?>

<?php if ($showAdminSection): ?>
    <p class="nav-section">Administración</p>
<?php endif; ?>
<?php if ($nav('menu.organizacion')): ?>
    <a href="<?= site_url('organizacion') ?>" class="<?= $title === 'Organización' ? 'active' : '' ?>" data-nav="org"><span class="nav-label">Organización</span></a>
<?php endif; ?>
<?php if ($nav('menu.departamentos')): ?>
    <a href="<?= site_url('departamentos') ?>" class="<?= in_array($title, ['Departamentos', 'Actividades', 'Temas de Interés'], true) ? 'active' : '' ?>" data-nav="deptos"><span class="nav-label">Departamentos</span></a>
<?php endif; ?>
<?php if ($nav('menu.recursos')): ?>
    <a href="<?= site_url('recursos') ?>" class="<?= in_array($title, ['Recursos', 'Recursos · Contratos'], true) ? 'active' : '' ?>" data-nav="recursos"><span class="nav-label">Recursos</span></a>
<?php endif; ?>
<?php if ($nav('menu.perfiles')): ?>
    <a href="<?= site_url('perfiles') ?>" class="<?= $title === 'Perfiles' ? 'active' : '' ?>" data-nav="perfiles"><span class="nav-label">Perfiles</span></a>
<?php endif; ?>
<?php if (! empty($memb['es_admin_central'])): ?>
    <a href="<?= site_url('admin/empresas') ?>" class="<?= $title === 'Admin central' ? 'active' : '' ?>" data-nav="admin"><span class="nav-label">Admin central</span></a>
<?php endif; ?>
<a href="<?= site_url('logout') ?>" class="nav-exit" data-nav="salir"><span class="nav-label">Salir</span></a>
