<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Repositoria') ?> · Repositoria</title>
    <link rel="icon" href="<?= base_url('assets/brand/mark.svg') ?>" type="image/svg+xml">
    <link rel="icon" href="<?= base_url('favicon.ico') ?>?v=20261104b" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('assets/brand/favicon-32.png') ?>?v=20261104b" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= base_url('assets/brand/apple-touch-icon.png') ?>?v=20261104b">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<?php if (! empty($memb)): ?>
<div class="shell" id="app-shell">
    <aside class="sidebar" id="app-sidebar">
        <div class="sidebar-stripe" aria-hidden="true">
            <span class="stripe-navy"></span>
            <span class="stripe-blue"></span>
            <span class="stripe-steel"></span>
            <span class="stripe-teal"></span>
        </div>
        <div class="sidebar-inner">
            <div class="sidebar-top">
                <div class="brand">
                    <img class="brand-mark" src="<?= base_url('assets/brand/mark-on-dark.svg') ?>" width="42" height="42" alt="">
                    <div class="brand-text">
                        <h1>Repositoria</h1>
                        <p>Gestor documental</p>
                    </div>
                </div>
                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-expanded="true" aria-controls="app-sidebar" aria-label="Contraer menú" title="Contraer menú">
                    <span class="sidebar-toggle-icon" aria-hidden="true"></span>
                </button>
            </div>
            <nav class="nav">
                <?php
                    $nav = static fn (string $key): bool => \App\Libraries\PerfilAcl::canMenu($memb, $key);
                    $showAdminSection = $nav('menu.organizacion') || $nav('menu.departamentos') || $nav('menu.recursos') || $nav('menu.perfiles') || ! empty($memb['es_admin_central']);
                ?>
                <p class="nav-section">Principal</p>
                <?php if ($nav('menu.inicio')): ?>
                <a href="<?= site_url('dashboard') ?>" class="<?= ($title ?? '') === 'Inicio' ? 'active' : '' ?>" data-nav="inicio"><span class="nav-label">Inicio</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.documentos')): ?>
                <a href="<?= site_url('documentos') ?>" class="<?= in_array(($title ?? ''), ['Documentos', 'Tipos de documento', 'Archivados', 'Respaldo archivados'], true) ? 'active' : '' ?>" data-nav="docs"><span class="nav-label">Documentos</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.documentos_tipos')): ?>
                    <a href="<?= site_url('documentos/tipos') ?>" class="nav-sub <?= ($title ?? '') === 'Tipos de documento' ? 'active' : '' ?>" data-nav="tipos"><span class="nav-label">Tipos</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.documentos_archivados')): ?>
                    <a href="<?= site_url('documentos/archivados') ?>" class="nav-sub <?= ($title ?? '') === 'Archivados' ? 'active' : '' ?>" data-nav="archivados"><span class="nav-label">Archivados</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.documentos_respaldo')): ?>
                    <a href="<?= site_url('documentos/archivados/respaldo') ?>" class="nav-sub <?= ($title ?? '') === 'Respaldo archivados' ? 'active' : '' ?>" data-nav="respaldo"><span class="nav-label">Respaldo</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.formularios')): ?>
                    <a href="<?= site_url('formularios') ?>" class="<?= ($title ?? '') === 'Formulario' ? 'active' : '' ?>" data-nav="formularios"><span class="nav-label">Formulario</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.contratos')): ?>
                <a href="<?= site_url('contratos') ?>" class="<?= ($title ?? '') === 'Contratos' ? 'active' : '' ?>" data-nav="contratos"><span class="nav-label">Contratos</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.socios')): ?>
                    <a href="<?= site_url('socios') ?>" class="<?= in_array(($title ?? ''), ['Socios', 'Formularios socios'], true) ? 'active' : '' ?>" data-nav="socios"><span class="nav-label">Socios</span></a>
                <?php endif; ?>


                <p class="nav-section">Canales</p>
                <?php if ($nav('menu.correo')): ?>
                <a href="<?= site_url('correo') ?>" class="<?= ($title ?? '') === 'Correo' ? 'active' : '' ?>" data-nav="correo"><span class="nav-label">Correo</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.flujos')): ?>
                    <a href="<?= site_url('flujos') ?>" class="<?= ($title ?? '') === 'Flujos' ? 'active' : '' ?>" data-nav="flujos"><span class="nav-label">Flujos</span></a>
                <?php endif; ?>

                <?php if ($showAdminSection): ?>
                    <p class="nav-section">Administración</p>
                <?php endif; ?>
                <?php if ($nav('menu.organizacion')): ?>
                    <a href="<?= site_url('organizacion') ?>" class="<?= ($title ?? '') === 'Organización' ? 'active' : '' ?>" data-nav="org"><span class="nav-label">Organización</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.departamentos')): ?>
                    <a href="<?= site_url('departamentos') ?>" class="<?= in_array(($title ?? ''), ['Departamentos', 'Actividades', 'Temas de Interés'], true) ? 'active' : '' ?>" data-nav="deptos"><span class="nav-label">Departamentos</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.recursos')): ?>
                    <a href="<?= site_url('recursos') ?>" class="<?= in_array(($title ?? ''), ['Recursos', 'Recursos · Contratos'], true) ? 'active' : '' ?>" data-nav="recursos"><span class="nav-label">Recursos</span></a>
                <?php endif; ?>
                <?php if ($nav('menu.perfiles')): ?>
                    <a href="<?= site_url('perfiles') ?>" class="<?= ($title ?? '') === 'Perfiles' ? 'active' : '' ?>" data-nav="perfiles"><span class="nav-label">Perfiles</span></a>
                <?php endif; ?>
                <?php if (! empty($memb['es_admin_central'])): ?>
                    <a href="<?= site_url('admin/empresas') ?>" class="<?= ($title ?? '') === 'Admin central' ? 'active' : '' ?>" data-nav="admin"><span class="nav-label">Admin central</span></a>
                <?php endif; ?>
                <a href="<?= site_url('logout') ?>" class="nav-exit" data-nav="salir"><span class="nav-label">Salir</span></a>
            </nav>
            <div class="sidebar-foot">
                MVP Fases 1–4<br>
                CI4 + MySQL
            </div>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <div class="who"><?= esc($memb['usuario_nombre'] ?? '') ?></div>
                <div class="meta">
                    <?= esc($memb['empresa_nombre'] ?? '') ?>
                    · <?= esc(\App\Libraries\Roles::label($memb['rol'])) ?>
                    <?php if (! empty($memb['es_socio'])): ?>
                        · Socio
                    <?php endif; ?>
                    <?php if (! empty($memb['sucursal_nombre'])): ?>
                        · <?= esc($memb['sucursal_nombre']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a class="btn btn-ghost btn-sm" href="<?= site_url('empresa/cambiar') ?>">Cambiar empresa</a>
            </div>
        </header>
        <div class="content">
            <?= $this->renderSection('content') ?>
        </div>
    </div>
</div>
<script>
(function () {
  var shell = document.getElementById('app-shell');
  var btn = document.getElementById('sidebar-toggle');
  if (!shell || !btn) return;
  var key = 'repositoria.sidebar.collapsed';
  function apply(collapsed) {
    shell.classList.toggle('is-collapsed', collapsed);
    var label = collapsed ? 'Expandir menú' : 'Contraer menú';
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }
  try {
    apply(localStorage.getItem(key) === '1');
  } catch (e) {
    apply(false);
  }
  btn.addEventListener('click', function () {
    var next = !shell.classList.contains('is-collapsed');
    apply(next);
    try { localStorage.setItem(key, next ? '1' : '0'); } catch (e) {}
  });
})();
</script>
<?php else: ?>
    <?= $this->renderSection('content') ?>
<?php endif; ?>
</body>
</html>
