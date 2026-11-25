<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCierre;
use App\Libraries\EquityUf;

$tipo = (string) ($reporte['tipo'] ?? '');
$tipoLabel = $tipos[$tipo] ?? $tipo;
$ufRate = (float) (($payload['uf_valor_clp'] ?? null) ?: ($reporte['uf_valor_clp'] ?? 0));
$periodoUrl = is_array($periodo) ? site_url('equity/cierre/periodo/' . $periodo['id']) : site_url('equity/cierre');
$labelPeriodo = is_array($periodo)
    ? EquityCierre::labelMes((int) $periodo['mes']) . ' ' . (int) $periodo['anio']
    : '';
?>

<style>
@media print {
  .sidebar, .topbar, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .card, .section-card, .banner { box-shadow:none !important; border:1px solid #ccc !important; }
}
</style>

<section class="banner">
    <h2><?= esc($reporte['titulo'] ?? $tipoLabel) ?></h2>
    <p>
        Snapshot histórico
        <?php if ($labelPeriodo !== ''): ?> · <?= esc($labelPeriodo) ?><?php endif; ?>
        <?php if ($ufRate > 0): ?> · UF <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?php endif; ?>
        <?php if (! empty($reporte['created_at'])): ?> · <?= esc($reporte['created_at']) ?><?php endif; ?>
    </p>
    <p class="no-print" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-ghost btn-sm" href="<?= esc($periodoUrl) ?>">← Periodo</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Imprimir</button>
    </p>
</section>

<?php if ($tipo === 'balance'): ?>
<div class="card section-card">
    <h3>Situación patrimonial</h3>
    <table class="table">
        <tbody>
            <tr><td>Total activos</td><td><?= esc(EquityUf::fmtUf((float) ($payload['total_activos_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['total_activos_clp'] ?? 0), 0)) ?></td></tr>
            <tr><td>Pasivos directos</td><td><?= esc(EquityUf::fmtUf((float) ($payload['total_pasivos_directos_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['total_pasivos_directos_clp'] ?? 0), 0)) ?></td></tr>
            <tr><td><strong>Patrimonio</strong></td><td><strong><?= esc(EquityUf::fmtUf((float) ($payload['patrimonio_uf'] ?? 0), 2)) ?></strong></td><td><strong><?= esc(EquityUf::fmtPesos((float) ($payload['patrimonio_clp'] ?? 0), 0)) ?></strong></td></tr>
            <tr><td>Pasivos + indirecta</td><td><?= esc(EquityUf::fmtUf((float) ($payload['pasivos_con_indirectos_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['pasivos_con_indirectos_clp'] ?? 0), 0)) ?></td></tr>
            <tr><td>Líneas disponibles</td><td><?= esc(EquityUf::fmtUf((float) ($payload['lineas_disponibles_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['lineas_disponibles_clp'] ?? 0), 0)) ?></td></tr>
        </tbody>
    </table>
    <h3 style="margin-top:1.25rem">Flujo mensual</h3>
    <p>
        Ingresos <?= esc(EquityUf::fmtPesos((float) (($payload['ingresos_mensuales']['total'] ?? 0)), 0)) ?>
        − Egresos <?= esc(EquityUf::fmtPesos((float) (($payload['egresos_mensuales']['total'] ?? 0)), 0)) ?>
        = <strong><?= esc(EquityUf::fmtPesos((float) ($payload['flujo_mensual_clp'] ?? 0), 0)) ?></strong>
    </p>
</div>

<?php elseif ($tipo === 'ingresos'): ?>
<div class="card section-card">
    <h3>Totales ingresos mensuales</h3>
    <table class="table">
        <thead><tr><th>Fuente</th><th>$ / mes</th></tr></thead>
        <tbody>
        <?php foreach (($payload['totales']['porFuente'] ?? []) as $k => $v): ?>
            <tr><td><?= esc($k) ?></td><td><?= esc(EquityUf::fmtPesos((float) $v, 0)) ?></td></tr>
        <?php endforeach; ?>
            <tr><td><strong>Total</strong></td><td><strong><?= esc(EquityUf::fmtPesos((float) ($payload['totales']['total'] ?? 0), 0)) ?></strong></td></tr>
        </tbody>
    </table>
    <?php if (! empty($payload['contratos'])): ?>
    <h3 style="margin-top:1.25rem">Contratos (vigentes y vencidos)</h3>
    <table class="table">
        <thead>
            <tr><th>Fuente</th><th>Contraparte</th><th>Estado</th><th>Periodicidad</th><th>Monto</th><th>Equiv. mensual</th></tr>
        </thead>
        <tbody>
        <?php foreach ($payload['contratos'] as $c): ?>
            <tr>
                <td><?= esc(($c['fuente'] ?? '') . ' / ' . ($c['subtipo'] ?? '')) ?></td>
                <td><?= esc($c['contraparte'] ?? '—') ?></td>
                <td><?= esc($c['estado'] ?? '—') ?></td>
                <td><?= esc($c['periodicidad'] ?? '—') ?></td>
                <td><?= isset($c['monto_contrato']) ? esc(EquityUf::fmtPesos((float) $c['monto_contrato'], 0)) : '—' ?></td>
                <td><?= isset($c['equiv_mensual']) ? esc(EquityUf::fmtPesos((float) $c['equiv_mensual'], 0)) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($tipo === 'egresos'): ?>
<div class="card section-card">
    <h3>Totales egresos mensuales</h3>
    <table class="table">
        <thead><tr><th>Fuente</th><th>$ / mes</th></tr></thead>
        <tbody>
        <?php foreach (($payload['totales']['porFuente'] ?? []) as $k => $v): ?>
            <tr><td><?= esc($k) ?></td><td><?= esc(EquityUf::fmtPesos((float) $v, 0)) ?></td></tr>
        <?php endforeach; ?>
            <tr><td><strong>Total</strong></td><td><strong><?= esc(EquityUf::fmtPesos((float) ($payload['totales']['total'] ?? 0), 0)) ?></strong></td></tr>
        </tbody>
    </table>
    <?php foreach (($payload['detalle'] ?? []) as $bloque): ?>
        <h3 style="margin-top:1.25rem"><?= esc($bloque['label'] ?? $bloque['clave'] ?? '') ?></h3>
        <?php if (empty($bloque['items'])): ?>
            <p class="muted">Sin ítems.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Institución</th><th>Moneda</th><th>Cuota</th><th>Amortización</th><th>Interés</th></tr>
                </thead>
                <tbody>
                <?php foreach ($bloque['items'] as $it): ?>
                    <tr>
                        <td><?= esc($it['institucion'] ?? '—') ?></td>
                        <td><?= esc($it['moneda'] ?? '—') ?></td>
                        <td><?= isset($it['cuota_clp']) ? esc(EquityUf::fmtPesos((float) $it['cuota_clp'], 0)) : '—' ?>
                            <?php if (isset($it['cuota_uf'])): ?> · <?= esc(EquityUf::fmtUf((float) $it['cuota_uf'], 2)) ?><?php endif; ?></td>
                        <td><?= isset($it['amortizacion_clp']) ? esc(EquityUf::fmtPesos((float) $it['amortizacion_clp'], 0)) : '—' ?></td>
                        <td><?= isset($it['interes_clp']) ? esc(EquityUf::fmtPesos((float) $it['interes_clp'], 0)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php elseif ($tipo === 'pasivos'): ?>
<div class="card section-card">
    <h3>Pasivos</h3>
    <table class="table">
        <tbody>
        <?php foreach (($payload['directos'] ?? []) as $row): ?>
            <tr>
                <td><?= esc($row['label'] ?? '') ?></td>
                <td><?= esc(EquityUf::fmtUf((float) ($row['uf'] ?? 0), 2)) ?></td>
                <td><?= esc(EquityUf::fmtPesos((float) ($row['clp'] ?? 0), 0)) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr><td><strong>Total directos</strong></td><td><strong><?= esc(EquityUf::fmtUf((float) ($payload['total_directos_uf'] ?? 0), 2)) ?></strong></td><td><strong><?= esc(EquityUf::fmtPesos((float) ($payload['total_directos_clp'] ?? 0), 0)) ?></strong></td></tr>
            <tr><td>Indirecta</td><td><?= esc(EquityUf::fmtUf((float) ($payload['indirecta_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['indirecta_clp'] ?? 0), 0)) ?></td></tr>
            <tr><td>Con indirectos</td><td><?= esc(EquityUf::fmtUf((float) ($payload['con_indirectos_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['con_indirectos_clp'] ?? 0), 0)) ?></td></tr>
            <tr><td>Líneas</td><td><?= esc(EquityUf::fmtUf((float) ($payload['lineas_uf'] ?? 0), 2)) ?></td><td><?= esc(EquityUf::fmtPesos((float) ($payload['lineas_clp'] ?? 0), 0)) ?></td></tr>
        </tbody>
    </table>
</div>

<?php elseif ($tipo === 'activos'): ?>
<div class="card section-card">
    <h3>Activos</h3>
    <table class="table">
        <tbody>
        <?php foreach (($payload['secciones'] ?? []) as $row): ?>
            <tr>
                <td><?= esc($row['label'] ?? '') ?></td>
                <td><?= esc(EquityUf::fmtUf((float) ($row['uf'] ?? 0), 2)) ?></td>
                <td><?= esc(EquityUf::fmtPesos((float) ($row['clp'] ?? 0), 0)) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr><td><strong>Total</strong></td><td><strong><?= esc(EquityUf::fmtUf((float) ($payload['total_uf'] ?? 0), 2)) ?></strong></td><td><strong><?= esc(EquityUf::fmtPesos((float) ($payload['total_clp'] ?? 0), 0)) ?></strong></td></tr>
        </tbody>
    </table>
</div>

<?php elseif ($tipo === 'situacion'): ?>
<div class="card section-card">
    <h3>Situación financiera</h3>
    <p>
        Ingresos <?= esc(EquityUf::fmtPesos((float) ($payload['ingresos_mensuales']['total'] ?? 0), 0)) ?>
        · Egresos <?= esc(EquityUf::fmtPesos((float) ($payload['egresos_mensuales']['total'] ?? 0), 0)) ?>
        · Flujo <?= esc(EquityUf::fmtPesos((float) ($payload['flujo_mensual_clp'] ?? 0), 0)) ?>
        <?php if (isset($payload['carga_financiera_pct']) && $payload['carga_financiera_pct'] !== null): ?>
            · Carga <?= esc((string) $payload['carga_financiera_pct']) ?>%
        <?php endif; ?>
    </p>
</div>

<?php else: ?>
<div class="card section-card">
    <h3>Payload</h3>
    <pre style="white-space:pre-wrap;font-size:.85rem"><?= esc(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
