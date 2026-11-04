<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($instancia['titulo']) ?></h2>
    <p>
        Plantilla <?= esc($plantilla['nombre'] ?? '') ?>
        · origen <?= esc($instancia['origen'] ?? '') ?>
        · <?= esc($instancia['completed_at'] ?? '') ?>
    </p>
</section>

<?= view('socios/_tabs', ['tab' => 'formularios']) ?>

<p class="muted"><a href="<?= site_url('socios/formularios') ?>">← Formularios</a>
<?php if (! empty($instancia['documento_id'])): ?>
    · <a href="<?= site_url('documentos/' . $instancia['documento_id']) ?>">Abrir PDF</a>
    · <a href="<?= site_url('documentos/' . $instancia['documento_id'] . '/download') ?>">Descargar</a>
<?php endif; ?>
</p>

<div class="card section-card">
    <h3>Respuestas</h3>
    <?= view('formularios/_campos_respuestas', [
        'campos'     => $campos,
        'respuestas' => $respuestas,
    ]) ?>
</div>

<?= $this->endSection() ?>
