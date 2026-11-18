<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
$fmt = static function (float $v): string {
    return '$' . number_format($v, 0, ',', '.');
};
?>

<section class="banner">
    <h2>Resumen patrimonial</h2>
    <p>Totales de activos, pasivos y patrimonio neto<?= $personaId !== '' ? ' filtrados por persona' : '' ?>.</p>
</section>

<div class="card section-card">
    <form method="get" action="<?= site_url('equity/resumen') ?>" class="form-row" style="grid-template-columns:1fr auto">
        <div>
            <label for="persona_id">Persona</label>
            <select id="persona_id" name="persona_id">
                <option value="">Todas</option>
                <?php foreach ($personas as $p): ?>
                    <option value="<?= esc($p['id']) ?>" <?= $personaId === $p['id'] ? 'selected' : '' ?>>
                        <?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?>
                        (<?= esc(\App\Libraries\EquityAcl::labelTipo((string) ($p['tipo'] ?? 'socio'))) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<div class="grid">
    <div class="card">
        <h3>Total activos</h3>
        <div class="value" style="font-size:1.35rem"><?= esc($fmt((float) $totales['activos'])) ?></div>
    </div>
    <div class="card">
        <h3>Total pasivos</h3>
        <div class="value" style="font-size:1.35rem"><?= esc($fmt((float) $totales['pasivos'])) ?></div>
    </div>
    <div class="card">
        <h3>Patrimonio neto</h3>
        <div class="value" style="font-size:1.35rem"><?= esc($fmt((float) $totales['patrimonio'])) ?></div>
    </div>
</div>

<div class="card section-card">
    <h3>Detalle</h3>
    <table class="table">
        <thead>
            <tr><th>Concepto</th><th>Valor CLP</th><th></th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Activos — Bienes raíces</td>
                <td><?= esc($fmt((float) $totales['bienes_raices'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/bienes-raices') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Activos — Vehículos</td>
                <td><?= esc($fmt((float) $totales['vehiculos'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/vehiculos') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Activos — Sociedades</td>
                <td><?= esc($fmt((float) $totales['sociedades'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/sociedades') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Activos — Otros</td>
                <td><?= esc($fmt((float) $totales['otros_activos'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/otros') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Pasivos — Inmuebles</td>
                <td><?= esc($fmt((float) $totales['pasivos_inm'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/pasivos/inmuebles') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Pasivos — Circulantes</td>
                <td><?= esc($fmt((float) $totales['pasivos_circ'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/pasivos/circulantes') ?>">Abrir</a></td>
            </tr>
            <tr>
                <td>Pasivos — Otros</td>
                <td><?= esc($fmt((float) $totales['pasivos_otro'])) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url('equity/pasivos/otros') ?>">Abrir</a></td>
            </tr>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
