<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Vincular casilla</h2>
    <p>La casilla se asocia a tu <code>usuarioId</code>. Sin IMAP_MAIL_BASE_URL se usa mock offline.</p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <p class="muted">Estado: <span class="tag"><?= esc($mode) ?></span> — <?= esc($health['detail'] ?? '') ?></p>
    <form method="post" action="<?= site_url('correo/configuracion') ?>">
        <?= csrf_field() ?>
        <label>Correo</label>
        <input type="email" name="email" required placeholder="tu@empresa.cl" value="demo@repositoria.local">
        <label>Nombre visible</label>
        <input type="text" name="display_name" placeholder="Opcional">
        <label>Proveedor</label>
        <select name="provider">
            <option value="IMAP">IMAP</option>
            <option value="GMAIL">Gmail OAuth (vía imap-mail)</option>
        </select>
        <label>Host IMAP</label>
        <input type="text" name="imap_host" placeholder="imap.example.local">
        <label>Puerto</label>
        <input type="text" name="imap_port" value="993">
        <label>Usuario IMAP</label>
        <input type="text" name="imap_user" placeholder="igual al correo">
        <label>Password IMAP</label>
        <input type="password" name="imap_password" autocomplete="new-password">
        <button class="btn btn-primary" type="submit">Vincular</button>
    </form>
</div>

<?php if ($accounts !== []): ?>
<div class="card section-card">
    <h3>Casillas</h3>
    <ul>
        <?php foreach ($accounts as $a): ?>
            <li><?= esc($a['email'] ?? '') ?> · <?= esc($a['provider'] ?? '') ?> · <?= esc($a['syncStatus'] ?? '') ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<p><a href="<?= site_url('correo') ?>">← Volver a Correo</a></p>
<?= $this->endSection() ?>
