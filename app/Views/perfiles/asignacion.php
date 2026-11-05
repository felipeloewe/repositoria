<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Asignación de perfiles</h2>
    <p>Asigna un perfil estereotipado (o variante con calificativo) a cada membresía.
        Al asignar se alinean <code>rol</code> y <code>es_socio</code> al estereotipo base.</p>
</section>

<?= $this->include('perfiles/_tabs') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Miembros activos</h3>
    <?php if (empty($miembros)): ?>
        <p class="muted">Sin membresías activas.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol actual</th>
                    <th>Perfil</th>
                    <th>Asignar</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($miembros as $m): ?>
                <tr>
                    <td>
                        <strong><?= esc($m['usuario_nombre']) ?></strong><br>
                        <span class="muted"><?= esc($m['usuario_email']) ?></span>
                        <?php if (! empty($m['sucursal_nombre'])): ?>
                            <br><span class="muted"><?= esc($m['sucursal_nombre']) ?></span>
                        <?php endif; ?>
                        <?php if (! empty($m['es_socio'])): ?>
                            <br><span class="tag tag-oficial">Socio</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(\App\Libraries\Roles::label((string) $m['rol'])) ?></td>
                    <td>
                        <?php if (! empty($m['perfil_nombre'])): ?>
                            <strong><?= esc($m['perfil_nombre']) ?></strong>
                        <?php else: ?>
                            <span class="muted">Sin perfil (fallback Roles)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?= site_url('perfiles/asignacion') ?>" class="form-row" style="align-items:end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="membresia_id" value="<?= esc($m['id']) ?>">
                            <label style="margin:0;flex:1">
                                <select name="perfil_id" required>
                                    <option value="">Elegir…</option>
                                    <?php foreach ($perfiles as $p): ?>
                                        <option value="<?= esc($p['id']) ?>" <?= ($m['perfil_id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                                            <?= esc($p['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
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
