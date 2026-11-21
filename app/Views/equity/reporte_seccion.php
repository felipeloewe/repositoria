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
    <h2>Reporte · <?= esc($meta['label'] ?? '') ?></h2>
    <p>
        <?= esc($meta['grupo'] ?? '') ?>
        <?= $personaNombre ? ' · ' . esc($personaNombre) : '' ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
        Generado <?= esc(date('d/m/Y H:i')) ?>.
    </p>
</section>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $backUrl, '/')) ?>">← Volver</a>
        <?php if (! empty($grupoReporte)): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $grupoReporte, '/')) ?>">Reporte grupo</a>
        <?php endif; ?>
    </div>
</div>

<div class="card section-card">
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
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Total</strong></td>
                    <?php if ($clave === 'bienes-raices'): ?>
                        <td><strong><?= esc(EquityUf::fmtPesos((float) ($totalAvaluo ?? 0), 0)) ?></strong></td>
                    <?php endif; ?>
                    <td><strong><?= esc(EquityUf::fmtUf((float) ($totalUf ?? 0), 2)) ?></strong></td>
                    <td><strong><?= esc(EquityUf::fmtPesos((float) ($totalClp ?? 0), 0)) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
