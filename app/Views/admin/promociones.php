<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Admin central · Promover filial</h2>
    <p>Convierte una Sucursal/Filial (modo b) en Empresa tenant propia (modo a): remapea docs/ACL/membresías y mueve blobs bajo STORAGE_ROOT.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Promover sucursal → empresa</h3>
    <?php if ($candidatas === []): ?>
        <p class="hint">No hay sucursales activas elegibles para promover.</p>
    <?php else: ?>
        <form method="post" action="<?= site_url('admin/promociones/promover') ?>" class="stack-form-inner" onsubmit="return confirm('¿Promover esta filial a empresa propia? Esta operación remapea IDs y mueve archivos.');">
            <?= csrf_field() ?>
            <label for="sucursal_id">Sucursal / Filial</label>
            <select id="sucursal_id" name="sucursal_id" required>
                <option value="">— seleccionar —</option>
                <?php foreach ($candidatas as $s): ?>
                    <option value="<?= esc($s['id']) ?>">
                        <?= esc($s['empresa_nombre']) ?> · <?= esc($s['departamento_nombre']) ?> · <?= esc($s['nombre']) ?>
                        <?php if (! empty($s['codigo'])): ?>(<?= esc($s['codigo']) ?>)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="nombre">Nombre nueva empresa (opcional)</label>
            <input id="nombre" type="text" name="nombre" placeholder="Default: nombre de la sucursal">
            <label for="slug">Slug (opcional)</label>
            <input id="slug" type="text" name="slug" placeholder="Default: slugify del nombre">
            <button class="btn btn-primary" type="submit">Promover a empresa</button>
        </form>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Historial de promociones</h3>
    <?php if ($historial === []): ?>
        <p class="hint">Sin promociones registradas.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Cuándo</th>
                <th>Quién</th>
                <th>Origen</th>
                <th>Nueva empresa</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= esc($h['created_at'] ?? '—') ?></td>
                    <td><?= esc($h['actor_email'] ?? 'CLI / sistema') ?></td>
                    <td>
                        <?= esc($h['origen_empresa_nombre']) ?>
                        → <?= esc($h['origen_sucursal_nombre']) ?>
                        <br><code><?= esc($h['origen_sucursal_id']) ?></code>
                    </td>
                    <td>
                        <?= esc($h['nueva_empresa_nombre']) ?>
                        <br><code><?= esc($h['nueva_empresa_id']) ?></code>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
