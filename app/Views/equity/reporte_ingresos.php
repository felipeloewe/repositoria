<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return EquityUf::fmtPesos((float) $v, 0);
};
$fmtDual = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $clp = (float) $v;
    $uf  = $ufRate > 0 ? round($clp / $ufRate, 2) : null;

    return '$' . number_format($clp, 0, ',', '.') . ($uf !== null ? ' · ' . number_format($uf, 2, ',', '.') . ' UF' : '');
};
?>

<style>
.report-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:1rem; }
.report-title { margin:0 0 .35rem; }
.report-title h2 { margin:0 0 .35rem; font-size:1.35rem; }
.report-title p { margin:0; color:inherit; opacity:.85; font-size:.92rem; }
.report-section { margin-top:.85rem; }
.report-section h3 { margin:0 0 .35rem; font-size:1.05rem; }
.report-section .muted { margin:.15rem 0 .65rem; }
.report-summary-final {
  margin-top:1.25rem; padding:.85rem 1rem;
  border-top:2px solid currentColor;
  display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap;
}
.report-summary-final .label { font-weight:700; letter-spacing:.02em; text-transform:uppercase; font-size:.9rem; }
.report-summary-final .value { font-size:1.25rem; font-weight:800; }
.report-subhead { margin:1rem 0 .4rem; font-size:.95rem; font-weight:650; }
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
    <h2>Reporte · Ingresos</h2>
    <p>
        Resumen por pestaña y detalle de contratos<?= $personaNombre ? ' · ' . esc($personaNombre) : ($personaId === '' ? ' · consolidado' : '') ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>
        · <?= esc(date('d/m/Y H:i')) ?>
    </p>
</header>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos') ?>">← Ingresos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/situacion-financiera') ?>">Situación financiera</a>
    </div>
</div>

<section class="report-section card section-card">
    <h3>Resumen por pestaña</h3>
    <p class="muted">Totales mensuales equivalentes (misma lógica que el hub Ingresos).</p>
    <table class="table">
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Componentes</th>
                <th>Total mensual</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($secciones ?? []) as $sec): ?>
            <?php
                $meta = $sec['meta'] ?? [];
                $comps = [];
                foreach ($sec['subtotales'] ?? [] as $st) {
                    $m = (float) ($st['monto'] ?? 0);
                    if ($m > 0) {
                        $comps[] = ($st['label'] ?? '') . ' · ' . $fmtP($m);
                    } else {
                        $comps[] = (string) ($st['label'] ?? '');
                    }
                }
            ?>
            <tr>
                <td><strong><?= esc($meta['label'] ?? ($sec['clave'] ?? '')) ?></strong></td>
                <td><?= esc(implode(' · ', $comps)) ?></td>
                <td><strong><?= esc($fmtDual($sec['total'] ?? 0)) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php foreach (($secciones ?? []) as $sec): ?>
    <?php
        $meta = $sec['meta'] ?? [];
        $vigentes = $sec['vigentes'] ?? [];
        $vencidos = $sec['vencidos'] ?? [];
        $subtotales = $sec['subtotales'] ?? [];
    ?>
    <section class="report-section card section-card">
        <h3>
            <?= esc($meta['label'] ?? '') ?>
            <span class="muted" style="font-weight:500;font-size:.9rem">
                · <?= esc($fmtDual($sec['total'] ?? 0)) ?>
                · <?= count($vigentes) ?> vigente(s)
                <?= count($vencidos) > 0 ? ' · ' . count($vencidos) . ' vencido(s)' : '' ?>
            </span>
        </h3>
        <p class="muted"><?= esc($meta['descripcion'] ?? '') ?></p>

        <div class="report-subhead">Resumen de la pestaña</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Equiv. mensual</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subtotales as $st): ?>
                <tr>
                    <td><?= esc($st['label'] ?? '—') ?></td>
                    <td><?= esc($fmtDual(($st['monto'] ?? 0) > 0 ? $st['monto'] : null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align:right"><strong>Subtotal <?= esc($meta['label'] ?? '') ?></strong></td>
                    <td><strong><?= esc($fmtDual($sec['total'] ?? 0)) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="report-subhead">Detalle · contratos vigentes (<?= count($vigentes) ?>)</div>
        <?php if ($vigentes === []): ?>
            <p class="muted">Sin contratos vigentes. El total de esta pestaña proviene de la ficha o de cánones de bienes (Inversiones).</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Contraparte</th>
                            <th>RUT</th>
                            <?php if (($sec['clave'] ?? '') === 'inversiones'): ?><th>Bien raíz</th><?php endif; ?>
                            <th>Inicio</th>
                            <th>Vencimiento</th>
                            <th>Periodicidad</th>
                            <th>Monto contrato</th>
                            <th>Equiv. mensual</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vigentes as $row): ?>
                        <tr>
                            <td><?= esc($row['tipo'] ?? '—') ?></td>
                            <td><?= esc($row['contraparte'] ?? '—') ?></td>
                            <td><?= esc($row['rut'] ?? '—') ?></td>
                            <?php if (($sec['clave'] ?? '') === 'inversiones'): ?>
                                <td><?= esc(($row['bien'] ?? '') !== '' ? $row['bien'] : '—') ?></td>
                            <?php endif; ?>
                            <td><?= esc($row['inicio'] ?? '—') ?></td>
                            <td><?= esc($row['vencimiento'] ?? '—') ?></td>
                            <td><?= esc($row['periodicidad'] ?? '—') ?></td>
                            <td><?= esc($fmtP($row['monto_contrato'] ?? null)) ?></td>
                            <td><strong><?= esc($fmtDual($row['equiv_mensual'] ?? null)) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($vencidos !== []): ?>
            <div class="report-subhead">Detalle · contratos vencidos (<?= count($vencidos) ?>)</div>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Contraparte</th>
                            <th>RUT</th>
                            <th>Inicio</th>
                            <th>Vencimiento</th>
                            <th>Periodicidad</th>
                            <th>Monto contrato</th>
                            <th>Equiv. mensual</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vencidos as $row): ?>
                        <tr>
                            <td><?= esc($row['tipo'] ?? '—') ?></td>
                            <td><?= esc($row['contraparte'] ?? '—') ?></td>
                            <td><?= esc($row['rut'] ?? '—') ?></td>
                            <td><?= esc($row['inicio'] ?? '—') ?></td>
                            <td><?= esc($row['vencimiento'] ?? '—') ?></td>
                            <td><?= esc($row['periodicidad'] ?? '—') ?></td>
                            <td><?= esc($fmtP($row['monto_contrato'] ?? null)) ?></td>
                            <td><?= esc($fmtDual(($row['equiv_mensual'] ?? 0) > 0 ? $row['equiv_mensual'] : null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<div class="report-summary-final">
    <div>
        <div class="label">Total ingresos mensuales</div>
        <div class="muted" style="margin-top:.2rem">Suma de Renta · Honorarios · Inversiones · Otros</div>
    </div>
    <div class="value"><?= esc($fmtDual($totalGeneral ?? 0)) ?></div>
</div>

<?= $this->endSection() ?>
