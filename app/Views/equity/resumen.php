<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$totalesUf = $totalesUf ?? [];
$ufVigente = $ufVigente ?? null;
$ufRate = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
?>

<section class="banner">
    <h2>Resumen patrimonial</h2>
    <p>
        Totales en UF (fijas) y equivalente en $ con UF vigente
        <?= $ufVigente ? esc(EquityUf::fmtPesos($ufRate, 2)) . ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>
        <?= $personaId !== '' ? ' · filtrado por persona' : '' ?>.
    </p>
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
        <div class="value" style="font-size:1.35rem"><?= esc(EquityUf::fmtPesos((float) $totales['activos'], 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($totalesUf['activos'] ?? 0), 2)) ?></div>
    </div>
    <div class="card">
        <h3>Total pasivos</h3>
        <div class="value" style="font-size:1.35rem"><?= esc(EquityUf::fmtPesos((float) $totales['pasivos'], 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($totalesUf['pasivos'] ?? 0), 2)) ?></div>
    </div>
    <div class="card">
        <h3>Patrimonio neto</h3>
        <div class="value" style="font-size:1.35rem"><?= esc(EquityUf::fmtPesos((float) $totales['patrimonio'], 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($totalesUf['patrimonio'] ?? 0), 2)) ?></div>
    </div>
</div>

<div class="card section-card">
    <h3>Detalle</h3>
    <table class="table">
        <thead>
            <tr><th>Concepto</th><th>UF</th><th>$ (UF vigente)</th><th></th></tr>
        </thead>
        <tbody>
            <?php
            $filas = [
                ['Activos — Bienes raíces', 'bienes_raices', 'equity/activos/bienes-raices'],
                ['Activos — Vehículos', 'vehiculos', 'equity/activos/vehiculos'],
                ['Activos — Sociedades', 'sociedades', 'equity/activos/sociedades'],
                ['Activos — Otros', 'otros_activos', 'equity/activos/otros'],
                ['Pasivos — Inmuebles', 'pasivos_inm', 'equity/pasivos/inmuebles'],
                ['Pasivos — Circulantes', 'pasivos_circ', 'equity/pasivos/circulantes'],
                ['Pasivos — Otros', 'pasivos_otro', 'equity/pasivos/otros'],
            ];
            foreach ($filas as [$label, $key, $path]):
            ?>
            <tr>
                <td><?= esc($label) ?></td>
                <td><?= esc(EquityUf::fmtUf((float) ($totalesUf[$key] ?? 0), 2)) ?></td>
                <td><?= esc(EquityUf::fmtPesos((float) ($totales[$key] ?? 0), 0)) ?></td>
                <td><a class="btn btn-ghost btn-sm" href="<?= site_url($path) ?>">Abrir</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
