<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $productName = \App\Libraries\Product::name(); ?>
    <title>Seleccionar persona · <?= esc($productName) ?></title>
    <link rel="icon" href="<?= base_url('assets/brand/mark.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <h2>¿A quién representas?</h2>
        <p class="lead">Puedes completar datos a nombre de más de una persona. Elige con quién trabajarás en esta sesión.</p>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('login/persona') ?>">
            <?= csrf_field() ?>
            <?php foreach ($personas as $p): ?>
                <?php
                $label = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
                if (($p['rut'] ?? '') !== '') {
                    $label .= ' · ' . $p['rut'];
                }
                ?>
                <label class="auth-choice" style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0">
                    <input type="radio" name="persona_id" value="<?= esc($p['id']) ?>" required>
                    <span><?= esc($label) ?></span>
                </label>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit" style="margin-top:1rem">Continuar</button>
        </form>
    </div>
</div>
</body>
</html>
