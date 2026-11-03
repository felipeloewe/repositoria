<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Formulario</h2>
    <p>Plantillas de formularios de empresa. El resultado se guarda como PDF en Documentos (ámbito general).</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (! empty($shareCreds) && is_array($shareCreds)): ?>
<div class="alert alert-ok share-creds">
    <strong>Credenciales del enlace</strong> (cópialas ahora; la contraseña no se vuelve a mostrar):<br>
    URL: <code><?= esc($shareCreds['url'] ?? '') ?></code><br>
    Contraseña: <code><?= esc($shareCreds['password'] ?? '') ?></code>
    <?php if (! empty($shareCreds['expira'])): ?>
        <br>Expira: <?= esc($shareCreds['expira']) ?>
    <?php endif; ?>
    · Máx. usos: <?= (int) ($shareCreds['max_usos'] ?? 1) ?>
</div>
<?php endif; ?>

<div class="card section-card">
    <div class="form-row" style="grid-template-columns:1fr auto;align-items:end;margin-bottom:0">
        <div>
            <h3 style="margin:0">Plantillas (<?= count($plantillas) ?>)</h3>
            <p class="muted" style="margin:.35rem 0 0">Custom de la empresa + genéricas de empresa activas. Distintas de Socios → Formularios.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= site_url('formularios/nueva') ?>">Nueva plantilla</a>
    </div>
</div>

<div class="card section-card">
    <?php if ($plantillas === []): ?>
        <p class="muted">Aún no hay plantillas. Crea una para empezar.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($plantillas as $p): ?>
            <?php $esGenerica = ! empty($p['clave_sistema']); ?>
            <tr>
                <td>
                    <a href="<?= site_url('formularios/' . $p['id']) ?>"><strong><?= esc($p['nombre']) ?></strong></a>
                    <?php if ($esGenerica): ?>
                        <span class="tag" title="Plantilla genérica de sistema (empresa)">Genérica</span>
                    <?php endif; ?>
                    <?php if (! empty($p['descripcion'])): ?>
                        <br><span class="muted" style="font-size:.85rem"><?= esc($p['descripcion']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $p['activo'] === 1): ?>
                        <span class="tag tag-oficial">Activa</span>
                    <?php else: ?>
                        <span class="tag">Inactiva</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('formularios/' . $p['id']) ?>">Abrir</a>
                    <?php if ((int) $p['activo'] === 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('formularios/' . $p['id'] . '/rellenar') ?>">Rellenar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Últimas instancias</h3>
    <?php if ($instancias === []): ?>
        <p class="muted">Sin formularios completados aún.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Plantilla</th>
                <th>Origen</th>
                <th>Completado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($instancias as $inst): ?>
            <?php $p = $plantillaMap[$inst['plantilla_id']] ?? null; ?>
            <tr>
                <td><strong><?= esc($inst['titulo']) ?></strong></td>
                <td><?= esc($p['nombre'] ?? '—') ?></td>
                <td><?= esc($inst['origen'] ?? '') ?></td>
                <td class="muted"><?= esc($inst['completed_at'] ?? $inst['created_at'] ?? '') ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('formularios/instancias/' . $inst['id']) ?>">Ver</a>
                    <?php if (! empty($inst['documento_id'])): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $inst['documento_id']) ?>">PDF</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
