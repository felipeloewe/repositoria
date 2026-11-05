<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($title) ?></h2>
    <p>
        <?php if ($tipo === \App\Libraries\DocumentoSolicitudService::TIPO_ARCHIVO): ?>
            Solicitudes de archivo hechas por usuarios sin perfil Master. Valida Master o Jefe.
        <?php else: ?>
            Solicitudes de baja. Solo Master valida, y debe existir un respaldo ZIP <strong>descargado</strong>.
            <?php if (! empty($zipOk)): ?>
                <span class="tag tag-oficial">ZIP descargado OK</span>
            <?php else: ?>
                <span class="tag">Sin ZIP descargado</span>
                — genera y descarga en <a href="<?= site_url('documentos/archivados/respaldo') ?>">Respaldo</a>.
            <?php endif; ?>
        <?php endif; ?>
    </p>
</section>

<?= $this->include('documentos/_ciclo_tabs') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Cola</h3>
    <?php if (empty($rows)): ?>
        <p class="muted">Sin solicitudes.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Solicitante</th>
                    <th>Estado</th>
                    <th>Notas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <strong><?= esc($r['doc_titulo'] ?? '(eliminado)') ?></strong>
                        <br><span class="muted"><?= esc($r['doc_ambito'] ?? '') ?> · <?= esc($r['doc_estado'] ?? '') ?></span>
                        <?php if (! empty($r['documento_id']) && ! empty($r['doc_titulo'])): ?>
                            <br><a href="<?= site_url('documentos/' . $r['documento_id']) ?>">Ver</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= esc($r['solicitante_nombre'] ?? '—') ?>
                        <br><span class="muted"><?= esc($r['created_at'] ?? '') ?></span>
                    </td>
                    <td>
                        <span class="tag"><?= esc(\App\Libraries\DocumentoSolicitudService::labelEstado((string) $r['estado'])) ?></span>
                        <?php if (! empty($r['validador_nombre'])): ?>
                            <br><span class="muted">por <?= esc($r['validador_nombre']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (! empty($r['nota_solicitud'])): ?>
                            <div><span class="muted">Solicitud:</span> <?= esc($r['nota_solicitud']) ?></div>
                        <?php endif; ?>
                        <?php if (! empty($r['nota_validacion'])): ?>
                            <div><span class="muted">Validación:</span> <?= esc($r['nota_validacion']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($r['estado'] ?? '') === 'PENDIENTE' && ! empty($canValidate)): ?>
                            <form method="post" action="<?= site_url('documentos/solicitudes/' . $r['id'] . '/resolver') ?>" style="margin-bottom:.4rem">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="aprobar">
                                <input type="text" name="nota" placeholder="Nota (opcional)" style="margin-bottom:.35rem">
                                <button class="btn btn-primary btn-sm" type="submit">Aprobar</button>
                            </form>
                            <form method="post" action="<?= site_url('documentos/solicitudes/' . $r['id'] . '/resolver') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="rechazar">
                                <input type="text" name="nota" placeholder="Motivo rechazo" style="margin-bottom:.35rem">
                                <button class="btn btn-ghost btn-sm" type="submit">Rechazar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
