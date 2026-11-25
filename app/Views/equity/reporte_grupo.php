<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static fn ($v): string => $v === null ? '—' : EquityUf::fmtPesos((float) $v, 0);
$fmtU = static fn ($v): string => $v === null ? '—' : EquityUf::fmtUf((float) $v, 2);
$grupoLabel = ($grupo ?? '') === 'pasivos' ? 'Pasivos' : 'Activos';
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
  display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap;
}
.report-summary-final .label { font-weight:700; letter-spacing:.02em; text-transform:uppercase; font-size:.9rem; }
.report-summary-final .value { font-size:1.25rem; font-weight:800; }
@media print {
  .sidebar, .topbar, .report-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .report-title { margin:0 0 .5rem; padding-bottom:.35rem; border-bottom:1px solid #ccc; }
  .report-section { break-inside:auto; page-break-inside:auto; }
  .report-section table { break-inside:auto; page-break-inside:auto; }
  .report-section tr { break-inside:avoid; page-break-inside:avoid; }
  .report-summary-final { break-inside:avoid; page-break-inside:avoid; }
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

<?php foreach (($secciones ?? []) as $sec): ?>
    <?php $meta = $sec['meta'] ?? []; ?>
    <section class="report-section card section-card">
        <h3>
            <?= esc($meta['label'] ?? '') ?>
            <span class="muted" style="font-weight:500;font-size:.9rem">(<?= count($sec['rows'] ?? []) ?>)</span>
            <a class="btn btn-ghost btn-sm no-print" href="<?= site_url(ltrim((string) ($sec['reporte'] ?? ''), '/')) ?>">Solo esta sección</a>
        </h3>
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
<?php endforeach; ?>

<div class="report-summary-final">
    <div>
        <div class="label">Total <?= esc(strtolower($grupoLabel)) ?></div>
        <div class="muted" style="font-size:.85rem">Suma de todas las secciones</div>
    </div>
    <div style="text-align:right">
        <div class="value"><?= esc(EquityUf::fmtPesos((float) ($granTotalClp ?? 0), 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($granTotalUf ?? 0), 2)) ?></div>
    </div>
</div>

<?= $this->endSection() ?>
