<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Formulario') ?> · Repositoria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card auth-card--wide">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true"></div>
            <div>
                <h1 style="font-family:var(--font-nav)">Repositoria</h1>
                <p><?= esc($label ?? 'Formulario') ?></p>
            </div>
        </div>
        <h2>No disponible</h2>
        <div class="alert alert-error"><?= esc($error ?? 'Enlace no válido.') ?></div>
    </div>
</div>
</body>
</html>
