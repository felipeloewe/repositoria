<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Repositoria') ?> · Repositoria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Questrial&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<?php if (! empty($memb)): ?>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true"></div>
            <div>
                <h1>Repositoria</h1>
                <p>Documental multi-empresa</p>
            </div>
        </div>
        <nav class="nav">
            <a href="<?= site_url('dashboard') ?>" class="<?= ($title ?? '') === 'Inicio' ? 'active' : '' ?>">Inicio</a>
            <a href="<?= site_url('documentos') ?>" class="<?= ($title ?? '') === 'Documentos' ? 'active' : '' ?>">Documentos</a>
            <?php if (\App\Libraries\Roles::canManageOrg($memb['rol'])): ?>
                <a href="<?= site_url('organizacion') ?>" class="<?= ($title ?? '') === 'Organización' ? 'active' : '' ?>">Organización</a>
            <?php endif; ?>
            <a href="<?= site_url('logout') ?>">Salir</a>
        </nav>
        <div class="sidebar-foot">
            Fase 1–2 · Auth, org, docs<br>
            Fases 3–4: follow-up
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <div class="who"><?= esc($memb['usuario_nombre'] ?? '') ?></div>
                <div class="meta">
                    <?= esc($memb['empresa_nombre'] ?? '') ?>
                    · <?= esc(\App\Libraries\Roles::label($memb['rol'])) ?>
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
<?php else: ?>
    <?= $this->renderSection('content') ?>
<?php endif; ?>
</body>
</html>
