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
        <?php
        $personaActiva = null;
        foreach ($personas as $p) {
            if (($p['id'] ?? '') === $personaId) {
                $personaActiva = $p;
                break;
            }
        }
        if ($personaActiva) {
            echo ' · ' . esc(trim(($personaActiva['nombre'] ?? '') . ' ' . ($personaActiva['apellido'] ?? '')));
        } elseif ($personaId === '') {
            echo ' · sin persona de sesión (admin)';
        }
        ?>.
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Reporte · Balance</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/activos') ?>">Reporte · Activos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/pasivos') ?>">Reporte · Pasivos</a>
    </p>
</section>

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
        <div class="muted" style="font-size:.8rem;margin-top:.35rem">Solo deuda directa</div>
    </div>
    <div class="card">
        <h3>Patrimonio neto</h3>
        <div class="value" style="font-size:1.35rem"><?= esc(EquityUf::fmtPesos((float) $totales['patrimonio'], 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($totalesUf['patrimonio'] ?? 0), 2)) ?></div>
    </div>
</div>

<div class="card section-card">
    <h3>Detalle · Activos y pasivos directos</h3>
    <table class="table">
        <thead>
            <tr><th>Concepto</th><th>UF</th><th>$ (UF vigente)</th><th></th></tr>
        </thead>
        <tbody>
            <?php
            $filasDirectas = [
                ['Activos — Bienes raíces', 'bienes_raices', 'equity/activos/bienes-raices'],
                ['Activos — Vehículos', 'vehiculos', 'equity/activos/vehiculos'],
                ['Activos — Sociedades', 'sociedades', 'equity/activos/sociedades'],
                ['Activos — Otros', 'otros_activos', 'equity/activos/otros'],
                ['Pasivos — Hipotecarios', 'pasivos_inm', 'equity/pasivos/inmuebles'],
                ['Pasivos — Circulantes', 'pasivos_circ', 'equity/pasivos/circulantes'],
                ['Pasivos — Otros', 'pasivos_otro', 'equity/pasivos/otros'],
            ];
            foreach ($filasDirectas as [$label, $key, $path]):
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

<div class="card section-card" style="margin-top:1rem">
    <h3>Referencias (no deuda directa)</h3>
    <p class="muted" style="margin-top:0">
        Deuda indirecta y líneas disponibles no son deuda directa de la persona; se muestran como referencia y <strong>no restan</strong> del patrimonio neto.
    </p>
    <table class="table">
        <thead>
            <tr><th>Concepto</th><th>UF</th><th>$ (UF vigente)</th><th></th></tr>
        </thead>
        <tbody>
            <?php
            $filasRef = [
                ['Deuda indirecta (codeudor / fiador / aval)', 'pasivos_indirecta', 'equity/pasivos/indirecta'],
                ['Líneas disponibles (cupos)', 'pasivos_lineas', 'equity/pasivos/lineas'],
            ];
            foreach ($filasRef as [$label, $key, $path]):
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
