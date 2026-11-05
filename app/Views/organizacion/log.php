<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Organización · Log</h2>
    <p>Historial de solicitudes de <strong>archivo</strong> y <strong>baja</strong> (quién pidió, quién validó, resultado).</p>
</section>

<?= $this->include('organizacion/_tabs') ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Eventos</h3>
    <?php if (empty($eventos)): ?>
        <p class="muted">Aún no hay solicitudes registradas.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Documento</th>
                    <th>Solicitante</th>
                    <th>Estado</th>
                    <th>Validador</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($eventos as $e): ?>
                <tr>
                    <td><?= esc($e['created_at'] ?? '') ?></td>
                    <td><?= esc(\App\Libraries\DocumentoSolicitudService::labelTipo((string) ($e['tipo'] ?? ''))) ?></td>
                    <td><?= esc($e['doc_titulo'] ?? $e['documento_id'] ?? '—') ?></td>
                    <td><?= esc($e['solicitante_nombre'] ?? '—') ?></td>
                    <td>
                        <?= esc(\App\Libraries\DocumentoSolicitudService::labelEstado((string) ($e['estado'] ?? ''))) ?>
                        <?php if (! empty($e['validado_at'])): ?>
                            <br><span class="muted"><?= esc($e['validado_at']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($e['validador_nombre'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
