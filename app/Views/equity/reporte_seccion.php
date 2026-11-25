<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static fn ($v): string => $v === null ? '—' : EquityUf::fmtPesos((float) $v, 0);
$fmtU = static fn ($v): string => $v === null ? '—' : EquityUf::fmtUf((float) $v, 2);
$clave = $meta['clave'] ?? '';
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
    <h2>Reporte · <?= esc($meta['label'] ?? '') ?></h2>
    <p>
        <?= esc($meta['grupo'] ?? '') ?>
        <?= $personaNombre ? ' · ' . esc($personaNombre) : '' ?>
        · UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>
        · <?= esc(date('d/m/Y H:i')) ?>
    </p>
</header>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $backUrl, '/')) ?>">← Volver</a>
        <?php if (! empty($grupoReporte)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $grupoReporte, '/')) ?>">Reporte grupo</a>
        <?php endif; ?>
    </div>
</div>

<section class="report-section card section-card">
    <h3>Detalle (<?= count($rows ?? []) ?>)</h3>
    <?php if (($rows ?? []) === []): ?>
        <p class="muted">Sin registros para reportar.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Detalle</th>
                    <?php if ($clave === 'bienes-raices'): ?><th>Avalúo fiscal</th><?php endif; ?>
                    <th>UF</th>
                    <th>$</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $persona = $row['persona'] ?? null;
                    $nombreP = $persona ? trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')) : '—';
                ?>
                <tr>
                    <td><?= esc($nombreP) ?></td>
                    <td><?= esc(($row['detalle'] ?? '') !== '' ? $row['detalle'] : '—') ?></td>
                    <?php if ($clave === 'bienes-raices'): ?>
                        <td><?= esc(! empty($row['avaluo_fiscal']) ? EquityUf::fmtPesos((float) $row['avaluo_fiscal'], 0) : '—') ?></td>
                    <?php endif; ?>
                    <td><?= esc($fmtU($row['uf_show'] ?? null)) ?></td>
                    <td><?= esc($fmtP($row['clp_show'] ?? null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if (($rows ?? []) !== []): ?>
<div class="report-summary-final">
    <div>
        <div class="label">Total</div>
        <?php if ($clave === 'bienes-raices'): ?>
            <div class="muted" style="font-size:.85rem">Avalúo fiscal <?= esc(EquityUf::fmtPesos((float) ($totalAvaluo ?? 0), 0)) ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align:right">
        <div class="value"><?= esc(EquityUf::fmtPesos((float) ($totalClp ?? 0), 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($totalUf ?? 0), 2)) ?></div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
