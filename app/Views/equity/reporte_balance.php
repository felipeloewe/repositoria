<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtP = static fn ($v): string => EquityUf::fmtPesos((float) ($v ?? 0), 0);
$fmtU = static fn ($v): string => EquityUf::fmtUf((float) ($v ?? 0), 2);
$qs = '';
?>

<style>
.balance-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:1rem; }
.balance-sheet { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem; }
.balance-col h3 { margin:0 0 .75rem; font-size:1.05rem; letter-spacing:.02em; text-transform:uppercase; }
.balance-col table { width:100%; }
.balance-col td:last-child, .balance-col th:last-child { text-align:right; white-space:nowrap; }
.balance-total { font-weight:700; border-top:2px solid currentColor; }
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
                <tr>
                    <td>Bienes raíces</td>
                    <td><?= esc($fmtP($totales['bienes_raices'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['bienes_raices'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Vehículos</td>
                    <td><?= esc($fmtP($totales['vehiculos'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['vehiculos'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Sociedades</td>
                    <td><?= esc($fmtP($totales['sociedades'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['sociedades'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Otros activos</td>
                    <td><?= esc($fmtP($totales['otros_activos'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['otros_activos'] ?? 0)) ?></td>
                </tr>
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
                <tr>
                    <td>Hipotecarios</td>
                    <td><?= esc($fmtP($totales['pasivos_inm'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['pasivos_inm'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Circulantes</td>
                    <td><?= esc($fmtP($totales['pasivos_circ'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['pasivos_circ'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td>Otros pasivos</td>
                    <td><?= esc($fmtP($totales['pasivos_otro'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['pasivos_otro'] ?? 0)) ?></td>
                </tr>
                <tr class="balance-total">
                    <td>Total pasivos (deuda directa)</td>
                    <td><?= esc($fmtP($totales['pasivos'] ?? 0)) ?></td>
                    <td><?= esc($fmtU($totalesUf['pasivos'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td class="balance-muted" colspan="3" style="padding-top:.85rem">Referencias (no restan del patrimonio)</td>
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
                <?php if (($arriendosDetalle ?? []) === [] && ($ingresosDetalle ?? []) === []): ?>
                    <tr><td colspan="2" class="muted">Sin ingresos informados</td></tr>
                <?php endif; ?>
                <?php foreach ($arriendosDetalle as $row): ?>
                    <tr>
                        <td>Arriendo · <?= esc($row['concepto']) ?></td>
                        <td><?= esc($fmtP($row['monto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($ingresosDetalle as $row): ?>
                    <tr>
                        <td>
                            <?= esc($row['concepto']) ?>
                            <?php if (! empty($row['persona'])): ?>
                                <span class="balance-muted"> · <?= esc(trim(($row['persona']['nombre'] ?? '') . ' ' . ($row['persona']['apellido'] ?? ''))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($fmtP($row['monto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
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
                <?php if (($egresosDetalle ?? []) === []): ?>
                    <tr><td colspan="2" class="muted">Sin cuotas mensuales informadas</td></tr>
                <?php endif; ?>
                <?php foreach ($egresosDetalle as $row): ?>
                    <tr>
                        <td><?= esc($row['concepto']) ?></td>
                        <td><?= esc($fmtP($row['monto'])) ?></td>
                    </tr>
                <?php endforeach; ?>
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
