<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($thread['subject'] ?? 'Hilo') ?></h2>
    <p>Modo <?= esc($mode) ?> · casilla <?= esc($thread['account']['email'] ?? '') ?></p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<?php foreach ($thread['messages'] ?? [] as $msg): ?>
<div class="card section-card">
    <h3 style="font-size:1rem"><?= esc($msg['subject'] ?? '') ?></h3>
    <p class="muted" style="margin:0 0 .5rem">
        <?= esc($msg['fromAddr'] ?? '') ?> → <?= esc($msg['toAddrs'] ?? '') ?>
        · <?= esc($msg['date'] ?? '') ?> · <?= esc($msg['folder'] ?? '') ?>
    </p>
    <p><?= nl2br(esc($msg['bodyText'] ?? '')) ?></p>
    <?php foreach ($msg['attachments'] ?? [] as $att): ?>
        <div style="margin-top:.75rem;padding:.75rem;background:var(--blue-50);border-radius:12px">
            <strong><?= esc($att['filename'] ?? 'adjunto') ?></strong>
            <span class="muted">(<?= esc($att['mimeType'] ?? '') ?>)</span>
            <?php if ($uploadableSections !== []): ?>
            <form method="post" action="<?= site_url('correo/guardar-adjunto') ?>" style="margin-top:.5rem">
                <?= csrf_field() ?>
                <input type="hidden" name="attachment_id" value="<?= esc($att['id']) ?>">
                <input type="hidden" name="thread_id" value="<?= esc($thread['id']) ?>">
                <label>Sección destino</label>
                <select name="seccion_id" required>
                    <?php foreach ($uploadableSections as $s): ?>
                        <option value="<?= esc($s['id']) ?>"><?= esc($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Título (opcional)</label>
                <input type="text" name="titulo" placeholder="Adjunto correo · <?= esc($att['filename'] ?? '') ?>">
                <button class="btn btn-primary btn-sm" type="submit">Guardar adjunto en repositorio</button>
            </form>
            <?php else: ?>
                <p class="muted" style="margin:.35rem 0 0">Tu rol no puede subir a secciones (solo Master/Jefe).</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="card section-card">
    <h3>Certificado PDF del hilo</h3>
    <?php if ($uploadableSections !== []): ?>
    <form method="post" action="<?= site_url('correo/guardar-certificado') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="thread_id" value="<?= esc($thread['id']) ?>">
        <label>Sección destino</label>
        <select name="seccion_id" required>
            <?php foreach ($uploadableSections as $s): ?>
                <option value="<?= esc($s['id']) ?>"><?= esc($s['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Título (opcional)</label>
        <input type="text" name="titulo" placeholder="Certificado de hilo">
        <button class="btn btn-primary" type="submit">Guardar certificado en repositorio</button>
    </form>
    <?php else: ?>
        <p class="muted">Sin permiso de carga en secciones.</p>
    <?php endif; ?>
</div>

<p><a href="<?= site_url('correo') ?>">← Hilos</a></p>
<?= $this->endSection() ?>
