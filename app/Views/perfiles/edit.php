<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($perfil['nombre']) ?></h2>
    <p>Estereotipo
        <strong><?= esc(\App\Libraries\PerfilPermisos::labelEstereotipo((string) $perfil['estereotipo'])) ?></strong>.
        Desmarca lo que no deba tener este perfil. Sigue ligado al estereotipo base
       <?= $perfil['calificativo'] ? '; calificativo <strong>' . esc($perfil['calificativo']) . '</strong>' : '' ?>.
    </p>
</section>

<?= $this->include('perfiles/_tabs') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<p><a href="<?= site_url('perfiles') ?>">← Volver al listado</a></p>

<form method="post" action="<?= site_url('perfiles/' . $perfil['id']) ?>">
    <?= csrf_field() ?>
    <div class="card section-card">
        <h3>Identidad</h3>
        <div class="form-row">
            <label>
                Nombre
                <input type="text" name="nombre" value="<?= esc($perfil['nombre']) ?>" maxlength="191" required>
            </label>
            <label>
                Calificativo
                <input type="text" name="calificativo" value="<?= esc($perfil['calificativo'] ?? '') ?>" maxlength="96" placeholder="ej. Minoritario">
            </label>
        </div>
    </div>

    <?php
    $grupos = [];
    foreach ($catalogo as $item) {
        $grupos[$item['grupo']][] = $item;
    }
    ?>
    <?php foreach ($grupos as $grupo => $items): ?>
        <div class="card section-card">
            <h3><?= esc($grupo) ?></h3>
            <div class="permiso-grid">
                <?php foreach ($items as $item): ?>
                    <?php $clave = $item['clave']; ?>
                    <label class="permiso-check">
                        <input type="checkbox" name="permisos[<?= esc($clave) ?>]" value="1"
                            <?= ! empty($permisos[$clave]) ? 'checked' : '' ?>>
                        <span><?= esc($item['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <button class="btn btn-primary" type="submit">Guardar perfil</button>
</form>

<style>
.permiso-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: .55rem .85rem;
}
.permiso-check {
    display: flex;
    align-items: flex-start;
    gap: .45rem;
    font-size: .92rem;
    margin: 0;
    cursor: pointer;
}
.permiso-check input { margin-top: .2rem; }
</style>

<?= $this->endSection() ?>
