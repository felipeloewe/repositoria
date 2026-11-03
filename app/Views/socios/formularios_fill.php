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

        <?php foreach ($campos as $c): ?>
            <?php
                $clave = $c['clave'];
                $req   = (int) $c['obligatorio'] === 1;
                $label = $c['etiqueta'] . ($req ? ' *' : '');
            ?>
            <label for="f_<?= esc($clave) ?>"><?= esc($label) ?></label>
            <?php if ($c['tipo'] === 'date'): ?>
                <input id="f_<?= esc($clave) ?>" type="date" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?>>
            <?php elseif ($c['tipo'] === 'file'): ?>
                <input id="f_<?= esc($clave) ?>" type="file" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?> style="margin-bottom:1rem">
            <?php else: ?>
                <input id="f_<?= esc($clave) ?>" type="text" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?>>
            <?php endif; ?>
        <?php endforeach; ?>

        <div style="margin-top:1rem;display:flex;gap:.5rem">
            <button class="btn btn-primary" type="submit">Generar PDF</button>
            <a class="btn btn-ghost" href="<?= site_url('socios/formularios/' . $plantilla['id']) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
