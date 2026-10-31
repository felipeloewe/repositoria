<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Flujos</h2>
    <p>Fiscalización autorizada por Master y canales seguros para externos.</p>
</section>

<div class="grid">
    <div class="card">
        <h3>Fiscalización</h3>
        <div class="value"><?= (int) $fisCount ?></div>
        <div class="hint"><?= (int) $pendientes ?> pendientes de revisión</div>
        <?php if ($canRequest || $canReview): ?>
            <p style="margin-top:.75rem"><a class="btn btn-primary btn-sm" href="<?= site_url('flujos/fiscalizacion') ?>">Ver solicitudes</a></p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Canales seguros</h3>
        <div class="value"><?= (int) $canalCount ?></div>
        <div class="hint"><?= $isExternal ? 'Tus paquetes activos' : 'Paquetes de la empresa' ?></div>
        <?php if ($canCanales): ?>
            <p style="margin-top:.75rem"><a class="btn btn-primary btn-sm" href="<?= site_url('flujos/canales') ?>">Abrir canales</a></p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Reglas</h3>
        <p class="muted" style="margin:0;font-size:.9rem">
            Sin OK Master no hay envío/exportación. Paquetes revocables para Contador / Abogado / Prevencionista.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
