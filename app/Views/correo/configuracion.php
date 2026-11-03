<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Vincular casilla</h2>
    <p>La casilla se asocia a tu usuario. Adjuntos y certificados PDF van al repositorio de <strong><?= esc($memb['empresa_nombre'] ?? 'tu empresa') ?></strong>.</p>
</section>

<?php if (! empty($success)): ?><div class="alert alert-ok"><?= esc($success) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <p class="muted" style="margin-top:0">Estado: <span class="tag"><?= esc($mode) ?></span> — <?= esc($health['detail'] ?? '') ?></p>

    <?php if ($gmailOAuthUrl): ?>
        <p>
            <a class="btn btn-ghost" href="<?= esc($gmailOAuthUrl) ?>" rel="noopener">Conectar Gmail (OAuth)</a>
            <span class="muted">Se abre en imap-mail; requiere GMAIL_* en el servicio.</span>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= site_url('correo/configuracion') ?>" class="correo-link-form">
        <?= csrf_field() ?>
        <label>Correo</label>
        <input type="email" name="email" required placeholder="tu@empresa.cl" value="">
        <label>Nombre visible</label>
        <input type="text" name="display_name" placeholder="Opcional">
        <label>Proveedor</label>
        <select name="provider" id="correo-provider">
            <option value="IMAP">IMAP</option>
            <option value="GMAIL">Gmail (registro; OAuth arriba)</option>
        </select>
        <div id="correo-imap-fields">
            <label>Host IMAP</label>
            <input type="text" name="imap_host" placeholder="imap.empresa.cl">
            <label>Puerto</label>
            <input type="text" name="imap_port" value="993">
            <label>Usuario IMAP</label>
            <input type="text" name="imap_user" placeholder="igual al correo">
            <label>Password IMAP</label>
            <input type="password" name="imap_password" autocomplete="new-password">
        </div>
        <button class="btn btn-primary" type="submit">Vincular</button>
    </form>
</div>

<?php if ($accounts !== []): ?>
<div class="card section-card">
    <h3>Casillas vinculadas</h3>
    <ul class="correo-account-list">
        <?php foreach ($accounts as $a): ?>
            <li class="correo-account-list__item">
                <div>
                    <strong><?= esc($a['email'] ?? '') ?></strong>
                    <span class="muted"> · <?= esc($a['provider'] ?? '') ?> · <?= esc($a['syncStatus'] ?? '') ?>
                        <?php if (! empty($a['lastSyncedAt'])): ?> · último <?= esc($a['lastSyncedAt']) ?><?php endif; ?>
                    </span>
                </div>
                <form method="post" action="<?= site_url('correo/sincronizar') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account_id" value="<?= esc($a['id'] ?? '') ?>">
                    <button class="btn btn-primary btn-sm" type="submit">Sincronizar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Sección destino (auto)</h3>
    <p class="muted">Usada cuando imap-mail envía adjuntos o el certificado PDF por webhook (si no trae <code>suggestedSeccionId</code>).</p>
    <?php if ($uploadableSections === []): ?>
        <p class="muted">Tu rol no puede elegir sección de carga (solo Master/Jefe).</p>
    <?php else: ?>
        <form method="post" action="<?= site_url('correo/preferencia') ?>">
            <?= csrf_field() ?>
            <label>Sección del repositorio</label>
            <select name="seccion_id" required>
                <?php foreach ($uploadableSections as $s): ?>
                    <option value="<?= esc($s['id']) ?>" <?= (($preferencia['seccion_id'] ?? '') === $s['id']) ? 'selected' : '' ?>>
                        <?= esc($s['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Guardar preferencia</button>
        </form>
    <?php endif; ?>
</div>

<p><a href="<?= site_url('correo') ?>">← Volver a Correo</a></p>
<?= $this->endSection() ?>
