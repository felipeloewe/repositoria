<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Departamentos</h2>
    <p>Catálogo de <strong>10 departamentos genéricos</strong> (sistema) que puedes activar o desactivar para esta empresa, más departamentos custom propios.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<p class="muted" style="margin:.25rem 0 1rem">
    <a href="<?= site_url('organizacion') ?>">← Organización</a>
    · Solo departamentos <strong>activos</strong> aparecen al crear sucursales, documentos y demás.
</p>

<div class="card section-card">
    <h3>Departamentos genéricos (<?= count($genericos) ?>)</h3>
    <p class="hint">Compartidos por todas las empresas. Activos por defecto al materializar; desactivar no borra datos ni sucursales asociadas.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Departamento</th>
                <th>Clave</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($genericos as $g): ?>
            <tr>
                <td>
                    <strong><?= esc($g['nombre']) ?></strong>
                    <br><span class="muted" style="font-size:.85rem"><?= esc($g['descripcion']) ?></span>
                </td>
                <td><code><?= esc($g['clave']) ?></code></td>
                <td>
                    <?php if (! empty($g['activo'])): ?>
                        <span class="tag tag-oficial">Activo</span>
                    <?php else: ?>
                        <span class="tag">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="<?= site_url('departamentos/toggle') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="clave" value="<?= esc($g['clave']) ?>">
                        <?php if (! empty($g['activo'])): ?>
                            <input type="hidden" name="activo" value="0">
                            <button class="btn btn-ghost btn-sm" type="submit">Desactivar</button>
                        <?php else: ?>
                            <input type="hidden" name="activo" value="1">
                            <button class="btn btn-primary btn-sm" type="submit">Activar</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card section-card">
    <h3>Departamentos custom (<?= count($custom) ?>)</h3>
    <?php if ($custom === []): ?>
        <p class="muted">Ninguno aún. Crea uno propio si el catálogo genérico no alcanza.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($custom as $c): ?>
                <tr>
                    <td><?= esc($c['nombre']) ?></td>
                    <td>
                        <?php if ((int) ($c['activo'] ?? 1) === 1): ?>
                            <span class="tag tag-oficial">Activo</span>
                        <?php else: ?>
                            <span class="tag">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h4 style="margin-top:1.25rem">Nuevo departamento custom</h4>
    <form method="post" action="<?= site_url('departamentos/custom') ?>" class="form-row" style="grid-template-columns:1fr auto;align-items:end">
        <?= csrf_field() ?>
        <div>
            <label for="depto_nombre">Nombre</label>
            <input id="depto_nombre" type="text" name="nombre" required placeholder="p.ej. Innovación">
        </div>
        <div>
            <button class="btn btn-primary btn-sm" type="submit">Crear</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
