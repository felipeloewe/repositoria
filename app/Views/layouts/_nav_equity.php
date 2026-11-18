<?php
/** @var array<string, mixed> $memb */
$nav = static fn (string $key): bool => \App\Libraries\PerfilAcl::canMenu($memb, $key);
$title = $title ?? '';
$canOrg = $nav('menu.organizacion');
$canEquity = $nav('menu.equity');
$canPerfiles = $nav('menu.perfiles');
?>
<p class="nav-section">Principal</p>
<a href="<?= site_url('dashboard') ?>" class="<?= $title === 'Inicio' ? 'active' : '' ?>" data-nav="inicio"><span class="nav-label">Inicio</span></a>
<?php if ($canEquity): ?>
    <a href="<?= site_url('equity') ?>" class="<?= $title === 'Equity' && empty($equityNav) ? 'active' : '' ?>" data-nav="personas"><span class="nav-label">Personas</span></a>
    <a href="<?= site_url('equity?tipo=socio') ?>" class="nav-sub <?= ($equityNav ?? '') === 'socio' ? 'active' : '' ?>" data-nav="socios"><span class="nav-label">Socios</span></a>
    <a href="<?= site_url('equity?tipo=gerente') ?>" class="nav-sub <?= ($equityNav ?? '') === 'gerente' ? 'active' : '' ?>" data-nav="gerentes"><span class="nav-label">Gerentes</span></a>
<?php endif; ?>

<?php if ($canOrg || $canPerfiles): ?>
    <p class="nav-section">Administración</p>
<?php endif; ?>
<?php if ($canOrg): ?>
    <a href="<?= site_url('organizacion') ?>" class="<?= $title === 'Organización' ? 'active' : '' ?>" data-nav="org"><span class="nav-label">Organización</span></a>
<?php endif; ?>
<?php if ($canPerfiles): ?>
    <a href="<?= site_url('perfiles') ?>" class="<?= $title === 'Perfiles' ? 'active' : '' ?>" data-nav="perfiles"><span class="nav-label">Perfiles</span></a>
<?php endif; ?>
<a href="<?= site_url('logout') ?>" class="nav-exit" data-nav="salir"><span class="nav-label">Salir</span></a>
