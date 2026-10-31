<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Fiscalización</h2>
    <p>Solicitud → Master aprueba/rechaza → solo entonces registrar envío/exportación.</p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<?php if ($canRequest): ?>
<p><a class="btn btn-primary btn-sm" href="<?= site_url('flujos/fiscalizacion/nueva') ?>">Nueva solicitud</a></p>
<?php endif; ?>

<div class="card section-card">
    <table class="table">
        <thead><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Solicitante</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= esc($it['titulo']) ?></td>
                <td><span class="tag"><?= esc($it['tipo']) ?></span></td>
                <td><?= esc($it['estado']) ?></td>
                <td class="muted"><?= esc($it['solicitante_nombre'] ?? '') ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('flujos/fiscalizacion/' . $it['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
            <tr><td colspan="5" class="muted">Sin solicitudes visibles.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<p><a href="<?= site_url('flujos') ?>">← Flujos</a></p>
<?= $this->endSection() ?>
