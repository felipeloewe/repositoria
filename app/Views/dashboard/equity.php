<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCatalogo;
$menus = EquityCatalogo::menusInicio();
$grupos = [];
foreach ($menus as $m) {
    $grupos[$m['grupo']][] = $m;
}
?>

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
    <h2>Estado de situación</h2>
    <p>
        <?= esc($memb['empresa_nombre']) ?> · <?= esc($rolLabel) ?>.
        Menú patrimonial: activos, pasivos, ingresos y respaldos por socio o gerente.
    </p>
</section>

<div class="grid">
    <div class="card">
        <h3>Grupo Familiar</h3>
        <div class="value"><?= (int) $stats['personas'] ?></div>
        <div class="hint"><?= (int) $stats['socios'] ?> personales · <?= (int) $stats['gerentes'] ?> laborales · <?= (int) ($stats['familiares'] ?? 0) ?> familiares</div>
    </div>
    <div class="card">
        <h3>Fichas / ingresos</h3>
        <div class="value"><?= (int) $stats['fichas'] ?></div>
        <div class="hint">Cortes de ingreso registrados</div>
    </div>
    <div class="card">
        <h3>Respaldos</h3>
        <div class="value"><?= (int) $stats['respaldos'] ?></div>
        <div class="hint">Documentos de evidencia</div>
    </div>
</div>

<?php if (empty($canEquity)): ?>
    <div class="alert alert-info" style="margin-top:1rem">Tu membresía aún no tiene acceso a Equity.</div>
<?php else: ?>
    <?php foreach ($grupos as $grupo => $items): ?>
        <div class="card section-card" style="margin-top:1rem">
            <h3><?= esc($grupo) ?></h3>
            <div class="grid" style="margin-top:.75rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                <?php foreach ($items as $item): ?>
                    <a class="card" href="<?= site_url(ltrim($item['path'], '/')) ?>" style="text-decoration:none;color:inherit;display:block">
                        <h3 style="margin:0 0 .35rem"><?= esc($item['label']) ?></h3>
                        <div class="hint"><?= esc($item['descripcion']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= $this->endSection() ?>
