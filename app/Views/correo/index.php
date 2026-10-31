<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Correo</h2>
    <p>Índice de hilos vía imap-mail (<?= esc($mode) ?>). Guardá adjuntos y certificados PDF en el repositorio con ACL de membresía.</p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <h3>Estado del servicio</h3>
    <p class="muted" style="margin:0">
        Modo <span class="tag"><?= esc($health['mode'] ?? $mode) ?></span>
        — <?= esc($health['detail'] ?? '') ?>
        <?php if (! ($health['ok'] ?? true)): ?> · fallback a mock<?php endif; ?>
    </p>
    <p style="margin:.75rem 0 0">
        <a class="btn btn-ghost btn-sm" href="<?= site_url('correo/configuracion') ?>">Vincular casilla</a>
    </p>
</div>

<div class="card section-card">
    <h3>Hilos</h3>
    <?php if ($threads === []): ?>
        <p class="muted">No hay hilos. Vinculá una casilla o usá el mock demo.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Asunto</th><th>Msgs</th><th>Último</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($threads as $t): ?>
                <tr>
                    <td><strong><?= esc($t['subject'] ?? '—') ?></strong></td>
                    <td><?= (int) ($t['messageCount'] ?? 0) ?></td>
                    <td class="muted"><?= esc($t['lastMessageAt'] ?? '—') ?></td>
                    <td><a class="btn btn-ghost btn-sm" href="<?= site_url('correo/' . $t['id']) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
