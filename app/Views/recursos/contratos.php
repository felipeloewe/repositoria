<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Recursos · Contratos</h2>
    <p>Entidades marcadas en <strong>Servicios</strong>. Asigna un contrato ya existente del menú Contratos o sube uno nuevo (queda en Contratos y vinculado aquí).</p>
</section>

<?= view('recursos/_tabs', ['tab' => 'contratos']) ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if ($entidades === []): ?>
    <div class="card section-card">
        <p class="muted">No hay entidades marcadas. Ve a <a href="<?= site_url('recursos') ?>">Servicios</a> y activa las que correspondan.</p>
    </div>
<?php else: ?>
    <?php
    $focus = $focus ?? '';
    $pool  = $contratosPool ?? [];
    ?>
    <?php foreach ($entidades as $row): ?>
        <?php
        $eid = $row['entidad_id'];
        $linkedIds = array_map(static fn ($c) => (string) $c['id'], $row['contratos']);
        $disponibles = array_values(array_filter(
            $pool,
            static fn (array $d): bool => ! in_array((string) $d['id'], $linkedIds, true)
        ));
        $open = $focus !== '' && $focus === $eid;
        ?>
        <div class="card section-card recurso-contrato-card <?= $open ? 'is-focus' : '' ?>" id="ent-<?= esc($eid) ?>">
            <h3 style="margin-bottom:.25rem"><?= esc($row['entidad_nombre']) ?></h3>
            <p class="muted" style="margin:0 0 .85rem;font-size:.9rem">
                <?= esc($row['agrupacion_nombre']) ?> · <?= esc($row['servicio_nombre']) ?>
            </p>

            <h4 style="font-size:.95rem;margin:.5rem 0 .4rem">Contratos vinculados (<?= count($row['contratos']) ?>)</h4>
            <?php if ($row['contratos'] === []): ?>
                <p class="muted" style="margin-bottom:.75rem">Ninguno aún.</p>
            <?php else: ?>
                <table class="table" style="margin-bottom:1rem">
                    <thead><tr><th>Título</th><th>Archivo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($row['contratos'] as $doc): ?>
                        <tr>
                            <td><a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a></td>
                            <td class="muted"><?= esc($doc['nombre_archivo'] ?? '') ?></td>
                            <td>
                                <form method="post" action="<?= site_url('recursos/contratos/unassign') ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="entidad_id" value="<?= esc($eid) ?>">
                                    <input type="hidden" name="documento_id" value="<?= esc($doc['id']) ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit">Quitar vínculo</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="stack-forms" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div class="card" style="margin:0;box-shadow:none;border:1px solid rgba(47,50,96,.08)">
                    <h4 style="margin-top:0">Asignar contrato existente</h4>
                    <?php if ($disponibles === []): ?>
                        <p class="muted">No hay contratos libres en el menú Contratos, o ya están todos asignados a esta entidad.
                            <a href="<?= site_url('contratos') ?>">Ver Contratos</a></p>
                    <?php else: ?>
                        <form method="post" action="<?= site_url('recursos/contratos/assign') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entidad_id" value="<?= esc($eid) ?>">
                            <label>Contrato</label>
                            <select name="documento_id" required>
                                <?php foreach ($disponibles as $d): ?>
                                    <option value="<?= esc($d['id']) ?>"><?= esc($d['titulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.5rem">Asignar</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin:0;box-shadow:none;border:1px solid rgba(47,50,96,.08)">
                    <h4 style="margin-top:0">Subir contrato nuevo</h4>
                    <?php if (empty($canUpload)): ?>
                        <p class="muted">Necesitas un <a href="<?= site_url('documentos/tipos') ?>">tipo de documento</a> activo.</p>
                    <?php else: ?>
                        <form method="post" action="<?= site_url('recursos/contratos/upload') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entidad_id" value="<?= esc($eid) ?>">
                            <label>Título</label>
                            <input type="text" name="titulo" required value="Contrato <?= esc($row['entidad_nombre']) ?>">
                            <label>Tipo</label>
                            <select name="tipo_id" required>
                                <?php foreach ($tiposActivos as $t): ?>
                                    <option value="<?= esc($t['id']) ?>"><?= esc($t['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>Archivo (máx. 15 MB)</label>
                            <input type="file" name="archivo" required>
                            <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.5rem">Subir y vincular</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= $this->endSection() ?>
