<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\DocumentLifecycle;
$estado = DocumentLifecycle::normalizarEstado($doc['estado'] ?? null);
$cadena = $cadena ?? [];
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
    <?php
        $ambitoDoc = \App\Libraries\DocumentAcl::normalizeAmbito($doc['ambito'] ?? null);
        $volverHref = \App\Libraries\DocumentAcl::listPathForAmbito($ambitoDoc);
        $volverLabel = match ($ambitoDoc) {
            \App\Libraries\DocumentAcl::AMBITO_SOCIO          => '← Volver a Socios',
            \App\Libraries\DocumentAcl::AMBITO_CONTRATO       => '← Volver a Contratos',
            \App\Libraries\DocumentAcl::AMBITO_SOCIO_CONTRATO => '← Volver a Contratos de socios',
            default                                            => '← Volver al listado',
        };
    ?>
    <a href="<?= site_url(ltrim($volverHref, '/')) ?>"><?= esc($volverLabel) ?></a>
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
                <label>Vence</label>
                <div><?= esc($doc['vence_at'] ?? '—') ?></div>
            </div>
            <div>
                <label>Alerta (días)</label>
                <div><?= esc($doc['alerta_dias'] ?? '—') ?></div>
            </div>
        <?php endif; ?>
        <div>
            <label>Versión actual</label>
            <div><strong>v<?= (int) ($doc['version_actual'] ?? 1) ?></strong></div>
        </div>
        <div>
            <label>Archivo</label>
            <div class="muted" style="font-size:.85rem"><?= esc($doc['nombre_archivo'] ?? '') ?></div>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
        </div>
    </div>
</div>

<div class="card section-card">
    <h3>Cabecera</h3>
    <?php if (! empty($canMutate)): ?>
    <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/cabecera') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr;align-items:end">
            <div>
                <label for="cab_asunto">Asunto</label>
                <input id="cab_asunto" type="text" name="cabecera_asunto" value="<?= esc($doc['cabecera_asunto'] ?? '') ?>">
            </div>
            <div>
                <label for="cab_partes">Partes</label>
                <input id="cab_partes" type="text" name="cabecera_partes" value="<?= esc($doc['cabecera_partes'] ?? '') ?>">
            </div>
            <div>
                <label for="cab_inicio">Inicio</label>
                <input id="cab_inicio" type="date" name="cabecera_inicio" value="<?= esc($doc['cabecera_inicio'] ?? '') ?>">
            </div>
            <div>
                <label for="cab_termino">Término</label>
                <input id="cab_termino" type="date" name="cabecera_termino" value="<?= esc($doc['cabecera_termino'] ?? $doc['fecha_termino'] ?? '') ?>">
            </div>
        </div>
        <label for="cab_auth">Autorizantes</label>
        <input id="cab_auth" type="text" name="cabecera_autorizantes" value="<?= esc($doc['cabecera_autorizantes'] ?? '') ?>" placeholder="Nombres o roles que autorizan">
        <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Guardar cabecera</button>
    </form>
    <?php else: ?>
        <dl class="cabecera-dl">
            <div><dt>Asunto</dt><dd><?= esc($doc['cabecera_asunto'] ?? '—') ?></dd></div>
            <div><dt>Partes</dt><dd><?= esc($doc['cabecera_partes'] ?? '—') ?></dd></div>
            <div><dt>Inicio</dt><dd><?= esc($doc['cabecera_inicio'] ?? '—') ?></dd></div>
            <div><dt>Término</dt><dd><?= esc($doc['cabecera_termino'] ?? $doc['fecha_termino'] ?? '—') ?></dd></div>
            <div><dt>Autorizantes</dt><dd><?= esc($doc['cabecera_autorizantes'] ?? '—') ?></dd></div>
        </dl>
    <?php endif; ?>
</div>

<?php
$canRequestCiclo = \App\Libraries\DocumentoSolicitudService::canRequestForDoc($memb, $doc);
?>
<?php if (! empty($isMaster) && $estado !== DocumentLifecycle::ARCHIVADO): ?>
<div class="card section-card">
    <h3>Archivar / desactivar</h3>
    <p class="muted">Mueve el archivo a <code>{empresa}/archivados/</code> y lo saca del listado activo. Luego puedes respaldarlo en ZIP mensual. Las solicitudes de no-Master se validan en Documentos → Archivado.</p>
    <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/archivar') ?>" onsubmit="return confirm('¿Archivar este documento y mover el archivo a archivados/?')">
        <?= csrf_field() ?>
        <label for="nota_arch">Nota</label>
        <input id="nota_arch" type="text" name="nota" value="Archivado por administración">
        <button class="btn btn-sm" type="submit" style="margin-top:.75rem;background:#fee2e2;color:#991b1b">Archivar documento</button>
    </form>
</div>
<?php elseif ($canRequestCiclo && $estado !== DocumentLifecycle::ARCHIVADO): ?>
<div class="card section-card">
    <h3>Solicitar archivo o baja</h3>
    <p class="muted">Como no eres Master, la acción queda pendiente de validación (Archivo: Master/Jefe · Baja: Master + ZIP descargado).</p>
    <div class="form-row">
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/solicitar-ciclo') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="ARCHIVO">
            <label>Nota archivo
                <input type="text" name="nota" placeholder="Motivo (opcional)">
            </label>
            <button class="btn btn-primary btn-sm" type="submit">Solicitar archivo</button>
        </form>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/solicitar-ciclo') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="tipo" value="BAJA">
            <label>Nota baja
                <input type="text" name="nota" placeholder="Motivo (opcional)">
            </label>
            <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Solicitar baja</button>
        </form>
    </div>
</div>
<?php elseif ($estado === DocumentLifecycle::ARCHIVADO): ?>
<div class="alert alert-info">
    Documento archivado<?= ! empty($doc['archivado_at']) ? ' el ' . esc($doc['archivado_at']) : '' ?>.
    <?php if (! empty($doc['blob_eliminado_at'])): ?>
        Blob eliminado tras respaldo ZIP (<?= esc($doc['blob_eliminado_at']) ?>).
    <?php else: ?>
        Archivo en disco bajo <code>archivados/</code>.
        <a href="<?= site_url('documentos/archivados') ?>">Ver listado</a> ·
        <a href="<?= site_url('documentos/archivados/respaldo') ?>">Respaldo mensual</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($oficial): ?>

    <?php if (count($cadena) > 0): ?>
    <div class="card section-card">
        <h3>Hilo de renovación</h3>
        <p class="muted" style="margin-top:0">Cadena completa (como un hilo de correo): cada vencimiento abre el siguiente Vigente.</p>
        <ol class="cadena-list">
            <?php foreach ($cadena as $i => $c): ?>
                <?php $cEstado = DocumentLifecycle::normalizarEstado($c['estado'] ?? null); ?>
                <li class="<?= $c['id'] === $doc['id'] ? 'cadena-actual' : '' ?>">
                    <a href="<?= site_url('documentos/' . $c['id']) ?>">
                        #<?= $i + 1 ?> · <?= esc($c['titulo']) ?>
                    </a>
                    <span class="tag <?= esc(DocumentLifecycle::cssEstado($cEstado)) ?>">
                        <?= esc(DocumentLifecycle::labelEstado($cEstado)) ?>
                    </span>
                    <?php if (! empty($c['vence_at'])): ?>
                        <span class="muted">vence <?= esc($c['vence_at']) ?></span>
                    <?php endif; ?>
                    <?php if ($c['id'] === $doc['id']): ?>
                        <span class="tag tag-oficial">Este doc</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (! empty($canRenovar)): ?>
        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/renovar') ?>" class="form-row" style="grid-template-columns:auto auto 1fr;align-items:end;margin-top:1rem">
            <?= csrf_field() ?>
            <div>
                <label for="anios">Años de vigencia del nuevo</label>
                <input id="anios" type="number" min="1" name="anios_vigencia" value="1" style="width:5rem">
            </div>
            <div>
                <label for="pub">Estado inicial</label>
                <select id="pub" name="publicar">
                    <option value="0">En elaboración</option>
                    <option value="1">Vigente de inmediato</option>
                </select>
            </div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit">Renovar (crear siguiente en el hilo)</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
            Estados confirmados: En elaboración → Vigente → Vencido → Renovado/Archivado. Publicar/archivar/renovar: Master (o ACL de carga según acción). Preferir «Renovar» para el hilo.
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
            Stub: al abrir el detalle, si ya pasó la fecha y estaba Vigente → pasa a Vencido. Correo/cron después.
        </p>
    </div>
    <?php endif; ?>

<?php else: ?>

    <?php if (! empty($canMutate)): ?>
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
                        <span class="tag tag-oficial">Actual</span>
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

<?php if ($oficial): ?>
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
<?php endif; ?>

<?= $this->endSection() ?>
