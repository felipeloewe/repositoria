<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static fn ($v): string => $v === null ? '—' : EquityUf::fmtPesos((float) $v, 0);
$fmtU = static fn ($v): string => $v === null ? '—' : EquityUf::fmtUf((float) $v, 2);
$fmtDual = static function ($clp, $uf) use ($fmtP, $fmtU): string {
    return $fmtP($clp) . ' · ' . $fmtU($uf);
};
$grupoLabel = ($grupo ?? '') === 'pasivos' ? 'Pasivos' : 'Activos';
$esPasivos = ($grupo ?? '') === 'pasivos';
$seccionesDirectas = [];
$seccionesIndirecta = [];
$seccionesLineas = [];
foreach (($secciones ?? []) as $sec) {
    if (! empty($sec['es_lineas'])) {
        $seccionesLineas[] = $sec;
    } elseif (! empty($sec['es_indirecta'])) {
        $seccionesIndirecta[] = $sec;
    } else {
        $seccionesDirectas[] = $sec;
    }
}
$totalConIndirectosClp = (float) ($granTotalClp ?? 0) + (float) ($totalIndirectaClp ?? 0);
$totalConIndirectosUf  = (float) ($granTotalUf ?? 0) + (float) ($totalIndirectaUf ?? 0);
?>

<style>
.report-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
.report-title { margin:0 0 .35rem; }
.report-title h2 { margin:0 0 .35rem; font-size:1.35rem; }
.report-title p { margin:0; color:inherit; opacity:.85; font-size:.92rem; }
.report-section { margin-top:.85rem; }
.report-section h3 { margin:0 0 .5rem; font-size:1.05rem; }
.report-summary-final {
  margin-top:1.25rem; padding:.85rem 1rem;
  border-top:2px solid currentColor;
  display:flex; flex-direction:column; align-items:stretch; gap:.85rem;
}
.report-summary-final .label { font-weight:700; letter-spacing:.02em; text-transform:uppercase; font-size:.9rem; }
.report-summary-final .value { font-size:1.25rem; font-weight:800; }
.report-summary-row {
  display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap;
}
.report-summary-row + .report-summary-row {
  padding-top:.65rem; border-top:1px solid rgba(0,0,0,.12);
}
.report-lineas-box {
  margin-top:1rem;
  padding:.9rem 1.1rem;
  border-radius:12px;
  border:2px solid #2f9e7a;
  background: linear-gradient(180deg, rgba(47,158,122,.08), rgba(255,255,255,.92));
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.85);
}
.report-lineas-box .label {
  font-weight:700; letter-spacing:.02em; text-transform:uppercase; font-size:.9rem; color:#0f6b4c;
}
.report-lineas-box .value { font-size:1.25rem; font-weight:800; color:#0f6b4c; }
.report-lineas-box .muted { opacity:.8; font-size:.9rem; margin-top:.2rem; }
.report-lineas-box table { margin-top:.75rem; }
.report-ref-note { margin:.15rem 0 .65rem; font-size:.9rem; opacity:.8; }
@media print {
  .sidebar, .topbar, .report-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .report-title { margin:0 0 .5rem; padding-bottom:.35rem; border-bottom:1px solid #ccc; }
  .report-section { break-inside:auto; page-break-inside:auto; }
  .report-section table { break-inside:auto; page-break-inside:auto; }
  .report-section tr { break-inside:avoid; page-break-inside:avoid; }
  .report-summary-final, .report-lineas-box { break-inside:avoid; page-break-inside:avoid; }
  a { text-decoration:none; color:inherit; }
}
</style>

<header class="report-title">
    <h2>Reporte · <?= esc($grupoLabel) ?></h2>
    <p>
        <?= $personaNombre ? esc($personaNombre) . ' · ' : '' ?>
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>
        · <?= esc(date('d/m/Y H:i')) ?>
    </p>
</header>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $backUrl, '/')) ?>">← Volver</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
    </div>
</div>

<?php
$renderSeccion = static function (array $sec, bool $esReferencia = false) use ($fmtU, $fmtP): void {
    $meta = $sec['meta'] ?? [];
    ?>
    <section class="report-section card section-card">
        <h3>
            <?= $esReferencia ? 'Referencia · ' : '' ?><?= esc($meta['label'] ?? '') ?>
            <span class="muted" style="font-weight:500;font-size:.9rem">(<?= count($sec['rows'] ?? []) ?>)</span>
            <a class="btn btn-ghost btn-sm no-print" href="<?= site_url(ltrim((string) ($sec['reporte'] ?? ''), '/')) ?>">Solo esta sección</a>
        </h3>
        <?php if ($esReferencia): ?>
            <p class="report-ref-note">No forma parte del total de pasivos directos.</p>
        <?php endif; ?>
        <?php if (($sec['rows'] ?? []) === []): ?>
            <p class="muted">Sin registros.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Detalle</th>
                        <th>UF</th>
                        <th>$</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sec['rows'] as $row): ?>
                    <tr>
                        <td><?= esc(($row['detalle'] ?? '') !== '' ? $row['detalle'] : '—') ?></td>
                        <td><?= esc($fmtU($row['uf_show'] ?? null)) ?></td>
                        <td><?= esc($fmtP($row['clp_show'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align:right"><strong>Subtotal</strong></td>
                        <td><strong><?= esc(EquityUf::fmtUf((float) ($sec['totalUf'] ?? 0), 2)) ?></strong></td>
                        <td><strong><?= esc(EquityUf::fmtPesos((float) ($sec['totalClp'] ?? 0), 0)) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </section>
    <?php
};
?>

<?php foreach ($seccionesDirectas as $sec): ?>
    <?php $renderSeccion($sec, false); ?>
<?php endforeach; ?>

<?php if ($esPasivos): ?>
    <?php foreach ($seccionesIndirecta as $sec): ?>
        <?php $renderSeccion($sec, true); ?>
    <?php endforeach; ?>

    <div class="report-summary-final">
        <div class="report-summary-row">
            <div>
                <div class="label">Total pasivos directos</div>
                <div class="muted" style="font-size:.85rem">Hipotecarios · Circulantes · Otros (misma base que Balance)</div>
            </div>
            <div style="text-align:right">
                <div class="value"><?= esc(EquityUf::fmtPesos((float) ($granTotalClp ?? 0), 0)) ?></div>
                <div class="muted"><?= esc(EquityUf::fmtUf((float) ($granTotalUf ?? 0), 2)) ?></div>
            </div>
        </div>
        <div class="report-summary-row">
            <div>
                <div class="label">Total pasivos incluyendo indirectos</div>
                <div class="muted" style="font-size:.85rem">
                    Directos <?= esc($fmtP($granTotalClp ?? 0)) ?>
                    + Indirecta <?= esc($fmtP($totalIndirectaClp ?? 0)) ?>
                </div>
            </div>
            <div style="text-align:right">
                <div class="value"><?= esc(EquityUf::fmtPesos($totalConIndirectosClp, 0)) ?></div>
                <div class="muted"><?= esc(EquityUf::fmtUf($totalConIndirectosUf, 2)) ?></div>
            </div>
        </div>
    </div>

    <?php
    $lineasSec = $seccionesLineas[0] ?? null;
    $lineasRows = is_array($lineasSec) ? ($lineasSec['rows'] ?? []) : [];
    ?>
    <div class="report-lineas-box">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
            <div>
                <div class="label">Líneas disponibles</div>
                <div class="muted">Cupos / líneas de crédito (referencia · no suman a pasivos)</div>
            </div>
            <div class="value"><?= esc($fmtDual($totalLineasClp ?? 0, $totalLineasUf ?? 0)) ?></div>
        </div>
        <?php if ($lineasRows !== []): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Detalle</th>
                        <th>UF</th>
                        <th>$</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lineasRows as $row): ?>
                    <tr>
                        <td><?= esc(($row['detalle'] ?? '') !== '' ? $row['detalle'] : '—') ?></td>
                        <td><?= esc($fmtU($row['uf_show'] ?? null)) ?></td>
                        <td><?= esc($fmtP($row['clp_show'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align:right"><strong>Total líneas</strong></td>
                        <td><strong><?= esc(EquityUf::fmtUf((float) ($totalLineasUf ?? 0), 2)) ?></strong></td>
                        <td><strong><?= esc(EquityUf::fmtPesos((float) ($totalLineasClp ?? 0), 0)) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach (array_merge($seccionesIndirecta, $seccionesLineas) as $sec): ?>
        <?php $renderSeccion($sec, false); ?>
    <?php endforeach; ?>

    <div class="report-summary-final">
        <div class="report-summary-row">
            <div>
                <div class="label">Total <?= esc(strtolower($grupoLabel)) ?></div>
                <div class="muted" style="font-size:.85rem">Suma de todas las secciones</div>
            </div>
            <div style="text-align:right">
                <div class="value"><?= esc(EquityUf::fmtPesos((float) ($granTotalClp ?? 0), 0)) ?></div>
                <div class="muted"><?= esc(EquityUf::fmtUf((float) ($granTotalUf ?? 0), 2)) ?></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
