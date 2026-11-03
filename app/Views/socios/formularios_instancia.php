<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2><?= esc($instancia['titulo']) ?></h2>
    <p>
        Plantilla <?= esc($plantilla['nombre'] ?? '') ?>
        · origen <?= esc($instancia['origen'] ?? '') ?>
        · <?= esc($instancia['completed_at'] ?? '') ?>
    </p>
</section>

<?= $this->include('socios/_tabs', ['tab' => 'formularios']) ?>

<p class="muted"><a href="<?= site_url('socios/formularios') ?>">← Formularios</a>
<?php if (! empty($instancia['documento_id'])): ?>
    · <a href="<?= site_url('documentos/' . $instancia['documento_id']) ?>">Abrir PDF</a>
    · <a href="<?= site_url('documentos/' . $instancia['documento_id'] . '/download') ?>">Descargar</a>
<?php endif; ?>
</p>

<div class="card section-card">
    <h3>Respuestas</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Campo</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($campos as $c): ?>
            <?php
                $val = $respuestas[$c['clave']] ?? null;
                if (is_array($val)) {
                    $shown = ($val['nombre'] ?? 'archivo') . (! empty($val['documento_id']) ? ' → doc' : '');
                } else {
                    $shown = $val === null || $val === '' ? '—' : (string) $val;
                }
            ?>
            <tr>
                <td><?= esc($c['etiqueta']) ?></td>
                <td>
                    <?= esc($shown) ?>
                    <?php if (is_array($val) && ! empty($val['documento_id'])): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $val['documento_id']) ?>">Ver adjunto</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
