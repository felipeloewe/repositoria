<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Empresa') ?> · Repositoria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Questrial&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="width:min(520px,100%)">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true"></div>
            <div>
                <h1>Repositoria</h1>
                <p>Selecciona la empresa de esta sesión</p>
            </div>
        </div>
        <h2><?= ! empty($switching) ? 'Cambiar empresa' : 'Tus membresías' ?></h2>
        <p class="lead">Si el rol cambia al cambiar de empresa, el perfil se restablece al de esa membresía.</p>

        <?php if (! empty($error)): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php foreach ($membresias as $m): ?>
            <form method="post" action="<?= site_url(! empty($switching) ? 'empresa/cambiar' : 'login/empresa') ?>" style="margin-bottom:.65rem">
                <?= csrf_field() ?>
                <input type="hidden" name="membresia_id" value="<?= esc($m['id']) ?>">
                <button type="submit" class="btn" style="width:100%;justify-content:flex-start;gap:.85rem;padding:1rem;border:1px solid #d7e2f5;border-radius:14px;background:#fbfdff;color:inherit;font-weight:500;text-align:left<?= (($currentId ?? '') === $m['id']) ? ';outline:2px solid rgba(26,109,255,.35)' : '' ?>">
                    <span style="display:block">
                        <strong style="display:block;font-size:1.05rem"><?= esc($m['empresa_nombre']) ?></strong>
                        <span class="muted"><?= esc($roles::label($m['rol'])) ?>
                        <?php if (! empty($m['sucursal_nombre'])): ?>
                            · <?= esc($m['sucursal_nombre']) ?>
                        <?php endif; ?>
                        </span>
                    </span>
                </button>
            </form>
        <?php endforeach; ?>

        <?php if (! empty($switching)): ?>
            <p style="margin-top:1rem"><a href="<?= site_url('dashboard') ?>">Cancelar</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
