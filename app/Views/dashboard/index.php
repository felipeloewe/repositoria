<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php if (! empty($notice)): ?>
    <div class="alert alert-info"><?= esc($notice) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= esc($flash) ?></div>
<?php endif; ?>

<section class="banner">
    <h2>Hola, <?= esc($memb['usuario_nombre']) ?></h2>
    <p>
        Perfil activo: <strong><?= esc($rolLabel) ?></strong>
        en <?= esc($memb['empresa_nombre']) ?>
        <?php if (! empty($memb['sucursal_nombre'])): ?>
            (<?= esc($memb['sucursal_nombre']) ?>)
        <?php endif; ?>.
        Entrada por membresía — sin switcher de roles.
    </p>
</section>

<div class="grid">
    <div class="card">
        <h3>Departamentos</h3>
        <div class="value"><?= (int) $stats['departamentos'] ?></div>
        <div class="hint">Jerarquía Empresa → Depto → Sucursal</div>
    </div>
    <div class="card">
        <h3>Sucursales</h3>
        <div class="value"><?= (int) $stats['sucursales'] ?></div>
        <div class="hint">Alcance ACL por sucursal</div>
    </div>
    <div class="card">
        <h3>Documentos</h3>
        <div class="value"><?= (int) $stats['documentos'] ?></div>
        <div class="hint">Visibles para tu membresía</div>
    </div>
</div>

<div class="card section-card" style="animation-delay:.08s">
    <h3>Roadmap en este stack</h3>
    <p class="muted" style="margin-top:0">CI4 · Fase 1 auth/org + Fase 2 repositorio documental.</p>
    <ul>
        <li><strong>Fase 2</strong> — docs por departamento, secciones, upload/download, ACL ✓</li>
        <li><strong>Fase 3</strong> — integración imap-mail (follow-up)</li>
        <li><strong>Fase 4</strong> — fiscalización y canales seguros (follow-up)</li>
    </ul>
    <a class="btn btn-primary btn-sm" href="<?= site_url('documentos') ?>">Ir al repositorio</a>
    <?php if ($canOrg): ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('organizacion') ?>">Organización</a>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
