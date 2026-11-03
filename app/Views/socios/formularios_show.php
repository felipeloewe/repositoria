<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php $esGenerica = ! empty($esGenerica) || ! empty($plantilla['clave_sistema']); ?>
<section class="banner">
    <h2><?= esc($plantilla['nombre']) ?><?php if ($esGenerica): ?> <span class="tag">Genérica</span><?php endif; ?></h2>
    <p><?= esc($plantilla['descripcion'] ?: 'Plantilla de formulario de socios.') ?></p>
</section>

<?= view('socios/_tabs', ['tab' => 'formularios']) ?>

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

<p class="muted" style="margin:.5rem 0 1rem">
    <a href="<?= site_url('socios/formularios') ?>">← Formularios</a>
    <?php if ((int) $plantilla['activo'] === 1): ?>
        · <a href="<?= site_url('socios/formularios/' . $plantilla['id'] . '/rellenar') ?>">Rellenar ahora</a>
    <?php endif; ?>
    · <?= (int) $plantilla['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
</p>

<div class="card section-card">
    <h3>Datos de la plantilla</h3>
    <?php if ($esGenerica): ?>
        <p class="hint">Plantilla genérica de sistema (<code><?= esc((string) ($plantilla['clave_sistema'] ?? '')) ?></code>). La estructura es fija; el Master activa/desactiva por empresa en <a href="<?= site_url('organizacion') ?>">Organización</a>.</p>
        <p><strong><?= esc($plantilla['nombre']) ?></strong></p>
        <p class="muted"><?= esc($plantilla['descripcion'] ?? '') ?></p>
    <?php else: ?>
    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id']) ?>">
        <?= csrf_field() ?>
        <label for="nombre">Nombre</label>
        <input id="nombre" type="text" name="nombre" required value="<?= esc($plantilla['nombre']) ?>">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="2"><?= esc($plantilla['descripcion'] ?? '') ?></textarea>
        <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
        </div>
    </form>
    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/toggle') ?>" style="margin-top:.75rem">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" type="submit">
            <?= (int) $plantilla['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Campos (<?= count($campos) ?>)</h3>
    <?php if ($campos === []): ?>
        <p class="muted">Sin campos. Agrega texto, fecha o archivo.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Etiqueta</th>
                <th>Clave</th>
                <th>Tipo</th>
                <th>Oblig.</th>
                <?php if (! $esGenerica): ?><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campos as $c): ?>
            <tr>
                <td class="muted"><?= (int) $c['orden'] ?></td>
                <td><?= esc($c['etiqueta']) ?></td>
                <td><code><?= esc($c['clave']) ?></code></td>
                <td><?= esc($c['tipo']) ?></td>
                <td><?= (int) $c['obligatorio'] === 1 ? 'Sí' : 'No' ?></td>
                <?php if (! $esGenerica): ?>
                <td>
                    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/campos/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('¿Eliminar campo?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Eliminar</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (! $esGenerica): ?>
    <h4 style="margin-top:1.25rem">Agregar campo</h4>
    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/campos') ?>" class="form-row" style="grid-template-columns:1.4fr 1fr .8fr auto auto;align-items:end">
        <?= csrf_field() ?>
        <div>
            <label for="etiqueta">Etiqueta</label>
            <input id="etiqueta" type="text" name="etiqueta" required placeholder="Nombre completo">
        </div>
        <div>
            <label for="clave">Clave (opcional)</label>
            <input id="clave" type="text" name="clave" placeholder="auto">
        </div>
        <div>
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" required>
                <?php foreach ($tiposCampo as $t): ?>
                    <option value="<?= esc($t) ?>"><?= esc($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><input type="checkbox" name="obligatorio" value="1"> Obligatorio</label>
        </div>
        <div>
            <button class="btn btn-primary btn-sm" type="submit">Agregar</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php if ((int) $plantilla['activo'] === 1 && $campos !== []): ?>
<div class="card section-card">
    <h3>Compartir (URL pública)</h3>
    <p class="muted">Genera un enlace con contraseña dinámica. El tercero no necesita cuenta Repositoria.</p>
    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/compartir') ?>" class="form-row" style="grid-template-columns:1fr 1fr auto;align-items:end">
        <?= csrf_field() ?>
        <div>
            <label for="max_usos">Máximo de usos</label>
            <input id="max_usos" type="number" name="max_usos" min="1" max="50" value="1">
        </div>
        <div>
            <label for="horas_vigencia">Vigencia (horas, 0 = sin límite de tiempo)</label>
            <input id="horas_vigencia" type="number" name="horas_vigencia" min="0" max="8760" value="72">
        </div>
        <div>
            <button class="btn btn-primary btn-sm" type="submit">Generar enlace</button>
        </div>
    </form>

    <?php if ($shares !== []): ?>
    <table class="table" style="margin-top:1rem">
        <thead>
            <tr>
                <th>Creado</th>
                <th>Usos</th>
                <th>Expira</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shares as $s): ?>
            <?php
                $usable = \App\Libraries\SocioFormService::shareIsUsable($s);
                $status = ! empty($s['revoked_at']) ? 'Revocado' : ($usable['ok'] ? 'Activo' : 'Agotado/expirado');
            ?>
            <tr>
                <td class="muted"><?= esc($s['created_at'] ?? '') ?></td>
                <td><?= (int) $s['usos'] ?> / <?= (int) $s['max_usos'] ?></td>
                <td class="muted"><?= esc($s['expira_at'] ?? '—') ?></td>
                <td><?= esc($status) ?></td>
                <td>
                    <?php if (empty($s['revoked_at']) && $usable['ok']): ?>
                    <form method="post" action="<?= site_url('socios/formularios/' . $plantilla['id'] . '/shares/' . $s['id'] . '/revoke') ?>" onsubmit="return confirm('¿Revocar enlace?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Revocar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Instancias de esta plantilla</h3>
    <?php if ($instancias === []): ?>
        <p class="muted">Ninguna aún.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Origen</th>
                <th>Fecha</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($instancias as $inst): ?>
            <tr>
                <td><?= esc($inst['titulo']) ?></td>
                <td><?= esc($inst['origen']) ?></td>
                <td class="muted"><?= esc($inst['completed_at'] ?? '') ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('socios/formularios/instancias/' . $inst['id']) ?>">Ver</a>
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
