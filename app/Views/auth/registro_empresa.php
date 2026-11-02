<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear empresa · Repositoria</title>
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

            <label for="rut">RUT (opcional)</label>
            <input id="rut" type="text" name="rut" maxlength="32"
                   value="<?= esc($old['rut'] ?? '') ?>" placeholder="76.123.456-7">

            <label for="slug">Slug URL (opcional)</label>
            <input id="slug" type="text" name="slug" maxlength="191"
                   value="<?= esc($old['slug'] ?? '') ?>" placeholder="servicios-andes">
            <p class="hint" style="margin-top:-.5rem;margin-bottom:1rem">Si lo dejas vacío, se genera desde el nombre.</p>

            <button class="btn btn-primary" type="submit">Continuar</button>
        </form>

        <p class="auth-alt">
            ¿Ya tienes cuenta? <a href="<?= site_url('login') ?>">Ingresar</a>
        </p>
    </div>
</div>
</body>
</html>
