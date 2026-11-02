<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Admin central · Empresas y planes</h2>
    <p>Asigna planes manualmente. El uso de disco mide la carpeta real de cada empresa bajo STORAGE_ROOT. Tipo (b) FREE hasta 3 usuarios / 10 GB; tipo (a) siempre Plan de pago.</p>
</section>

<nav class="admin-subnav" aria-label="Admin central">
    <a class="active" href="<?= site_url('admin/empresas') ?>">Empresas</a>
    <a href="<?= site_url('admin/promociones') ?>">Promover filial</a>
</nav>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Nueva empresa + administrador</h3>
    <p class="hint">Primero se crea la empresa; luego el usuario Admin queda como Administrador Master. Tipo (b) = FREE 3u/10GB. Tipo (a) requiere Plan de pago.</p>
    <form method="post" action="<?= site_url('admin/empresas/crear') ?>" class="admin-create-empresa">
        <?= csrf_field() ?>
        <div class="stack-forms">
            <div>
                <label for="emp_nombre">Nombre empresa</label>
                <input id="emp_nombre" type="text" name="nombre" required maxlength="191">
                <label for="emp_slug">Slug (opcional)</label>
                <input id="emp_slug" type="text" name="slug" maxlength="191">
                <label for="emp_rut">RUT (opcional)</label>
                <input id="emp_rut" type="text" name="rut" maxlength="32">
                <label for="emp_tipo">Tipo</label>
                <select id="emp_tipo" name="tipo" required>
                    <option value="b" selected>(b) Filial · FREE</option>
                    <option value="a">(a) Multiempresa · Plan de pago</option>
                </select>
                <label for="emp_plan">Plan (solo tipo a; opcional)</label>
                <select id="emp_plan" name="plan_id">
                    <option value="">— asignar después —</option>
                    <?php foreach ($planes as $p):
                        if ((int) ($p['es_gratuito'] ?? 0) === 1) {
                            continue;
                        }
                        $okTipo = ($p['tipo_aplicable'] === 'ambos' || $p['tipo_aplicable'] === 'a');
                        if (! $okTipo) {
                            continue;
                        }
                        ?>
                        <option value="<?= esc($p['id']) ?>"><?= esc($p['codigo']) ?> · <?= esc($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="adm_nombre">Nombre admin</label>
                <input id="adm_nombre" type="text" name="admin_nombre" required maxlength="191">
                <label for="adm_email">Email admin</label>
                <input id="adm_email" type="email" name="admin_email" required maxlength="191">
                <label for="adm_password">Contraseña admin</label>
                <input id="adm_password" type="password" name="admin_password" required minlength="6" autocomplete="new-password">
                <button class="btn btn-primary" type="submit" style="width:auto;margin-top:.5rem">Crear empresa y admin</button>
            </div>
        </div>
    </form>
</div>

<div class="card section-card">
    <h3>Empresas</h3>
    <?php if ($empresas === []): ?>
        <p class="hint">No hay empresas registradas.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Empresa</th>
                <th>Tipo</th>
                <th>Plan / estado</th>
                <th>Usuarios</th>
                <th>Disco vs plan</th>
                <th>Asignar plan</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $item):
                $e    = $item['row'];
                $snap = $item['snapshot'];
                $seatsLimit = $snap['seats_limit'];
                $storLimit  = $snap['storage_limit_bytes'];
                ?>
                <tr>
                    <td>
                        <strong><?= esc($e['nombre']) ?></strong>
                        <br><code><?= esc($e['slug']) ?></code>
                    </td>
                    <td>
                        <?php if (($e['tipo'] ?? '') === 'a'): ?>
                            <span class="tag">(a) Multiempresa</span>
                        <?php else: ?>
                            <span class="tag">(b) Filial</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= esc($e['plan_nombre'] ?? '— sin plan —') ?>
                        <?php if (! empty($e['plan_codigo'])): ?>
                            <br><code><?= esc($e['plan_codigo']) ?></code>
                        <?php endif; ?>
                        <br><small><?= esc($e['subscription_status'] ?? '—') ?></small>
                    </td>
                    <td>
                        <?= (int) $snap['seats_used'] ?>
                        /
                        <?= $seatsLimit === null ? '∞' : (int) $seatsLimit ?>
                        <?php if (! $snap['seats_ok']): ?>
                            <br><small class="text-danger">Límite</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>Disco:</strong>
                        <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) ($snap['disk_used_bytes'] ?? 0))) ?>
                        /
                        <?= $storLimit === null ? '∞' : esc(\App\Libraries\PlanEntitlement::formatBytes((int) $storLimit)) ?>
                        <br><small class="muted">
                            BD (docs): <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) ($snap['db_storage_bytes'] ?? 0))) ?>
                            · Facturable: <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) $snap['storage_used_bytes'])) ?>
                        </small>
                        <?php if (! $snap['storage_ok']): ?>
                            <br><small class="text-danger">Sobre el límite del plan</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= site_url('admin/empresas/asignar-plan') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="empresa_id" value="<?= esc($e['id']) ?>">
                            <select name="plan_id" required>
                                <option value="">— plan —</option>
                                <?php foreach ($planes as $p):
                                    $tipo = (string) ($e['tipo'] ?? 'b');
                                    $okTipo = ($p['tipo_aplicable'] === 'ambos' || $p['tipo_aplicable'] === $tipo);
                                    $okFree = ! ((int) $p['es_gratuito'] === 1 && $tipo === 'a');
                                    if (! $okTipo || ! $okFree) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= esc($p['id']) ?>" <?= (($e['plan_id'] ?? '') === $p['id']) ? 'selected' : '' ?>>
                                        <?= esc($p['codigo']) ?> · <?= esc($p['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
