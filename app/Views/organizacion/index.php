<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Organización</h2>
    <p>Empresa → Departamentos → Sucursales. Actividades y Temas de Interés son tags independientes del departamento.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (! empty($planSnap)): ?>
<div class="card section-card">
    <h3>Plan y cupos</h3>
    <p class="hint">
        Tipo <?= esc(($planSnap['empresa']['tipo'] ?? 'b') === 'a' ? '(a) multiempresa' : '(b) filial') ?>
        · Plan <?= esc($planSnap['plan']['codigo'] ?? '—') ?>
        · Estado <?= esc($planSnap['empresa']['subscription_status'] ?? '—') ?>
        · Usuarios <?= (int) $planSnap['seats_used'] ?>/<?= $planSnap['seats_limit'] === null ? '∞' : (int) $planSnap['seats_limit'] ?>
        · Storage <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) $planSnap['storage_used_bytes'])) ?>
        / <?= $planSnap['storage_limit_bytes'] === null ? '∞' : esc(\App\Libraries\PlanEntitlement::formatBytes((int) $planSnap['storage_limit_bytes'])) ?>
    </p>
</div>
<?php endif; ?>

<div class="stack-forms">
    <div class="card">
        <h3>Agregar usuario</h3>
        <form method="post" action="<?= site_url('organizacion/membresia') ?>">
            <?= csrf_field() ?>
            <label for="user_email">Email</label>
            <input id="user_email" type="email" name="email" required>
            <label for="user_nombre">Nombre</label>
            <input id="user_nombre" type="text" name="nombre" required>
            <label for="user_password">Contraseña (solo usuarios nuevos)</label>
            <input id="user_password" type="password" name="password" autocomplete="new-password">
            <label for="user_rol">Rol</label>
            <select id="user_rol" name="rol" required>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= esc($rol) ?>"><?= esc(\App\Libraries\Roles::label($rol)) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="user_suc">Sucursal (si Jefe)</label>
            <select id="user_suc" name="sucursal_id">
                <option value="">—</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= esc($s['id']) ?>"><?= esc($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Agregar usuario</button>
        </form>
    </div>
    <div class="card">
        <h3>Miembros activos</h3>
        <?php if (empty($miembros)): ?>
            <p class="hint">Sin membresías.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Rol</th><th>Sucursal</th></tr></thead>
                <tbody>
                <?php foreach ($miembros as $m): ?>
                    <tr>
                        <td><?= esc($m['usuario_nombre']) ?><br><small><?= esc($m['usuario_email']) ?></small></td>
                        <td><?= esc(\App\Libraries\Roles::label($m['rol'])) ?></td>
                        <td><?= esc($m['sucursal_nombre'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="stack-forms">
    <div class="card">
        <h3>Nuevo departamento</h3>
        <form method="post" action="<?= site_url('organizacion/departamento') ?>">
            <?= csrf_field() ?>
            <label for="depto_nombre">Nombre</label>
            <input id="depto_nombre" type="text" name="nombre" required>
            <button class="btn btn-primary" type="submit">Crear departamento</button>
        </form>
    </div>
    <div class="card">
        <h3>Nueva sucursal</h3>
        <form method="post" action="<?= site_url('organizacion/sucursal') ?>">
            <?= csrf_field() ?>
            <label for="suc_depto">Departamento</label>
            <select id="suc_depto" name="departamento_id" required>
                <?php foreach ($deptos as $d): ?>
                    <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="suc_nombre">Nombre</label>
            <input id="suc_nombre" type="text" name="nombre" required>
            <label for="suc_codigo">Código (opcional)</label>
            <input id="suc_codigo" type="text" name="codigo">
            <button class="btn btn-primary" type="submit">Crear sucursal</button>
        </form>
    </div>
</div>

<div class="stack-forms">
    <div class="card">
        <h3>Nueva actividad (tag)</h3>
        <form method="post" action="<?= site_url('organizacion/actividad') ?>">
            <?= csrf_field() ?>
            <label>Departamento</label>
            <select name="departamento_id" required>
                <?php foreach ($deptos as $d): ?>
                    <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Nombre</label>
            <input type="text" name="nombre" required>
            <button class="btn btn-primary" type="submit">Crear actividad</button>
        </form>
    </div>
    <div class="card">
        <h3>Nuevo tema de interés (tag)</h3>
        <form method="post" action="<?= site_url('organizacion/tema') ?>">
            <?= csrf_field() ?>
            <label>Departamento</label>
            <select name="departamento_id" required>
                <?php foreach ($deptos as $d): ?>
                    <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Nombre</label>
            <input type="text" name="nombre" required>
            <button class="btn btn-primary" type="submit">Crear tema</button>
        </form>
    </div>
</div>

<div class="card section-card">
    <h3>Departamentos</h3>
    <table class="table">
        <thead><tr><th>Nombre</th><th>ID</th></tr></thead>
        <tbody>
        <?php foreach ($deptos as $d): ?>
            <tr>
                <td><?= esc($d['nombre']) ?></td>
                <td><code><?= esc($d['id']) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card section-card">
    <h3>Sucursales</h3>
    <table class="table">
        <thead><tr><th>Nombre</th><th>Depto</th><th>Código</th></tr></thead>
        <tbody>
        <?php foreach ($sucursales as $s): ?>
            <tr>
                <td><?= esc($s['nombre']) ?></td>
                <td><?= esc($deptoMap[$s['departamento_id']] ?? '—') ?></td>
                <td><?= esc($s['codigo'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="stack-forms">
    <div class="card">
        <h3>Actividades</h3>
        <table class="table">
            <thead><tr><th>Nombre</th><th>Depto</th></tr></thead>
            <tbody>
            <?php foreach ($actividades as $a): ?>
                <tr>
                    <td><span class="tag"><?= esc($a['nombre']) ?></span></td>
                    <td><?= esc($deptoMap[$a['departamento_id']] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3>Temas de interés</h3>
        <table class="table">
            <thead><tr><th>Nombre</th><th>Depto</th></tr></thead>
            <tbody>
            <?php foreach ($temas as $t): ?>
                <tr>
                    <td><span class="tag"><?= esc($t['nombre']) ?></span></td>
                    <td><?= esc($deptoMap[$t['departamento_id']] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
