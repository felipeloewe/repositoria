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

<div class="card section-card">
    <h3>Recursos genéricos</h3>
    <p class="hint">Plantillas y documentos de sistema compartidos por todas las empresas. Activos por defecto; puedes desactivarlos solo para esta empresa. El mismo patrón aplica a futuros documentos genéricos.</p>
    <?php if (empty($recursosSistema)): ?>
        <p class="muted">Sin recursos genéricos publicados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Recurso</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recursosSistema as $rec): ?>
                <tr>
                    <td>
                        <strong><?= esc($rec['nombre']) ?></strong>
                        <br><span class="muted" style="font-size:.85rem"><?= esc($rec['descripcion']) ?></span>
                        <br><code><?= esc($rec['clave']) ?></code>
                    </td>
                    <td><?= esc($rec['tipo'] === 'form_plantilla' ? 'Plantilla formulario' : 'Documento') ?></td>
                    <td>
                        <?php if (! empty($rec['activo'])): ?>
                            <span class="tag tag-oficial">Activa</span>
                        <?php else: ?>
                            <span class="tag">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= site_url('organizacion/recursos-sistema/toggle') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="recurso_tipo" value="<?= esc($rec['tipo']) ?>">
                            <input type="hidden" name="recurso_clave" value="<?= esc($rec['clave']) ?>">
                            <?php if (! empty($rec['activo'])): ?>
                                <input type="hidden" name="activo" value="0">
                                <button class="btn btn-ghost btn-sm" type="submit">Desactivar</button>
                            <?php else: ?>
                                <input type="hidden" name="activo" value="1">
                                <button class="btn btn-primary btn-sm" type="submit">Activar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (! empty($planSnap)): ?>
<div class="card section-card">
    <h3>Plan y cupos</h3>
    <p class="hint">
        Tipo <?= esc(($planSnap['empresa']['tipo'] ?? 'b') === 'a' ? '(a) multiempresa' : '(b) filial') ?>
        · Plan <?= esc($planSnap['plan']['codigo'] ?? '—') ?>
        · Estado <?= esc($planSnap['empresa']['subscription_status'] ?? '—') ?>
        · Usuarios <?= (int) $planSnap['seats_used'] ?>/<?= $planSnap['seats_limit'] === null ? '∞' : (int) $planSnap['seats_limit'] ?>
        · Disco <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) ($planSnap['disk_used_bytes'] ?? 0))) ?>
        / <?= $planSnap['storage_limit_bytes'] === null ? '∞' : esc(\App\Libraries\PlanEntitlement::formatBytes((int) $planSnap['storage_limit_bytes'])) ?>
        <br>BD docs: <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) ($planSnap['db_storage_bytes'] ?? 0))) ?>
        · Facturable: <?= esc(\App\Libraries\PlanEntitlement::formatBytes((int) $planSnap['storage_used_bytes'])) ?>
    </p>
</div>
<?php endif; ?>

<div class="stack-forms">
    <div class="card">
        <h3>Agregar usuario a la organización</h3>
        <p class="hint">Como Administrador Master puedes crear membresías en esta empresa. El límite de cupos lo define el plan.</p>
        <?php
        $seatsFull = ! empty($planSnap)
            && $planSnap['seats_limit'] !== null
            && (int) $planSnap['seats_used'] >= (int) $planSnap['seats_limit'];
        $blocked = ! empty($planSnap) && (empty($planSnap['can_operate']) || $seatsFull);
        ?>
        <?php if ($blocked): ?>
            <div class="alert alert-error">
                No puedes agregar más usuarios ahora
                (<?= (int) ($planSnap['seats_used'] ?? 0) ?>/<?= $planSnap['seats_limit'] === null ? '∞' : (int) $planSnap['seats_limit'] ?>).
                Amplía el Plan con el Administrador central o libera una membresía.
            </div>
        <?php endif; ?>
        <form method="post" action="<?= site_url('organizacion/membresia') ?>">
            <?= csrf_field() ?>
            <label for="user_email">Email</label>
            <input id="user_email" type="email" name="email" required <?= $blocked ? 'disabled' : '' ?>>
            <label for="user_nombre">Nombre</label>
            <input id="user_nombre" type="text" name="nombre" required <?= $blocked ? 'disabled' : '' ?>>
            <label for="user_password">Contraseña (solo usuarios nuevos)</label>
            <input id="user_password" type="password" name="password" autocomplete="new-password" <?= $blocked ? 'disabled' : '' ?>>
            <label for="user_rol">Rol</label>
            <select id="user_rol" name="rol" required <?= $blocked ? 'disabled' : '' ?>>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= esc($rol) ?>"><?= esc(\App\Libraries\Roles::label($rol)) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="user_suc">Sucursal (si Jefe)</label>
            <select id="user_suc" name="sucursal_id" <?= $blocked ? 'disabled' : '' ?>>
                <option value="">—</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= esc($s['id']) ?>"><?= esc($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="socio-field" class="socio-field" hidden>
                <label class="check-label" for="user_socio">
                    <input id="user_socio" type="checkbox" name="es_socio" value="1" <?= $blocked ? 'disabled' : '' ?>>
                    Socio — activa Menú Socios (documentos exclusivos de socios / representantes legales)
                </label>
            </div>
            <button class="btn btn-primary" type="submit" <?= $blocked ? 'disabled' : '' ?>>Agregar usuario</button>
        </form>
        <script>
        (function () {
          var rol = document.getElementById('user_rol');
          var wrap = document.getElementById('socio-field');
          var box = document.getElementById('user_socio');
          if (!rol || !wrap || !box) return;
          function sync() {
            var isMaster = rol.value === 'ADMINISTRADOR_MASTER';
            wrap.hidden = !isMaster;
            if (!isMaster) box.checked = false;
          }
          rol.addEventListener('change', sync);
          sync();
        })();
        </script>
    </div>
    <div class="card">
        <h3>Miembros activos</h3>
        <?php if (empty($miembros)): ?>
            <p class="hint">Sin membresías.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Nombre</th><th>Rol</th><th>Sucursal</th><th>Socio</th></tr></thead>
                <tbody>
                <?php foreach ($miembros as $m): ?>
                    <tr>
                        <td><?= esc($m['usuario_nombre']) ?><br><small><?= esc($m['usuario_email']) ?></small></td>
                        <td><?= esc(\App\Libraries\Roles::label($m['rol'])) ?></td>
                        <td><?= esc($m['sucursal_nombre'] ?? '—') ?></td>
                        <td>
                            <?php if (\App\Libraries\Roles::isMaster($m['rol'])): ?>
                                <form method="post" action="<?= site_url('organizacion/membresia/socio') ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="membresia_id" value="<?= esc($m['id']) ?>">
                                    <?php if ((int) ($m['es_socio'] ?? 0) === 1): ?>
                                        <input type="hidden" name="es_socio" value="0">
                                        <button class="btn btn-ghost btn-sm" type="submit" title="Desactivar Socio">Sí · quitar</button>
                                    <?php else: ?>
                                        <input type="hidden" name="es_socio" value="1">
                                        <button class="btn btn-primary btn-sm" type="submit" title="Activar Socio">No · activar</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
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
