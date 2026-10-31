<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($paq['titulo']) ?></h2>
    <p>
        Para <?= esc($paq['destinatario_nombre'] ?? '') ?>
        · <span class="tag"><?= esc($paq['estado']) ?></span>
    </p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<?php if (! empty($paq['descripcion'])): ?>
    <div class="card section-card"><p class="muted" style="margin:0"><?= esc($paq['descripcion']) ?></p></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Documentos del paquete</h3>
    <table class="table">
        <thead><tr><th>Título</th><th>Archivo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): ?>
            <tr>
                <td><?= esc($d['titulo']) ?></td>
                <td class="muted"><?= esc($d['nombre_archivo'] ?? '') ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('flujos/canales/' . $paq['id'] . '/download/' . $d['id']) ?>">Descargar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($canManage && $paq['estado'] === 'ACTIVO'): ?>
        <form method="post" action="<?= site_url('flujos/canales/' . $paq['id'] . '/revoke') ?>" onsubmit="return confirm('¿Revocar acceso?')">
            <?= csrf_field() ?>
            <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Revocar paquete</button>
        </form>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Auditoría</h3>
    <table class="table">
        <thead><tr><th>Cuándo</th><th>Actor</th><th>Acción</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($audit as $a): ?>
            <tr>
                <td class="muted"><?= esc($a['created_at'] ?? '') ?></td>
                <td><?= esc($a['actor_nombre'] ?? '') ?></td>
                <td><span class="tag"><?= esc($a['accion']) ?></span></td>
                <td class="muted"><?= esc($a['detalle'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<p><a href="<?= site_url('flujos/canales') ?>">← Canales</a></p>
<?= $this->endSection() ?>
