<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($sol['titulo']) ?></h2>
    <p>
        <span class="tag"><?= esc($sol['tipo']) ?></span>
        · Estado <strong><?= esc($sol['estado']) ?></strong>
    </p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <h3>Detalle</h3>
    <p class="muted"><?= esc($sol['motivo'] ?? 'Sin motivo') ?></p>
    <?php if (! empty($sol['comentario_revision'])): ?>
        <p><strong>Comentario revisión:</strong> <?= esc($sol['comentario_revision']) ?></p>
    <?php endif; ?>
    <h3 style="margin-top:1rem">Documentos</h3>
    <ul>
        <?php foreach ($docs as $d): ?>
            <li><?= esc($d['titulo']) ?> <span class="muted">(<?= esc($d['nombre_archivo'] ?? '') ?>)</span></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="card section-card">
    <h3>Acciones</h3>
    <?php if ($canSubmit && in_array($sol['estado'], ['BORRADOR', 'RECHAZADA'], true)): ?>
        <form method="post" action="<?= site_url('flujos/fiscalizacion/' . $sol['id'] . '/submit') ?>" style="margin-bottom:.75rem">
            <?= csrf_field() ?>
            <button class="btn btn-primary btn-sm" type="submit">Enviar a revisión</button>
        </form>
    <?php endif; ?>

    <?php if ($canReview && $sol['estado'] === 'PENDIENTE'): ?>
        <form method="post" action="<?= site_url('flujos/fiscalizacion/' . $sol['id'] . '/review') ?>">
            <?= csrf_field() ?>
            <label>Comentario</label>
            <input type="text" name="comentario" placeholder="Opcional">
            <div style="display:flex;gap:.5rem;margin-top:.5rem">
                <button class="btn btn-primary btn-sm" type="submit" name="decision" value="APPROVE">Aprobar</button>
                <button class="btn btn-sm" type="submit" name="decision" value="DENY" style="background:#fee2e2;color:#991b1b">Rechazar</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($canSend && $sol['estado'] === 'APROBADA'): ?>
        <form method="post" action="<?= site_url('flujos/fiscalizacion/' . $sol['id'] . '/send') ?>" style="margin-top:.75rem">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Registrar exportación / envío</button>
        </form>
    <?php elseif ($sol['estado'] !== 'APROBADA' && $sol['estado'] !== 'ENVIADA'): ?>
        <p class="muted">Sin OK Master no hay envío.</p>
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

<p><a href="<?= site_url('flujos/fiscalizacion') ?>">← Solicitudes</a></p>
<?= $this->endSection() ?>
