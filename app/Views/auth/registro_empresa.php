<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear empresa · Repositoria</title>
    <link rel="icon" href="<?= base_url('assets/brand/mark.svg') ?>" type="image/svg+xml">
    <link rel="icon" href="<?= base_url('favicon.ico') ?>?v=20261104b" sizes="any">
    <link rel="icon" type="image/png" href="<?= base_url('assets/brand/favicon-32.png') ?>?v=20261104b" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= base_url('assets/brand/apple-touch-icon.png') ?>?v=20261104b">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card auth-card--wide">
        <div class="brand">
            <img class="brand-mark" src="<?= base_url('assets/brand/mark.svg') ?>" width="42" height="42" alt="">
            <div>
                <h1>Repositoria</h1>
                <p>Alta FREE · paso 1 de 2</p>
            </div>
        </div>
        <h2>Crea tu empresa</h2>
        <p class="lead">Primero registramos la organización. Luego crearás el usuario Administrador Master. Plan FREE: hasta 3 usuarios y 10 GB.</p>

        <ol class="signup-steps" aria-label="Pasos del registro">
            <li class="is-active">Empresa</li>
            <li>Administrador</li>
        </ol>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('registro/empresa') ?>">
            <?= csrf_field() ?>
            <label for="nombre">Nombre de la empresa</label>
            <input id="nombre" type="text" name="nombre" required maxlength="191"
                   value="<?= esc($old['nombre'] ?? '') ?>" autocomplete="organization"
                   placeholder="Ej. Servicios Andes SpA">

            <label for="alias">Alias</label>
            <input id="alias" type="text" name="alias" required maxlength="191"
                   value="<?= esc($old['alias'] ?? $old['slug'] ?? '') ?>"
                   placeholder="servicios-andes" autocomplete="off">
            <p class="hint" style="margin-top:-.5rem;margin-bottom:1rem">Identificador único de la empresa (letras, números o guiones). Obligatorio.</p>

            <label for="rut">RUT (opcional)</label>
            <input id="rut" type="text" name="rut" maxlength="32"
                   value="<?= esc($old['rut'] ?? '') ?>" placeholder="76.123.456-7">

            <button class="btn btn-primary" type="submit">Continuar</button>
        </form>

        <p class="auth-alt">
            ¿Ya tienes cuenta? <a href="<?= site_url('login') ?>">Ingresar</a>
        </p>
    </div>
</div>
</body>
</html>
