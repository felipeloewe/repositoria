<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Repositoria</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('assets/brand/favicon-32.png') ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= base_url('assets/brand/apple-touch-icon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="brand brand--auth">
            <img class="brand-logo" src="<?= base_url('assets/brand/logo.svg') ?>" width="280" height="64" alt="Repositoria — Gestor documental">
        </div>
        <h2>Bienvenido</h2>
        <p class="lead">Usa tu correo y contraseña. El perfil sale de tu membresía activa — sin switcher de roles.</p>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('login') ?>">
            <?= csrf_field() ?>
            <label for="email">Correo</label>
            <input id="email" type="email" name="email" required autocomplete="username" placeholder="admin@repositoria.local">

            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">

            <button class="btn btn-primary" type="submit">Ingresar</button>
        </form>

        <p class="auth-alt">
            ¿Nueva empresa? <a href="<?= site_url('registro') ?>">Inicia FREE</a>
        </p>

        <div class="demo-hint">
            Seed: <code>admin@repositoria.local</code> / <code>demo123</code><br>
            Multi-empresa: <code>multi@repositoria.local</code>
        </div>
    </div>
</div>
</body>
</html>
