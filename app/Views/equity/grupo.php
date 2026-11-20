<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-success"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>
<?php if (! empty($inviteInfo)): ?>
    <div class="alert alert-success" style="white-space:pre-wrap;font-size:.9rem"><?= esc($inviteInfo) ?></div>
<?php endif; ?>

<?php use App\Libraries\IdentityVault; ?>

<p style="margin:0 0 1rem;display:flex;gap:.5rem;flex-wrap:wrap">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/grupo/privacidad') ?>">Privacidad · inventario PII</a>
</p>

<section class="card-panel" style="margin-bottom:1.25rem">
    <h2 style="margin-top:0">Empresa matriz</h2>
    <p class="muted" style="margin-top:0">
        La matriz contrata Equity y apadrina subsidiarias (pueden compartir socios aunque no haya participación societaria).
    </p>
    <?php if (is_array($matriz ?? null)): ?>
        <p><strong><?= esc($matriz['nombre'] ?? '') ?></strong>
            <?php if (! empty($matriz['rut'])): ?> · RUT <?= esc($matriz['rut']) ?><?php endif; ?>
            <?php if (! empty($matriz['equity_es_matriz'])): ?> · <span class="badge">Matriz</span><?php endif; ?>
        </p>
    <?php endif; ?>

    <h3>Subsidiarias</h3>
    <?php if (($subsidiarias ?? []) === []): ?>
        <p class="muted">Aún no hay subsidiarias apadrinadas.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($subsidiarias as $s): ?>
                <li><?= esc($s['nombre'] ?? '') ?><?php if (! empty($s['rut'])): ?> · <?= esc($s['rut']) ?><?php endif; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= site_url('equity/grupo/subsidiaria') ?>" class="form-grid" style="margin-top:1rem">
        <?= csrf_field() ?>
        <label for="sub_nombre">Nueva subsidiaria · Nombre *</label>
        <input id="sub_nombre" name="nombre" type="text" required>

        <label for="sub_rut">RUT</label>
        <input id="sub_rut" name="rut" type="text" placeholder="76.xxx.xxx-x">

        <button class="btn btn-primary" type="submit">Registrar subsidiaria</button>
    </form>
</section>

<section class="card-panel" style="margin-bottom:1.25rem">
    <h2 style="margin-top:0">Alta de declarante</h2>
    <p class="muted" style="margin-top:0">
        Como Admin de la empresa, inscribes aquí a las <strong>personas que hacen la declaración</strong> patrimonial
        (en la matriz o una subsidiaria). Se crea el usuario, se envía verificación de correo y una clave temporal (cambio obligatorio al ingresar).
        Si la persona será representada por un tercero, indícalo: no se crea login propio y el representante elegirá a quién declarar al inicio de sesión.
    </p>

    <form method="post" action="<?= site_url('equity/grupo/persona') ?>" class="form-grid">
        <?= csrf_field() ?>
        <label for="empresa_id">Empresa *</label>
        <select id="empresa_id" name="empresa_id" required>
            <?php foreach ($grupoIds as $gid): ?>
                <?php $e = $empresasMap[$gid] ?? null; if (! is_array($e)) continue; ?>
                <option value="<?= esc($gid) ?>"><?= esc($e['nombre'] ?? $gid) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="form-grid-2">
            <div>
                <label for="nombre">Nombre *</label>
                <input id="nombre" name="nombre" type="text" required>
            </div>
            <div>
                <label for="apellido">Apellido</label>
                <input id="apellido" name="apellido" type="text">
            </div>
        </div>

        <label for="email">Correo (obligatorio si no es representado)</label>
        <input id="email" type="email" name="email">

        <div class="form-grid-2">
            <div>
                <label for="rut">RUT</label>
                <input id="rut" name="rut" type="text">
            </div>
            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="socio">Socio</option>
                    <option value="gerente">Gerente</option>
                    <option value="familiar">Familiar</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
        </div>

        <label for="representado_por_usuario_id">Lo representa un tercero (usuario existente)</label>
        <select id="representado_por_usuario_id" name="representado_por_usuario_id">
            <option value="">— No, tendrá su propio acceso —</option>
            <?php foreach ($usuariosGrupo as $u): ?>
                <option value="<?= esc($u['id']) ?>"><?= esc($u['nombre'] ?? $u['email'] ?? $u['id']) ?> · <?= esc($u['email'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">Inscribir e invitar</button>
    </form>
</section>

<section class="card-panel">
    <h2 style="margin-top:0">Personas del grupo</h2>
    <p class="muted" style="margin-top:0">
        Baja = desactiva acceso y marca la identidad. Anonimizar = borra PII (nombre/RUT/correo) y deja solo el ID para el patrimonio histórico.
    </p>
    <?php if (($personas ?? []) === []): ?>
        <p class="muted">Sin personas aún.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Empresa</th>
                    <th>Correo</th>
                    <th>Estado</th>
                    <th>Acceso</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($personas as $p): ?>
                    <?php
                    $emp = $empresasMap[$p['empresa_id'] ?? ''] ?? null;
                    $rep = $usuariosGrupo[$p['representado_por_usuario_id'] ?? ''] ?? null;
                    $own = $usuariosGrupo[$p['usuario_id'] ?? ''] ?? null;
                    $scrubbed = ! empty($p['pii_scrubbed_at']);
                    $baja = $scrubbed || ! empty($p['deactivated_at']) || (int) ($p['activo'] ?? 1) !== 1;
                    $acceso = $scrubbed
                        ? 'Anonimizado'
                        : ($rep
                            ? ('Representado por ' . ($rep['nombre'] ?? $rep['email'] ?? ''))
                            : ($own ? ('Usuario: ' . ($own['email'] ?? '')) : 'Sin usuario'));
                    $estado = $scrubbed ? 'Anonimizada' : ($baja ? 'Dada de baja' : 'Activa');
                    ?>
                    <tr>
                        <td><?= esc(IdentityVault::labelPersona($p)) ?></td>
                        <td><?= esc(IdentityVault::labelEmpresa(is_array($emp) ? $emp : null)) ?></td>
                        <td><?= esc($scrubbed ? '—' : ($p['email'] ?? '—')) ?></td>
                        <td><?= esc($estado) ?></td>
                        <td><?= esc($acceso) ?></td>
                        <td style="white-space:nowrap">
                            <?php if ($scrubbed): ?>
                                <span class="muted">—</span>
                            <?php elseif (! $baja): ?>
                                <form method="post" action="<?= site_url('equity/grupo/persona/' . $p['id'] . '/baja') ?>" style="display:inline" onsubmit="return confirm('¿Dar de baja a esta persona? Conservará el patrimonio por ID.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit">Baja</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= site_url('equity/grupo/persona/' . $p['id'] . '/scrub') ?>" style="display:inline" onsubmit="return confirm('¿Anonimizar PII de forma irreversible? Esta acción no se puede deshacer.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit">Anonimizar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?= $this->endSection() ?>
