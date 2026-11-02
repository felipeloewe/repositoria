<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Documentos archivados</h2>
    <p>Listado de documentos desactivados/archivados. Los archivos viven bajo <code>{empresa}/archivados/</code>. Usa <strong>Respaldo</strong> para ZIP mensual y liberar disco.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('documentos') ?>">Documentos</a>
    <a href="<?= site_url('documentos/archivados') ?>" class="active">Archivados</a>
    <a href="<?= site_url('documentos/archivados/respaldo') ?>">Respaldo mensual</a>
</div>

<div class="card section-card">
    <h3>Archivados (<?= count($docs) ?>)</h3>
    <?php if ($docs === []): ?>
        <p class="muted">No hay documentos archivados en esta empresa.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Archivado</th>
                <th>Blob</th>
                <th>Ruta</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($docs as $doc): ?>
            <tr>
                <td>
                    <a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a><br>
                    <span class="muted" style="font-size:.8rem"><?= esc($doc['nombre_archivo'] ?? '') ?></span>
                </td>
                <td>
                    <?php if (! empty($doc['tipo'])): ?>
                        <?= esc($doc['tipo']['nombre']) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="muted"><?= esc($doc['archivado_at'] ?? '—') ?></td>
                <td>
                    <?php if (! empty($doc['en_disco'])): ?>
                        <span class="tag tag-oficial">En disco</span>
                        <span class="muted"><?= number_format((int) ($doc['tamano_bytes'] ?? 0) / 1024, 1) ?> KB</span>
                    <?php else: ?>
                        <span class="tag estado-archivado">Respaldado / borrado</span>
                        <span class="muted"><?= esc($doc['blob_eliminado_at'] ?? '') ?></span>
                    <?php endif; ?>
                </td>
                <td class="muted" style="font-size:.75rem;max-width:220px;word-break:break-all"><?= esc($doc['ruta_relativa'] ?? '') ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id']) ?>">Abrir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
