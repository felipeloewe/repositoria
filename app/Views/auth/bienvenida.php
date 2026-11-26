<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bienvenido · Equity</title>
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
        <div class="brand brand--auth">
            <img class="brand-logo" src="<?= base_url('assets/brand/logo.svg') ?>" width="280" height="64" alt="Equity — Información financiera">
            <div class="brand-text" style="text-align:center;margin-top:.75rem">
                <h1 style="margin:0;font-family:var(--font-nav);font-size:1.6rem">Equity</h1>
                <p style="margin:.25rem 0 0;color:var(--muted,#64748b)">Información financiera</p>
            </div>
        </div>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
            <p class="lead">Si el enlace expiró, pide una nueva invitación a tu administrador.</p>
            <p style="margin-top:1.25rem">
                <a class="btn btn-primary" href="<?= site_url('login') ?>" style="text-decoration:none">Ir al login</a>
            </p>
        <?php else: ?>
            <div class="alert alert-ok">Correo verificado correctamente</div>
            <h2 style="font-family:var(--font-nav);margin:.35rem 0 .75rem">Gracias por registrarte en EQUITY</h2>
            <p class="lead" style="margin-bottom:1.25rem">
                Ya verificaste tus credenciales. Ahora ingresa a registrar tus
                <strong>Activos</strong> y <strong>Pasivos</strong> en la aplicación.
            </p>
            <p style="margin:0">
                <a class="btn btn-primary" href="<?= site_url('login') ?>" style="text-decoration:none">Ingresar</a>
            </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
