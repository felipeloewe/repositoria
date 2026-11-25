<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static fn ($v): string => EquityUf::fmtPesos((float) ($v ?? 0), 0);
$fmtU = static fn ($v): string => EquityUf::fmtUf((float) ($v ?? 0), 2);

$activosLines = [
    ['Bienes raíces', $fmtP($totales['bienes_raices'] ?? 0), $fmtU($totalesUf['bienes_raices'] ?? 0)],
    ['Vehículos', $fmtP($totales['vehiculos'] ?? 0), $fmtU($totalesUf['vehiculos'] ?? 0)],
    ['Sociedades', $fmtP($totales['sociedades'] ?? 0), $fmtU($totalesUf['sociedades'] ?? 0)],
    ['Otros activos', $fmtP($totales['otros_activos'] ?? 0), $fmtU($totalesUf['otros_activos'] ?? 0)],
];
$pasivosLines = [
    ['Hipotecarios', $fmtP($totales['pasivos_inm'] ?? 0), $fmtU($totalesUf['pasivos_inm'] ?? 0)],
    ['Circulantes', $fmtP($totales['pasivos_circ'] ?? 0), $fmtU($totalesUf['pasivos_circ'] ?? 0)],
    ['Otros pasivos', $fmtP($totales['pasivos_otro'] ?? 0), $fmtU($totalesUf['pasivos_otro'] ?? 0)],
];
$padAp = max(count($activosLines), count($pasivosLines));

$ingresosLines = [];
foreach ($arriendosDetalle ?? [] as $row) {
    $ingresosLines[] = ['Arriendo · ' . ($row['concepto'] ?? ''), $fmtP($row['monto'] ?? 0)];
}
foreach ($ingresosDetalle ?? [] as $row) {
    $lab = (string) ($row['concepto'] ?? '');
    if (! empty($row['persona'])) {
        $lab .= ' · ' . trim(($row['persona']['nombre'] ?? '') . ' ' . ($row['persona']['apellido'] ?? ''));
    }
    $ingresosLines[] = [$lab, $fmtP($row['monto'] ?? 0)];
}
$egresosLines = [];
foreach ($egresosDetalle ?? [] as $row) {
    $egresosLines[] = [(string) ($row['concepto'] ?? ''), $fmtP($row['monto'] ?? 0)];
}
if ($ingresosLines === []) {
    $ingresosLines[] = ['Sin ingresos informados', '—'];
}
if ($egresosLines === []) {
    $egresosLines[] = ['Sin cuotas mensuales informadas', '—'];
}
$padIe = max(count($ingresosLines), count($egresosLines));
?>

<style>
.balance-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:1rem; }
.balance-sheet { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem; align-items:start; }
.balance-col h3 { margin:0 0 .75rem; font-size:1.05rem; letter-spacing:.02em; text-transform:uppercase; }
.balance-col table { width:100%; }
.balance-col td:last-child, .balance-col th:last-child { text-align:right; white-space:nowrap; }
.balance-total { font-weight:700; border-top:2px solid currentColor; }
.balance-total td { padding-top:.65rem; }
tr.balance-pad td {
  border:none !important;
  background:transparent !important;
  padding-top:.55rem;
  padding-bottom:.55rem;
  line-height:1.25;
  color:transparent !important;
  user-select:none;
}
.balance-refs { margin-top:.35rem; }
.balance-refs td { border:none !important; }
.balance-patrimonio {
  margin-top:1rem; padding:1rem 1.25rem; border-radius:12px;
  background: linear-gradient(135deg, rgba(15,76,129,.08), rgba(32,148,143,.1));
  display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap;
}
.balance-patrimonio .label { font-weight:700; letter-spacing:.03em; text-transform:uppercase; font-size:.9rem; }
.balance-patrimonio .value { font-size:1.45rem; font-weight:800; }
.balance-flow { margin-top:1.5rem; }
.balance-muted { opacity:.75; font-size:.9rem; }
.balance-result-pos { color:#0f6b4c; }
.balance-result-neg { color:#9b2c2c; }
@media print {
  .sidebar, .topbar, .balance-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .card, .section-card, .banner { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }
  .balance-sheet { gap:.75rem; }
  a { text-decoration:none; color:inherit; }
}
@media (max-width: 800px) {
  .balance-sheet { grid-template-columns:1fr; }
}
</style>

<section class="banner">
    <h2>Balance patrimonial</h2>
    <p>
        Activos, pasivos y patrimonio<?= $personaNombre ? ' · ' . esc($personaNombre) : ($personaId === '' ? ' · consolidado' : '') ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
        Generado <?= esc(date('d/m/Y H:i')) ?>.
    </p>
</section>

<div class="card section-card no-print">
    <div class="balance-actions">
        <button class="btn btn-ghost btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/resumen') ?>">← Resumen</a>
    </div>
</div>

<div class="balance-sheet">
    <div class="card section-card balance-col">
        <h3>Activos</h3>
        <table class="table">
            <thead><tr><th>Cuenta</th><th>$</th><th>UF</th></tr></thead>
            <tbody>
            <?php foreach ($activosLines as [$lab, $p, $u]): ?>
                <tr>
                    <td><?= esc($lab) ?></td>
                    <td><?= esc($p) ?></td>
                    <td><?= esc($u) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = count($activosLines); $i < $padAp; $i++): ?>
                <tr class="balance-pad" aria-hidden="true"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
                <tr class="balance-total">
                    <td>Total activos</td>
                    <td><?= esc($fmtP($totales['activos'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['activos'] ?? 0)) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card section-card balance-col">
        <h3>Pasivos</h3>
        <table class="table">
            <thead><tr><th>Cuenta</th><th>$</th><th>UF</th></tr></thead>
            <tbody>
            <?php foreach ($pasivosLines as [$lab, $p, $u]): ?>
                <tr>
                    <td><?= esc($lab) ?></td>
                    <td><?= esc($p) ?></td>
                    <td><?= esc($u) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = count($pasivosLines); $i < $padAp; $i++): ?>
                <tr class="balance-pad" aria-hidden="true"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            <?php endfor; ?>
                <tr class="balance-total">
                    <td>Total pasivos (deuda directa)</td>
                    <td><?= esc($fmtP($totales['pasivos'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['pasivos'] ?? 0)) ?></td>
                </tr>
            </tbody>
        </table>
        <table class="table balance-refs">
            <tbody>
                <tr>
                    <td class="balance-muted" colspan="3">Referencias (no restan del patrimonio)</td>
                </tr>
                <tr>
                    <td class="balance-muted">Deuda indirecta</td>
                    <td class="balance-muted"><?= esc($fmtP($totales['pasivos_indirecta'] ?? 0)) ?></td>
                    <td class="balance-muted"><?= esc($fmtU($totalesUf['pasivos_indirecta'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td class="balance-muted">Líneas disponibles (cupos)</td>
                    <td class="balance-muted"><?= esc($fmtP($totales['pasivos_lineas'] ?? 0)) ?></td>
                    <td class="balance-muted"><?= esc($fmtU($totalesUf['pasivos_lineas'] ?? 0)) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="balance-patrimonio">
    <div>
        <div class="label">Patrimonio neto</div>
        <div class="balance-muted">Activos − Pasivos</div>
    </div>
    <div style="text-align:right">
        <div class="value"><?= esc($fmtP($totales['patrimonio'] ?? 0)) ?></div>
        <div class="balance-muted"><?= esc($fmtU($totalesUf['patrimonio'] ?? 0)) ?></div>
    </div>
</div>

<div class="card section-card balance-flow">
    <h3>Ingresos vs egresos (mensual)</h3>
    <p class="muted" style="margin-top:0">Flujo estimado del período: cánones de arriendo, fichas de ingreso y cuotas de créditos.</p>

    <div class="balance-sheet">
        <div class="balance-col">
            <h3>Ingresos</h3>
            <table class="table">
                <thead><tr><th>Concepto</th><th>$</th></tr></thead>
                <tbody>
                <?php foreach ($ingresosLines as [$lab, $p]): ?>
                    <tr>
                        <td><?= esc($lab) ?></td>
                        <td><?= esc($p) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php for ($i = count($ingresosLines); $i < $padIe; $i++): ?>
                    <tr class="balance-pad" aria-hidden="true"><td>&nbsp;</td><td>&nbsp;</td></tr>
                <?php endfor; ?>
                    <tr class="balance-total">
                        <td>Total ingresos</td>
                        <td><?= esc($fmtP($totalIngresos ?? 0)) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="balance-col">
            <h3>Egresos</h3>
            <table class="table">
                <thead><tr><th>Concepto</th><th>$</th></tr></thead>
                <tbody>
                <?php foreach ($egresosLines as [$lab, $p]): ?>
                    <tr>
                        <td><?= esc($lab) ?></td>
                        <td><?= esc($p) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php for ($i = count($egresosLines); $i < $padIe; $i++): ?>
                    <tr class="balance-pad" aria-hidden="true"><td>&nbsp;</td><td>&nbsp;</td></tr>
                <?php endfor; ?>
                    <tr class="balance-total">
                        <td>Total egresos</td>
                        <td><?= esc($fmtP($totalEgresos ?? 0)) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $res = (float) ($resultadoMensual ?? 0);
    $resClass = $res >= 0 ? 'balance-result-pos' : 'balance-result-neg';
    ?>
    <div class="balance-patrimonio" style="margin-top:1rem">
        <div>
            <div class="label">Resultado mensual</div>
            <div class="balance-muted">Ingresos − Egresos</div>
        </div>
        <div class="value <?= $resClass ?>"><?= esc($fmtP($res)) ?></div>
    </div>
</div>

<?= $this->endSection() ?>
