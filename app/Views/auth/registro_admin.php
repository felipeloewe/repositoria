<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrador · Repositoria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Questrial&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card auth-card--wide">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true"></div>
            <div>
                <h1>Repositoria</h1>
                <p>Alta FREE · paso 2 de 2</p>
            </div>
        </div>
        <h2>Administrador Master</h2>
        <p class="lead">
            Empresa: <strong><?= esc($draft['nombre'] ?? '') ?></strong>
            <?php if (! empty($draft['slug'])): ?>
                · <code><?= esc($draft['slug']) ?></code>
            <?php endif; ?>
        </p>

        <ol class="signup-steps" aria-label="Pasos del registro">
            <li class="is-done">Empresa</li>
            <li class="is-active">Administrador</li>
        </ol>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('registro/admin') ?>">
            <?= csrf_field() ?>
            <label for="nombre">Tu nombre</label>
            <input id="nombre" type="text" name="nombre" required maxlength="191"
                   value="<?= esc($old['nombre'] ?? '') ?>" autocomplete="name"
                   placeholder="Nombre completo">

            <label for="email">Correo</label>
            <input id="email" type="email" name="email" required maxlength="191"
                   value="<?= esc($old['email'] ?? '') ?>" autocomplete="username"
                   placeholder="admin@tuempresa.cl">

            <label for="password">Contraseña</label>
            <input id="password" type="password" name="password" required minlength="6"
                   autocomplete="new-password" placeholder="Mínimo 6 caracteres">

            <label for="password_confirm">Confirmar contraseña</label>
            <input id="password_confirm" type="password" name="password_confirm" required minlength="6"
                   autocomplete="new-password">

            <button class="btn btn-primary" type="submit">Crear empresa y entrar</button>
        </form>

        <p class="auth-alt">
            <a href="<?= site_url('registro/cancelar') ?>">← Cambiar datos de la empresa</a>
        </p>
    </div>
</div>
</body>
</html>
