<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Rellenar: <?= esc($plantilla['nombre']) ?></h2>
    <p>Al enviar se genera un PDF y se guarda en documentos de socios.</p>
</section>

<?= view('socios/_tabs', ['tab' => 'formularios']) ?>

<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/rellenar') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="titulo">Título del documento / PDF</label>
        <input id="titulo" type="text" name="titulo" value="<?= esc($plantilla['nombre'] . ' — ' . date('Y-m-d')) ?>" required>

        <?= view('formularios/_campos_fill', ['campos' => $campos]) ?>

        <div style="margin-top:1rem;display:flex;gap:.5rem">
            <button class="btn btn-primary" type="submit">Generar PDF</button>
            <a class="btn btn-ghost" href="<?= site_url('socios/formularios/' . $plantilla['id']) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
