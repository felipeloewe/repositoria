<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Perfiles</h2>
    <p>Cuatro estereotipos base (Master, Jefe, Socio, Externo) con acceso completo de su mundo.
        Desmarca checkboxes para restringir; crea variantes con <strong>calificativo</strong>
        (ej. Socio · Minoritario) sin inventar un estereotipo nuevo.</p>
</section>

<?= $this->include('perfiles/_tabs') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Nueva variante</h3>
    <p class="hint">Parte de un estereotipo (permisos por defecto ON según el tipo) y opcionalmente un calificativo.</p>
    <form method="post" action="<?= site_url('perfiles') ?>" class="stack-forms">
        <?= csrf_field() ?>
        <div class="form-row">
            <label>
                Estereotipo
                <select name="estereotipo" required>
                    <?php foreach ($estereotipos as $est): ?>
                        <option value="<?= esc($est) ?>"><?= esc(\App\Libraries\PerfilPermisos::labelEstereotipo($est)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Calificativo
                <input type="text" name="calificativo" placeholder="ej. Minoritario" maxlength="96">
            </label>
            <label>
                Nombre (opcional)
                <input type="text" name="nombre" placeholder="Se arma solo si lo dejas vacío" maxlength="191">
            </label>
        </div>
        <button class="btn btn-primary" type="submit">Crear variante</button>
    </form>
</div>

<div class="card section-card">
    <h3>Perfiles de la empresa</h3>
    <?php if (empty($perfiles)): ?>
        <p class="muted">Sin perfiles.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Estereotipo</th>
                    <th>Calificativo</th>
                    <th>Tipo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($perfiles as $p): ?>
                <tr>
                    <td><strong><?= esc($p['nombre']) ?></strong></td>
                    <td><?= esc(\App\Libraries\PerfilPermisos::labelEstereotipo((string) $p['estereotipo'])) ?></td>
                    <td><?= esc($p['calificativo'] ?: '—') ?></td>
                    <td>
                        <?php if (! empty($p['es_plantilla'])): ?>
                            <span class="tag tag-oficial">Plantilla</span>
                        <?php else: ?>
                            <span class="tag">Variante</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('perfiles/' . $p['id']) ?>">Editar permisos</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
