<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Editar empresa</h2>
    <p>
        Cambiá los datos de la empresa, invitá o reenviá acceso al Admin Master.
        El Admin autogestiona su clave tras verificar el correo.
    </p>
</section>

<nav class="admin-subnav" aria-label="Admin central">
    <a href="<?= site_url('admin/empresas') ?>">Empresas</a>
    <a class="active" href="<?= site_url('admin/empresas/' . $empresa['id']) ?>">Editar</a>
    <a href="<?= site_url('admin/promociones') ?>">Promover filial</a>
</nav>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($inviteInfo)): ?>
    <div class="alert alert-ok" style="white-space:pre-wrap;font-size:.9rem"><?= esc($inviteInfo) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<p style="margin:0 0 1rem">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/empresas') ?>">← Volver al listado</a>
</p>

<div class="card section-card">
    <h3><?= esc($empresa['nombre']) ?></h3>
    <p class="hint" style="margin-top:0">
        Tipo:
        <?php if (($empresa['tipo'] ?? '') === 'a'): ?>
            <span class="tag">(a) Multiempresa</span>
        <?php else: ?>
            <span class="tag">(b) Filial</span>
        <?php endif; ?>
        · Plan: <?= esc($empresa['plan_nombre'] ?? '— sin plan —') ?>
        <?php if (! empty($empresa['plan_codigo'])): ?>
            (<code><?= esc($empresa['plan_codigo']) ?></code>)
        <?php endif; ?>
        · Estado: <?= esc($empresa['subscription_status'] ?? '—') ?>
        · Usuarios: <?= (int) ($snapshot['seats_used'] ?? 0) ?>
        /
        <?php
        $seatsLimit = $snapshot['seats_limit'] ?? null;
        echo $seatsLimit === null ? '∞' : (int) $seatsLimit;
        ?>
    </p>

    <form method="post" action="<?= site_url('admin/empresas/' . $empresa['id'] . '/actualizar') ?>" class="admin-edit-empresa">
        <?= csrf_field() ?>
        <div class="stack-forms">
            <div>
                <label for="emp_nombre">Nombre empresa</label>
                <input id="emp_nombre" type="text" name="nombre" required maxlength="191"
                       value="<?= esc($empresa['nombre']) ?>">
                <label for="emp_alias">Alias</label>
                <input id="emp_alias" type="text" name="alias" required maxlength="191"
                       value="<?= esc($empresa['slug']) ?>">
                <label for="emp_rut">RUT</label>
                <input id="emp_rut" type="text" name="rut" maxlength="32"
                       value="<?= esc((string) ($empresa['rut'] ?? '')) ?>">
            </div>
            <div>
                <label style="display:flex;gap:.5rem;align-items:center;margin-top:.5rem">
                    <input type="checkbox" name="activa" value="1" <?= ((int) ($empresa['activa'] ?? 1) === 1) ? 'checked' : '' ?>>
                    Empresa activa
                </label>
                <?php if (array_key_exists('equity_es_matriz', $empresa)): ?>
                    <label style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem">
                        <input type="checkbox" name="equity_matriz" value="1" <?= ((int) ($empresa['equity_es_matriz'] ?? 0) === 1) ? 'checked' : '' ?>>
                        Matriz Equity (apadrina subsidiarias)
                    </label>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit" style="width:auto;margin-top:1rem">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>

<div class="card section-card">
    <h3>Admins Master</h3>
    <?php if ($admins === []): ?>
        <p class="hint">Esta empresa aún no tiene Admin Master. Invitá uno abajo.</p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo / RUT</th>
                <th>Estado</th>
                <th>Declarante</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $a):
                $verified = ! empty($a['email_verified_at']);
                $pending  = ! $verified && ! empty($a['email_verify_token']);
                $mustChg  = (int) ($a['must_change_password'] ?? 0) === 1;
                $inactive = (int) ($a['activo'] ?? 1) !== 1 || (int) ($a['membresia_activa'] ?? 1) !== 1;
                $esDecl   = (int) ($a['es_declarante'] ?? 0) === 1;
                ?>
                <tr>
                    <td>
                        <strong><?= esc($a['nombre'] ?? '—') ?></strong>
                        <?php if (! empty($a['alias'])): ?>
                            <br><code><?= esc($a['alias']) ?></code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= esc($a['email'] ?? '') ?>
                        <?php if (! empty($a['rut'])): ?>
                            <br><code><?= esc($a['rut']) ?></code>
                        <?php else: ?>
                            <br><span class="muted">Sin RUT</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($inactive): ?>
                            <span class="tag">Inactivo</span>
                        <?php elseif ($pending): ?>
                            <span class="tag">Pendiente verificación</span>
                        <?php elseif ($mustChg): ?>
                            <span class="tag">Debe cambiar clave</span>
                        <?php else: ?>
                            <span class="tag">Activo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= site_url('admin/empresas/' . $empresa['id'] . '/admin-declarante') ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usuario_id" value="<?= esc($a['id']) ?>">
                            <?php if ($esDecl): ?>
                                <input type="hidden" name="es_declarante" value="0">
                                <span class="tag" style="margin-right:.35rem">Sí</span>
                                <button class="btn btn-ghost btn-sm" type="submit">Quitar</button>
                            <?php elseif (empty($a['rut'])): ?>
                                <span class="muted" title="Carga el RUT del Admin para homologar con la ficha Declarante">Sin RUT</span>
                            <?php else: ?>
                                <input type="hidden" name="es_declarante" value="1">
                                <span class="muted" style="margin-right:.35rem">No</span>
                                <button class="btn btn-ghost btn-sm" type="submit">Marcar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="<?= site_url('admin/empresas/' . $empresa['id'] . '/reinvitar-admin') ?>" class="inline-form"
                              onsubmit="return confirm('¿Reenviar verificación y nueva clave temporal a <?= esc($a['email'] ?? '', 'attr') ?>?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usuario_id" value="<?= esc($a['id']) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Reinvitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Invitar Admin Master</h3>
    <p class="hint">
        Crea el contacto (o asocia un usuario existente) como Admin Master de esta empresa.
        Recibe verificación + clave temporal; al entrar define su propia clave.
        Si también declara, el RUT homologa usuario ↔ ficha persona; el patrimonio queda bajo ese persona_id.
    </p>
    <form method="post" action="<?= site_url('admin/empresas/' . $empresa['id'] . '/invitar-admin') ?>">
        <?= csrf_field() ?>
        <div class="stack-forms">
            <div>
                <label for="adm_nombre">Nombre</label>
                <input id="adm_nombre" type="text" name="admin_nombre" required maxlength="191">
                <label for="adm_alias">Alias</label>
                <input id="adm_alias" type="text" name="admin_alias" required maxlength="64" placeholder="ana.admin">
                <label for="adm_cargo">Cargo</label>
                <input id="adm_cargo" type="text" name="admin_cargo" maxlength="191" placeholder="Opcional">
            </div>
            <div>
                <label for="adm_rut">RUT</label>
                <input id="adm_rut" type="text" name="admin_rut" maxlength="32" placeholder="Obligatorio si también declara">
                <label for="adm_email">Correo *</label>
                <input id="adm_email" type="email" name="admin_email" required maxlength="191" placeholder="admin@empresa.cl">
                <label style="display:flex;gap:.5rem;align-items:center;margin-top:.75rem">
                    <input type="checkbox" name="admin_es_declarante" value="1" checked>
                    También es declarante en esta empresa
                </label>
                <button class="btn btn-primary" type="submit" style="width:auto;margin-top:.75rem">Invitar Admin</button>
            </div>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
