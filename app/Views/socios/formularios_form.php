<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Nueva plantilla</h2>
    <p>Define el nombre; luego agregarás los campos (texto, fecha, archivo).</p>
</section>

<?= view('socios/_tabs', ['tab' => 'formularios']) ?>

<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <form method="post" action="<?= site_url('socios/formularios') ?>">
        <?= csrf_field() ?>
        <label for="nombre">Nombre</label>
        <input id="nombre" type="text" name="nombre" required maxlength="255" placeholder="Ej. Declaración de representante legal">

        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="3" placeholder="Opcional"></textarea>

        <div style="margin-top:1rem;display:flex;gap:.5rem">
            <button class="btn btn-primary" type="submit">Crear</button>
            <a class="btn btn-ghost" href="<?= site_url('socios/formularios') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
