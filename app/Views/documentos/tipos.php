<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Tipos de documento</h2>
    <p>Cada tipo es <strong>borrador</strong> (control de cambios + fecha término) u <strong>oficial</strong> (ciclo de vida, versiones y vencimientos).</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('documentos') ?>">Documentos</a>
    <a href="<?= site_url('documentos/tipos') ?>" class="active">Tipos</a>
</div>

<div class="card section-card">
    <h3>Nuevo tipo</h3>
    <form method="post" action="<?= site_url('documentos/tipos') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1.2fr .8fr 1fr 1.4fr auto;align-items:end">
            <div>
                <label for="t_nombre">Nombre</label>
                <input id="t_nombre" type="text" name="nombre" required placeholder="Política interna">
            </div>
            <div>
                <label for="t_codigo">Código</label>
                <input id="t_codigo" type="text" name="codigo" required placeholder="POLITICA">
            </div>
            <div>
                <label for="t_mod">Modalidad</label>
                <select id="t_mod" name="modalidad" required>
                    <option value="oficial">Oficial</option>
                    <option value="borrador">Borrador</option>
                </select>
            </div>
            <div>
                <label for="t_desc">Descripción</label>
                <input id="t_desc" type="text" name="descripcion" placeholder="Opcional">
            </div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit">Crear</button>
            </div>
        </div>
    </form>
</div>

<div class="card section-card">
    <h3>Tipos (<?= count($tipos) ?>)</h3>
    <?php if ($tipos === []): ?>
        <p class="muted">Aún no hay tipos. Crea al menos uno oficial y uno borrador.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Código</th>
                <th>Modalidad</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tipos as $t): ?>
            <tr>
                <td>
                    <strong><?= esc($t['nombre']) ?></strong>
                    <?php if (! empty($t['descripcion'])): ?>
                        <br><span class="muted" style="font-size:.8rem"><?= esc($t['descripcion']) ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="tag"><?= esc($t['codigo']) ?></span></td>
                <td>
                    <?php if ($t['modalidad'] === 'oficial'): ?>
                        <span class="tag tag-oficial">Oficial</span>
                    <?php else: ?>
                        <span class="tag tag-borrador-tipo">Borrador</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $t['activo'] === 1 ? 'Activo' : 'Inactivo' ?></td>
                <td>
                    <form method="post" action="<?= site_url('documentos/tipos/' . $t['id'] . '/toggle') ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-sm" type="submit">
                            <?= (int) $t['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
