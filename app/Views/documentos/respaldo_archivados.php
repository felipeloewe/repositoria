<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Respaldo mensual de archivados</h2>
    <p>Genera un ZIP por mes, descárgalo a tu respaldo local ordenado y libera espacio en el servidor (se eliminan los blobs archivados de ese periodo).</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('documentos') ?>">Documentos</a>
    <a href="<?= site_url('documentos/archivados') ?>">Archivados</a>
    <a href="<?= site_url('documentos/archivados/respaldo') ?>" class="active">Respaldo mensual</a>
</div>

<div class="card section-card">
    <h3>Periodos pendientes (blob aún en disco)</h3>
    <?php if ($pendientes === []): ?>
        <p class="muted">No hay archivados pendientes de respaldo.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Mes</th>
                <th>Documentos</th>
                <th>Tamaño aprox.</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pendientes as $p): ?>
            <tr>
                <td><strong><?= esc($p['periodo']) ?></strong></td>
                <td><?= (int) $p['docs'] ?></td>
                <td class="muted"><?= number_format((int) $p['bytes'] / 1024, 1) ?> KB</td>
                <td>
                    <form method="post" action="<?= site_url('documentos/archivados/respaldo') ?>" onsubmit="return confirm('¿Generar ZIP de <?= esc($p['periodo']) ?> y eliminar los archivos del servidor?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="periodo" value="<?= esc($p['periodo']) ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Generar ZIP y limpiar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Historial de respaldos</h3>
    <?php if ($historial === []): ?>
        <p class="muted">Aún no se ha generado ningún ZIP.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Docs</th>
                <th>Tamaño ZIP</th>
                <th>Creado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($historial as $h): ?>
            <tr>
                <td><strong><?= esc($h['periodo']) ?></strong></td>
                <td><?= (int) $h['docs_count'] ?></td>
                <td class="muted"><?= number_format((int) ($h['tamano_bytes'] ?? 0) / 1024, 1) ?> KB</td>
                <td class="muted"><?= esc($h['created_at'] ?? '') ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/archivados/respaldo/' . $h['id'] . '/download') ?>">Descargar ZIP</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
