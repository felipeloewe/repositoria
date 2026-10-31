<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Canales seguros</h2>
    <p>Paquetes documentales acotados y revocables para Contador / Abogado / Prevencionista.</p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<?php if ($canManage): ?>
<p><a class="btn btn-primary btn-sm" href="<?= site_url('flujos/canales/nuevo') ?>">Nuevo paquete</a></p>
<?php endif; ?>

<div class="card section-card">
    <table class="table">
        <thead><tr><th>Título</th><th>Destinatario</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= esc($it['titulo']) ?></td>
                <td><?= esc($it['destinatario_nombre'] ?? '') ?><br><span class="muted"><?= esc($it['destinatario_email'] ?? '') ?></span></td>
                <td><span class="tag"><?= esc($it['estado']) ?></span></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('flujos/canales/' . $it['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($items === []): ?>
            <tr><td colspan="4" class="muted">Sin paquetes.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<p><a href="<?= site_url('flujos') ?>">← Flujos</a></p>
<?= $this->endSection() ?>
