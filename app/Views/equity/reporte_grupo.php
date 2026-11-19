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
.report-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.75rem; margin:1rem 0; }
@media print {
  .sidebar, .topbar, .report-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .card, .section-card, .banner { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }
  a { text-decoration:none; color:inherit; }
}
</style>

<section class="banner">
    <h2>Reporte · <?= esc($grupoLabel) ?></h2>
    <p>
        Consolidado de <?= esc(strtolower($grupoLabel)) ?>
        <?= $personaNombre ? ' · ' . esc($personaNombre) : '' ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
        Generado <?= esc(date('d/m/Y H:i')) ?>.
    </p>
</section>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $backUrl, '/')) ?>">← Volver</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
    </div>
</div>

<div class="report-summary">
    <div class="card">
        <h3>Total <?= esc(strtolower($grupoLabel)) ?></h3>
        <div class="value" style="font-size:1.25rem"><?= esc(EquityUf::fmtPesos((float) ($granTotalClp ?? 0), 0)) ?></div>
        <div class="muted"><?= esc(EquityUf::fmtUf((float) ($granTotalUf ?? 0), 2)) ?></div>
    </div>
</div>

<?php foreach (($secciones ?? []) as $sec): ?>
    <?php $meta = $sec['meta'] ?? []; ?>
    <div class="card section-card">
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
    </div>
<?php endforeach; ?>

<?= $this->endSection() ?>
