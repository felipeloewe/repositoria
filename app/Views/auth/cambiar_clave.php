<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $productName = \App\Libraries\Product::name(); ?>
    <title>Cambiar clave · <?= esc($productName) ?></title>
    <link rel="icon" href="<?= base_url('assets/brand/mark.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <h2>Cambia tu clave</h2>
        <p class="lead">Por seguridad debes definir una clave nueva. Mínimo 6 caracteres, con letras y números. Caracteres especiales opcionales.</p>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('cambiar-clave') ?>">
            <?= csrf_field() ?>
            <label for="password">Nueva clave</label>
            <input id="password" type="password" name="password" required minlength="6" autocomplete="new-password">

            <label for="password_confirm">Confirmar clave</label>
            <input id="password_confirm" type="password" name="password_confirm" required minlength="6" autocomplete="new-password">

            <button class="btn btn-primary" type="submit">Guardar e ingresar</button>
        </form>
    </div>
</div>
</body>
</html>
