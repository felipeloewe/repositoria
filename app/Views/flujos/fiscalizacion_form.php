<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Nueva solicitud de fiscalización</h2>
    <p>Incluí los documentos a enviar/exportar. Master debe aprobar antes del envío.</p>
</section>

<?php if (! empty($error)): ?><div class="alert alert-error"><?= esc($error) ?></div><?php endif; ?>

<div class="card section-card">
    <form method="post" action="<?= site_url('flujos/fiscalizacion') ?>">
        <?= csrf_field() ?>
        <label>Título</label>
        <input type="text" name="titulo" required>
        <label>Tipo</label>
        <select name="tipo" required>
            <option value="LIQUIDACION">Liquidación</option>
            <option value="CONTRATO">Contrato</option>
        </select>
        <label>Motivo</label>
        <input type="text" name="motivo">
        <label>Departamento</label>
        <select name="departamento_id">
            <option value="">—</option>
            <?php foreach ($deptos as $d): ?>
                <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Sucursal</label>
        <select name="sucursal_id">
            <option value="">—</option>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= esc($s['id']) ?>"><?= esc($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <label>Documentos</label>
        <?php foreach ($docs as $doc): ?>
            <label style="font-weight:500;display:flex;gap:.5rem;align-items:center;margin-bottom:.35rem">
                <input type="checkbox" name="documento_id[]" value="<?= esc($doc['id']) ?>" style="width:auto;margin:0">
                <?= esc($doc['titulo']) ?>
            </label>
        <?php endforeach; ?>
        <?php if ($docs === []): ?>
            <p class="muted">No hay documentos visibles para adjuntar.</p>
        <?php endif; ?>
        <label style="display:flex;gap:.5rem;align-items:center;margin:1rem 0;font-weight:500">
            <input type="checkbox" name="submit_now" value="1" checked style="width:auto;margin:0">
            Enviar a revisión Master ahora
        </label>
        <button class="btn btn-primary" type="submit">Crear</button>
    </form>
</div>
<?= $this->endSection() ?>
