<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\DocumentLifecycle;
$estado = DocumentLifecycle::normalizarEstado($doc['estado'] ?? null);
?>

<section class="banner">
    <h2><?= esc($doc['titulo']) ?></h2>
    <p>
        <?= esc($deptoNombre) ?> · <?= esc($seccionNombre) ?>
        <?php if ($tipo): ?>
            · Tipo <strong><?= esc($tipo['nombre']) ?></strong>
            (<?= esc(DocumentLifecycle::labelModalidad($tipo['modalidad'])) ?>)
        <?php endif; ?>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('documentos') ?>">← Volver al listado</a>
</div>

<div class="card section-card">
    <h3>Resumen</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:start">
        <?php if ($oficial): ?>
            <div>
                <label>Estado del ciclo</label>
                <div>
                    <span class="tag <?= esc(DocumentLifecycle::cssEstado($estado)) ?>">
                        <?= esc(DocumentLifecycle::labelEstado($estado)) ?>
                    </span>
                    <?php if (! empty($vencido)): ?>
                        <span class="tag tag-vencido">Vencido</span>
                    <?php elseif (! empty($porVencer)): ?>
                        <span class="tag tag-alerta">Por vencer</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label>Versión actual</label>
                <div><strong>v<?= (int) ($doc['version_actual'] ?? 1) ?></strong></div>
            </div>
            <div>
                <label>Vence</label>
                <div><?= esc($doc['vence_at'] ?? '—') ?></div>
            </div>
            <div>
                <label>Alerta (días)</label>
                <div><?= esc($doc['alerta_dias'] ?? '—') ?></div>
            </div>
        <?php else: ?>
            <div>
                <label>Fecha término</label>
                <div><?= esc($doc['fecha_termino'] ?? '—') ?></div>
            </div>
        <?php endif; ?>
        <div>
            <label>Archivo</label>
            <div class="muted" style="font-size:.85rem"><?= esc($doc['nombre_archivo'] ?? '') ?></div>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
        </div>
    </div>
</div>

<?php if ($oficial): ?>

    <?php if (! empty($canMutate) && $siguientes !== []): ?>
    <div class="card section-card">
        <h3>Ciclo de vida</h3>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/estado') ?>">
            <?= csrf_field() ?>
            <div class="form-row" style="grid-template-columns:1fr 1.4fr auto;align-items:end">
                <div>
                    <label for="nuevo_estado">Nuevo estado</label>
                    <select id="nuevo_estado" name="estado" required>
                        <?php foreach ($siguientes as $e): ?>
                            <option value="<?= esc($e) ?>"><?= esc(DocumentLifecycle::labelEstado($e)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nota_estado">Nota (opcional)</label>
                    <input id="nota_estado" type="text" name="nota" placeholder="Motivo del cambio">
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" type="submit">Cambiar estado</button>
                </div>
            </div>
        </form>
        <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
            Publicar y archivar requieren rol Master. Estados del ciclo son provisionales (distintos del catálogo de tipos).
        </p>
    </div>
    <?php endif; ?>

    <?php if (! empty($canMutate)): ?>
    <div class="card section-card">
        <h3>Vencimiento y alerta</h3>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/vencimiento') ?>">
            <?= csrf_field() ?>
            <div class="form-row" style="grid-template-columns:1fr 1fr auto;align-items:end">
                <div>
                    <label for="vence_at">Fecha de vencimiento</label>
                    <input id="vence_at" type="date" name="vence_at" value="<?= esc($doc['vence_at'] ?? '') ?>">
                </div>
                <div>
                    <label for="alerta_dias">Alertar con anticipación (días)</label>
                    <input id="alerta_dias" type="number" min="0" name="alerta_dias" value="<?= esc($doc['alerta_dias'] ?? '30') ?>">
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                </div>
            </div>
        </form>
        <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
            Stub: la alerta se muestra en UI (badge «Por vencer»); el envío por correo queda para una fase posterior.
        </p>
    </div>
    <?php endif; ?>

    <?php if (! empty($canMutate) && DocumentLifecycle::puedeVersionar($estado)): ?>
    <div class="card section-card">
        <h3>Nueva versión</h3>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/version') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <label for="ver_file">Archivo</label>
            <input id="ver_file" type="file" name="archivo" required style="margin-bottom:.75rem">
            <label for="ver_notas">Notas del cambio</label>
            <input id="ver_notas" type="text" name="notas" placeholder="Qué cambió en esta versión">
            <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Publicar versión</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card section-card">
        <h3>Historial de versiones</h3>
        <?php if ($versiones === []): ?>
            <p class="muted">Sin versiones registradas.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Versión</th>
                    <th>Archivo</th>
                    <th>Notas</th>
                    <th>Por</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($versiones as $v): ?>
                <tr>
                    <td><strong>v<?= (int) $v['version_num'] ?></strong>
                        <?php if ((int) $v['version_num'] === (int) ($doc['version_actual'] ?? 0)): ?>
                            <span class="tag tag-vigente">Actual</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= esc($v['nombre_archivo'] ?? '') ?></td>
                    <td><?= esc($v['notas'] ?? '—') ?></td>
                    <td><?= esc($actorMap[$v['creado_por_id'] ?? ''] ?? '—') ?></td>
                    <td class="muted"><?= esc($v['created_at'] ?? '') ?></td>
                    <td>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/versiones/' . $v['id'] . '/download') ?>">Descargar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card section-card">
        <h3>Historial de estados</h3>
        <?php if ($historial === []): ?>
            <p class="muted">Sin transiciones registradas.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>De</th>
                    <th>A</th>
                    <th>Nota</th>
                    <th>Actor</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= $h['estado_desde'] ? esc(DocumentLifecycle::labelEstado($h['estado_desde'])) : '—' ?></td>
                    <td><?= esc(DocumentLifecycle::labelEstado($h['estado_hasta'])) ?></td>
                    <td><?= esc($h['nota'] ?? '—') ?></td>
                    <td><?= esc($actorMap[$h['actor_id'] ?? ''] ?? '—') ?></td>
                    <td class="muted"><?= esc($h['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php if (! empty($canMutate)): ?>
    <div class="card section-card">
        <h3>Fecha de término</h3>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/termino') ?>">
            <?= csrf_field() ?>
            <div class="form-row" style="grid-template-columns:1fr auto;align-items:end">
                <div>
                    <label for="fecha_termino">Fin del borrador</label>
                    <input id="fecha_termino" type="date" name="fecha_termino" value="<?= esc($doc['fecha_termino'] ?? '') ?>">
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card section-card">
        <h3>Registrar cambio</h3>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/cambio') ?>">
            <?= csrf_field() ?>
            <label for="cam_resumen">Resumen</label>
            <input id="cam_resumen" type="text" name="resumen" required placeholder="Qué se modificó">
            <label for="cam_detalle">Detalle</label>
            <textarea id="cam_detalle" name="detalle" rows="3" placeholder="Opcional"></textarea>
            <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Agregar al control de cambios</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card section-card">
        <h3>Control de cambios</h3>
        <?php if ($cambios === []): ?>
            <p class="muted">Sin entradas aún.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Resumen</th>
                    <th>Detalle</th>
                    <th>Actor</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cambios as $c): ?>
                <tr>
                    <td><strong><?= esc($c['resumen']) ?></strong></td>
                    <td class="muted"><?= esc($c['detalle'] ?? '—') ?></td>
                    <td><?= esc($actorMap[$c['actor_id'] ?? ''] ?? '—') ?></td>
                    <td class="muted"><?= esc($c['created_at'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card section-card">
        <h3>Modalidad borrador</h3>
        <p class="muted">
            Este tipo no usa ciclo de vida oficial, versionamiento ni vencimientos.
            Solo control de cambios y fecha de término.
        </p>
    </div>

<?php endif; ?>

<?= $this->endSection() ?>
