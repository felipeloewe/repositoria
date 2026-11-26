<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>PIN de bóveda</h2>
    <p>Protege claves de internet y PINes de cajero. Sin este PIN no se pueden leer los secretos cifrados.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <?php if (! empty($returnTo)): ?>
        <a href="<?= esc($returnTo) ?>">← Volver</a>
    <?php else: ?>
        <a href="<?= site_url('equity') ?>">← Equity</a>
    <?php endif; ?>
</div>

<?php if (empty($secretosOk)): ?>
    <div class="alert alert-error">Falta la migración de bóveda (000043).</div>
<?php else: ?>
<div class="card section-card">
    <h3><?= ! empty($tienePin) ? 'Cambiar PIN' : 'Crear PIN' ?></h3>
    <p class="muted" style="margin-top:0">
        Usa 4 a 12 dígitos. Queda en tu perfil de usuario (no en la ficha de la persona).
        Más adelante se podrá sumar desbloqueo con clave del dispositivo; por ahora el PIN es el candado.
    </p>
    <form method="post" action="<?= site_url('equity/vault-pin') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <?php if (! empty($returnTo)): ?>
            <input type="hidden" name="return" value="<?= esc($returnTo) ?>">
        <?php endif; ?>
        <?php if (! empty($tienePin)): ?>
            <div class="form-row" style="grid-template-columns:1fr;max-width:280px">
                <div>
                    <label for="pin_actual">PIN actual</label>
                    <input id="pin_actual" name="pin_actual" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="12" required autocomplete="off">
                </div>
            </div>
        <?php endif; ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr;max-width:420px;gap:1rem">
            <div>
                <label for="pin">Nuevo PIN</label>
                <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="12" required autocomplete="new-password">
            </div>
            <div>
                <label for="pin_confirm">Confirmar PIN</label>
                <input id="pin_confirm" name="pin_confirm" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="12" required autocomplete="new-password">
            </div>
        </div>
        <p style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= ! empty($tienePin) ? 'Actualizar PIN' : 'Guardar PIN' ?></button>
        </p>
    </form>
    <?php if (! empty($tienePin)): ?>
        <p class="muted" style="margin-top:1rem">
            Estado: PIN configurado<?= ! empty($vaultOpen) ? ' · bóveda abierta en esta sesión' : ' · bóveda bloqueada' ?>.
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
