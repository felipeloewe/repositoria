<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtDual = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $clp = (float) $v;
    $uf  = $ufRate > 0 ? round($clp / $ufRate, 2) : null;

    return '$' . number_format($clp, 0, ',', '.') . ($uf !== null ? ' · ' . number_format($uf, 2, ',', '.') . ' UF' : '');
};
$fmtP = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return EquityUf::fmtPesos((float) $v, 0);
};
$fmtUf = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 4, ',', '.') . ' UF';
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
    <h2>Reporte · Egresos</h2>
    <p>
        Resumen por pestaña y detalle de créditos<?= $personaNombre ? ' · ' . esc($personaNombre) : ($personaId === '' ? ' · consolidado' : '') ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>
        · <?= esc(date('d/m/Y H:i')) ?>
    </p>
</header>

<div class="card section-card no-print">
    <div class="report-actions">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/egresos') ?>">← Egresos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/ingresos') ?>">Ingresos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/situacion-financiera') ?>">Situación financiera</a>
    </div>
</div>

<section class="report-section card section-card">
    <h3>Resumen por pestaña</h3>
    <p class="muted">Cuotas mensuales en $ (misma lógica que el hub Egresos). Solo deuda directa.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Registros</th>
                <th>Cuota / mes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($secciones ?? []) as $sec): ?>
            <?php $meta = $sec['meta'] ?? []; ?>
            <tr>
                <td><strong><?= esc($meta['label'] ?? ($sec['clave'] ?? '')) ?></strong></td>
                <td><?= (int) ($sec['conteo'] ?? 0) ?></td>
                <td><strong><?= esc($fmtDual($sec['total'] ?? 0)) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php foreach (($secciones ?? []) as $sec): ?>
    <?php
        $meta = $sec['meta'] ?? [];
        $detalle = $sec['detalle'] ?? [];
    ?>
    <section class="report-section card section-card">
        <h3>
            <?= esc($meta['label'] ?? '') ?>
            <span class="muted" style="font-weight:500;font-size:.9rem">
                · <?= esc($fmtDual($sec['total'] ?? 0)) ?>
                · <?= (int) ($sec['conteo'] ?? 0) ?> registro(s)
            </span>
        </h3>
        <p class="muted"><?= esc($meta['descripcion'] ?? '') ?></p>

        <div class="report-subhead">Detalle · créditos (<?= count($detalle) ?>)</div>
        <?php if ($detalle === []): ?>
            <p class="muted">Sin créditos en esta pestaña.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Institución</th>
                            <th>Nº</th>
                            <?php if ($personaId === ''): ?><th>Persona</th><?php endif; ?>
                            <th>Tipo</th>
                            <th>Moneda</th>
                            <th>Reg. / venc.</th>
                            <th>Div.</th>
                            <th>Saldo</th>
                            <th>Cuota mes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detalle as $row): ?>
                        <tr>
                            <td><?= esc($row['institucion'] ?? '—') ?></td>
                            <td><?= esc($row['numero'] ?? '—') ?></td>
                            <?php if ($personaId === ''): ?>
                                <td><?= esc($row['persona'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td><?= esc($row['tipo'] ?? '—') ?></td>
                            <td><?= esc($row['moneda'] ?? '—') ?></td>
                            <td>
                                <?= esc($row['fecha_registro'] ?? '—') ?>
                                <?php if (! empty($row['fecha_vencimiento']) && ($row['fecha_vencimiento'] ?? '') !== ($row['fecha_registro'] ?? '')): ?>
                                    <div class="muted" style="font-size:.85em">Venc. <?= esc($row['fecha_vencimiento']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['dividendo_n'] ?? '—') ?></td>
                            <td>
                                <?= esc($fmtUf($row['saldo_uf'] ?? null)) ?>
                                <?php if (($row['saldo_clp'] ?? null) !== null): ?>
                                    <div class="muted" style="font-size:.85em"><?= esc($fmtP($row['saldo_clp'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= esc($fmtDual($row['cuota_clp'] ?? null)) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="<?= $personaId === '' ? 8 : 7 ?>" style="text-align:right"><strong>Subtotal <?= esc($meta['label'] ?? '') ?></strong></td>
                            <td><strong><?= esc($fmtDual($sec['total'] ?? 0)) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php if (($indirectaDetalle ?? []) !== []): ?>
<section class="report-section card section-card">
    <h3>
        Referencia · Deuda indirecta
        <span class="muted" style="font-weight:500;font-size:.9rem">
            · <?= esc($fmtDual($totalPasivosIndirecta ?? 0)) ?>
            · <?= count($indirectaDetalle) ?> registro(s)
        </span>
    </h3>
    <p class="muted">No forma parte de la carga mensual de egresos; se incluye solo en el totalizado de pasivos con indirectos.</p>
    <div style="overflow-x:auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Institución</th>
                    <th>Tipo</th>
                    <?php if ($personaId === ''): ?><th>Persona</th><?php endif; ?>
                    <th>Tomador</th>
                    <th>Vigente</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($indirectaDetalle as $row): ?>
                <tr>
                    <td><?= esc($row['institucion'] ?? '—') ?></td>
                    <td><?= esc($row['tipo'] ?? '—') ?></td>
                    <?php if ($personaId === ''): ?>
                        <td><?= esc($row['persona'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td>
                        <?= esc($row['tomador'] ?? '—') ?>
                        <?= ! empty($row['tomador_rut']) ? ' · ' . esc($row['tomador_rut']) : '' ?>
                    </td>
                    <td><strong><?= esc($fmtDual($row['vigente_clp'] ?? null)) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="<?= $personaId === '' ? 4 : 3 ?>" style="text-align:right"><strong>Subtotal deuda indirecta</strong></td>
                    <td><strong><?= esc($fmtDual($totalPasivosIndirecta ?? 0)) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
<?php endif; ?>

<div class="report-summary-final" style="flex-direction:column;align-items:stretch;gap:.85rem">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
        <div>
            <div class="label">Total egresos mensuales</div>
            <div class="muted" style="margin-top:.2rem">Cuotas Hipotecarios · Circulantes · Otros</div>
        </div>
        <div class="value"><?= esc($fmtDual($totalGeneral ?? 0)) ?></div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;padding-top:.65rem;border-top:1px solid rgba(0,0,0,.12)">
        <div>
            <div class="label">Total pasivos directos</div>
            <div class="muted" style="margin-top:.2rem">Stock Hipotecarios · Circulantes · Otros (misma base que Balance)</div>
        </div>
        <div class="value"><?= esc($fmtDual($totalPasivosDirectos ?? 0)) ?></div>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;padding-top:.65rem;border-top:1px solid rgba(0,0,0,.12)">
        <div>
            <div class="label">Total pasivos incluyendo indirectos</div>
            <div class="muted" style="margin-top:.2rem">
                Directos<?= esc($fmtP($totalPasivosDirectos ?? 0)) ?>
                + Indirecta <?= esc($fmtP($totalPasivosIndirecta ?? 0)) ?>
            </div>
        </div>
        <div class="value"><?= esc($fmtDual($totalPasivosConIndirectos ?? 0)) ?></div>
    </div>
</div>

<?= $this->endSection() ?>
