<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php if (! empty($notice)): ?>
    <div class="alert alert-info"><?= esc($notice) ?></div>
<?php endif; ?>
<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<section class="banner">
    <h2>Hola, <?= esc($memb['usuario_nombre']) ?></h2>
    <p>
        Equity en <strong><?= esc($memb['empresa_nombre']) ?></strong>
        · <?= esc($rolLabel) ?>.
        Flujo: personas (socio/gerente) → ficha financiera → respaldos.
    </p>
</section>

<div class="grid">
    <div class="card">
        <h3>Personas</h3>
        <div class="value"><?= (int) $stats['personas'] ?></div>
        <div class="hint"><?= (int) $stats['activos'] ?> activas</div>
    </div>
    <div class="card">
        <h3>Socios</h3>
        <div class="value"><?= (int) $stats['socios'] ?></div>
        <div class="hint">Perfiles de socio</div>
    </div>
    <div class="card">
        <h3>Gerentes</h3>
        <div class="value"><?= (int) $stats['gerentes'] ?></div>
        <div class="hint">Perfiles de gerente</div>
    </div>
</div>

<div class="grid" style="margin-top:1rem">
    <div class="card">
        <h3>Fichas</h3>
        <div class="value"><?= (int) $stats['fichas'] ?></div>
        <div class="hint">Cortes financieros</div>
    </div>
    <div class="card">
        <h3>Respaldos</h3>
        <div class="value"><?= (int) $stats['respaldos'] ?></div>
        <div class="hint">Documentos de respaldo</div>
    </div>
</div>

<div class="card section-card" style="animation-delay:.08s">
    <h3>Empezar</h3>
    <p class="muted" style="margin-top:0">Registra socios o gerentes y carga su información financiera con evidencias.</p>
    <?php if (! empty($canEquity)): ?>
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity') ?>">Ver personas</a>
        <?php if (! empty($canManage)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/nueva') ?>">Nueva persona</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity?tipo=socio') ?>">Socios</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity?tipo=gerente') ?>">Gerentes</a>
    <?php else: ?>
        <div class="alert alert-info">Tu membresía aún no tiene acceso a Equity. Pide al Master que active el permiso.</div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
