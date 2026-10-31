<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Nuevo paquete seguro</h2>
    <p>Solo Master. Destinatario debe ser rol externo de la empresa.</p>
</section>

<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <form method="post" action="<?= site_url('flujos/canales') ?>">
        <?= csrf_field() ?>
        <label>Título</label>
        <input type="text" name="titulo" required>
        <label>Descripción</label>
        <input type="text" name="descripcion">
        <label>Destinatario</label>
        <select name="destinatario_id" required>
            <?php foreach ($externals as $ex): ?>
                <option value="<?= esc($ex['id']) ?>">
                    <?= esc($ex['nombre']) ?> · <?= esc(\App\Libraries\Roles::label($ex['rol'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Documentos</label>
        <?php foreach ($docs as $doc): ?>
            <label style="font-weight:500;display:flex;gap:.5rem;align-items:center;margin-bottom:.35rem">
                <input type="checkbox" name="documento_id[]" value="<?= esc($doc['id']) ?>" style="width:auto;margin:0">
                <?= esc($doc['titulo']) ?>
            </label>
        <?php endforeach; ?>
        <button class="btn btn-primary" type="submit" style="margin-top:1rem">Crear paquete</button>
    </form>
</div>
<?= $this->endSection() ?>
