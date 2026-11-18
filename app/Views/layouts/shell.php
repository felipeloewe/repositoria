<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $productName = \App\Libraries\Product::name(); ?>
    <title><?= esc($title ?? $productName) ?> · <?= esc($productName) ?></title>
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
                        <h1><?= esc(\App\Libraries\Product::name()) ?></h1>
                        <p><?= esc(\App\Libraries\Product::tagline()) ?></p>
                    </div>
                </div>
                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-expanded="true" aria-controls="app-sidebar" aria-label="Contraer menú" title="Contraer menú">
                    <span class="sidebar-toggle-icon" aria-hidden="true"></span>
                </button>
            </div>
            <nav class="nav">
                <?php if (\App\Libraries\Product::isEquity()): ?>
                    <?= view('layouts/_nav_equity', ['memb' => $memb, 'title' => $title ?? '', 'equityNav' => $equityNav ?? '']) ?>
                <?php else: ?>
                    <?= view('layouts/_nav_repositoria', ['memb' => $memb, 'title' => $title ?? '']) ?>
                <?php endif; ?>
            </nav>
            <div class="sidebar-foot">
                <?= \App\Libraries\Product::footNote() ?>
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
  var key = <?= json_encode(\App\Libraries\Product::sidebarStorageKey(), JSON_UNESCAPED_SLASHES) ?>;
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
