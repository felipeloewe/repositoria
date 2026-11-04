<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Recursos</h2>
    <p>Agrupaciones genéricas de <strong>servicios</strong> y <strong>entidades</strong> prestadoras. Marca la agrupación y las entidades vigentes; luego vincula contratos en la pestaña Contratos.</p>
</section>

<?= view('recursos/_tabs', ['tab' => 'servicios']) ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<p class="muted" style="margin:.25rem 0 1rem">
    Pestaña <strong>Servicios</strong>: catálogo ON/OFF. Las entidades marcadas aparecen en <a href="<?= site_url('recursos/contratos') ?>">Contratos</a> para asignar o subir archivos.
</p>

<?php foreach ($agrupaciones as $agr): ?>
    <div class="card section-card recurso-agrupacion <?= ! empty($agr['marcado']) ? 'is-marked' : '' ?>">
        <div class="recurso-agrupacion-head">
            <form method="post" action="<?= site_url('recursos/agrupacion/toggle') ?>" class="recurso-check-form">
                <?= csrf_field() ?>
                <input type="hidden" name="agrupacion_id" value="<?= esc($agr['id']) ?>">
                <input type="hidden" name="marcado" value="<?= ! empty($agr['marcado']) ? '0' : '1' ?>">
                <label class="recurso-check">
                    <input type="checkbox" <?= ! empty($agr['marcado']) ? 'checked' : '' ?>
                           onchange="this.form.submit()"
                           aria-label="Marcar agrupación <?= esc($agr['nombre']) ?>">
                    <span>
                        <strong><?= esc($agr['nombre']) ?></strong>
                        <span class="muted" style="font-size:.85rem;display:block">Agrupación · marca si aplica a esta empresa</span>
                    </span>
                </label>
            </form>
        </div>

        <?php foreach ($agr['servicios'] as $svc): ?>
            <div class="recurso-servicio">
                <h4><?= esc($svc['nombre']) ?></h4>
                <div class="recurso-entidades">
                    <?php foreach ($svc['entidades'] as $ent): ?>
                        <form method="post" action="<?= site_url('recursos/entidad/toggle') ?>" class="recurso-check-form recurso-entidad">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entidad_id" value="<?= esc($ent['id']) ?>">
                            <input type="hidden" name="marcado" value="<?= ! empty($ent['marcado']) ? '0' : '1' ?>">
                            <label class="recurso-check recurso-check--entity">
                                <input type="checkbox" <?= ! empty($ent['marcado']) ? 'checked' : '' ?>
                                       onchange="this.form.submit()"
                                       aria-label="Marcar entidad <?= esc($ent['nombre']) ?>">
                                <span><?= esc($ent['nombre']) ?></span>
                            </label>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
