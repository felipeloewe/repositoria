<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmtPesos = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return '$' . number_format((float) $v, 0, ',', '.');
};
$fmtDual = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $clp = (float) $v;
    $uf  = $ufRate > 0 ? round($clp / $ufRate, 2) : null;

    return '$' . number_format($clp, 0, ',', '.') . ($uf !== null ? ' · ' . number_format($uf, 2, ',', '.') . ' UF' : '');
};
$fmtInput = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }

    return number_format((float) $v, 0, ',', '.');
};
$cargaPct = null;
if ($ingresoMensual !== null && (float) $ingresoMensual > 0) {
    $cargaPct = round(((float) $totalCuotas / (float) $ingresoMensual) * 100, 1);
}
$fuentesIngreso = $fuentesIngreso ?? [];
$totalesFuente  = $totalesFuente ?? [];
$rowsIndirecta  = $rowsIndirecta ?? [];
?>

<style>
@media print {
  .sidebar, .topbar, .report-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .card, .section-card, .banner { box-shadow:none !important; border:1px solid #ccc !important; }
  a { text-decoration:none; color:inherit; }
}
</style>

<section class="banner">
    <h2>Situación financiera</h2>
    <p>
        Reporte de ingresos informados y carga mensual de créditos (cuotas / vencimientos).
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
    </p>
    <p class="report-actions no-print" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Reporte · Imprimir / PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos') ?>">Ingresos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/egresos') ?>">Egresos</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php
$personaLabelSf = '';
foreach ($personas as $p) {
    if (($p['id'] ?? '') === $personaId) {
        $personaLabelSf = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        break;
    }
}
?>
<?php if ($personaLabelSf !== ''): ?>
<div class="card section-card">
    <p style="margin:0">Declarando como <strong><?= esc($personaLabelSf) ?></strong>.</p>
</div>
<?php endif; ?>

<div class="grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <div class="card">
        <h3>Ingresos reportados</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($totalIngresosReportados ?? 0)) ?></div>
        <div class="hint">Renta · Honorarios · Inversiones · Otros</div>
    </div>
    <div class="card">
        <h3>Ingreso de referencia</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($ingresoMensual)) ?></div>
        <div class="hint">Mismo total que hub Ingresos / Balance</div>
    </div>
    <div class="card">
        <h3>Total cuotas mensuales</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($totalCuotas ?? 0)) ?></div>
        <div class="hint"><?= count($rows) ?> crédito(s) directos</div>
    </div>
    <div class="card">
        <h3>Carga financiera</h3>
        <div class="value" style="font-size:1.25rem"><?= $cargaPct !== null ? esc((string) $cargaPct) . '%' : '—' ?></div>
        <div class="hint">Cuotas ÷ ingreso de referencia</div>
    </div>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Ingresos por fuente</h3>
    <p class="muted" style="margin-top:0">
        Misma lógica que <a href="<?= site_url('equity/ingresos') ?>">Ingresos</a> y Balance:
        contratos vigentes (sin duplicar arriendos del mismo bien); si no hay, última ficha; Inversiones sin monto usa cánones de bienes.
    </p>
    <table class="table">
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Total mensual</th>
                <th class="no-print"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fuentesIngreso as $clave => $meta): ?>
            <tr>
                <td><?= esc($meta['label'] ?? $clave) ?></td>
                <td><strong><?= esc($fmtDual($totalesFuente[$clave] ?? 0)) ?></strong></td>
                <td class="no-print">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos/' . rawurlencode($clave)) ?>">Abrir →</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td style="text-align:right"><strong>Total ingresos</strong></td>
                <td><strong><?= esc($fmtPesos($totalIngresosReportados ?? 0)) ?></strong></td>
                <td class="no-print"></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Última ficha de ingresos (<?= count($ingresos ?? []) ?>)</h3>
    <p class="muted" style="margin-top:0">
        Corte más reciente por persona (referencia). El total del reporte no suma fichas históricas ni vuelve a agregar arriendos.
    </p>
    <?php if (($ingresos ?? []) === []): ?>
        <p class="muted">Sin ficha de ingreso. Cárgalas en <a href="<?= site_url('equity/ingresos') ?>">Ingresos</a> por fuente.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Corte</th>
                        <th>Renta<br><span class="muted">Fija+Var</span></th>
                        <th>Honorarios<br><span class="muted">Hon+Com+Bon</span></th>
                        <th>Inversiones<br><span class="muted">Arr+Mut+Div</span></th>
                        <th>Otros<br><span class="muted">Pen+Don+Ind+ER</span></th>
                        <th>Total mensual</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ingresos as $f): ?>
                    <?php
                    $p = $f['persona'] ?? null;
                    $renta = (float) ($f['ingreso_fijo'] ?? 0) + (float) ($f['ingreso_variable'] ?? 0);
                    $hon   = (float) ($f['ingreso_honorarios'] ?? 0) + (float) ($f['ingreso_comisiones'] ?? 0) + (float) ($f['ingreso_bonos'] ?? 0);
                    $inv   = (float) ($f['ingreso_arriendos'] ?? 0) + (float) ($f['ingreso_mutuos'] ?? 0) + (float) ($f['ingreso_dividendos'] ?? 0);
                    $otr   = (float) ($f['ingreso_pensiones'] ?? 0) + (float) ($f['ingreso_donaciones'] ?? 0) + (float) ($f['ingreso_indemnizaciones'] ?? 0) + (float) ($f['ingreso_capital_er'] ?? 0) + (float) ($f['ingreso_intereses_er'] ?? 0) + (float) ($f['ingreso_otros'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <?php if ($p): ?>
                                <a href="<?= site_url('equity/' . $p['id']) ?>"><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= esc($f['fecha_corte'] ?? '—') ?></td>
                        <td><?= esc($fmtPesos($renta > 0 ? $renta : null)) ?></td>
                        <td><?= esc($fmtPesos($hon > 0 ? $hon : null)) ?></td>
                        <td><?= esc($fmtPesos($inv > 0 ? $inv : null)) ?></td>
                        <td><?= esc($fmtPesos($otr > 0 ? $otr : null)) ?></td>
                        <td><strong><?= esc($fmtDual($f['ingreso_mensual_total'] ?? $f['renta_liquida'] ?? null)) ?></strong></td>
                        <td class="no-print">
                            <?php if ($p && ! empty($canManage)): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos?persona_id=' . rawurlencode($p['id'])) ?>">Ingresos</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right"><strong>Suma última(s) ficha(s)</strong></td>
                        <td><strong><?= esc($fmtPesos($totalFichasIngresos ?? 0)) ?></strong></td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if (($arriendos ?? []) !== []): ?>
        <h3 style="margin-top:1.5rem">Detalle arriendos — Inversiones (<?= count($arriendos) ?>)</h3>
        <p class="muted" style="margin-top:0">
            Contratos vigentes (un registro por bien). Ya incluidos en el total de Inversiones; no se suman otra vez.
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>Dirección / notas</th>
                    <th>Arrendatario</th>
                    <th>Mes</th>
                    <th>Pago</th>
                    <th>Cánon mensual</th>
                    <th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $mesesNom = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
            foreach ($arriendos as $ar):
                $mesLab = isset($ar['mes_canon'], $mesesNom[(int) $ar['mes_canon']])
                    ? ((int) $ar['mes_canon']) . '. ' . $mesesNom[(int) $ar['mes_canon']]
                    : '—';
                $editHref = ! empty($ar['bien_raiz_id'])
                    ? site_url('equity/activos/bienes-raices/' . $ar['bien_raiz_id'] . '/editar')
                    : ((! empty($ar['origen']) && $ar['origen'] === 'bien' && ! empty($ar['id']))
                        ? site_url('equity/activos/bienes-raices/' . $ar['id'] . '/editar')
                        : site_url('equity/ingresos/inversiones'));
            ?>
                <tr>
                    <td><?= esc($ar['direccion'] ?? $ar['descripcion'] ?? '—') ?></td>
                    <td><?= esc($ar['arrendatario'] ?? '—') ?></td>
                    <td><?= esc($mesLab) ?></td>
                    <td><?= esc($ar['medio_pago'] ?? '—') ?></td>
                    <td><strong><?= esc($fmtDual($ar['canon_show'] ?? null)) ?></strong></td>
                    <td class="no-print">
                        <?php if (! empty($canManage)): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= esc($editHref) ?>">Abrir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right"><strong>Total cánones (detalle)</strong></td>
                    <td><strong><?= esc($fmtPesos($totalArriendos ?? 0)) ?></strong></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <?php if (($ingresosLaborales ?? []) !== []): ?>
        <h3 style="margin-top:1.5rem">Rentas desde antecedentes laborales (<?= count($ingresosLaborales) ?>)</h3>
        <p class="muted" style="margin-top:0">Referencia de la ficha personal / laboral. No se suman al total si ya hay ingresos por fuente.</p>
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Empleador / cargo</th>
                    <th>Renta líquida</th>
                    <th>Renta 2° actividad</th>
                    <th>Total laboral</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ingresosLaborales as $lab): ?>
                <?php
                    $p = $lab['persona'] ?? null;
                    $totLab = (float) ($lab['renta_liquida'] ?? 0) + (float) ($lab['renta_liquida_2'] ?? 0);
                ?>
                <tr>
                    <td>
                        <?php if ($p): ?>
                            <a href="<?= site_url('equity/' . $p['id']) ?>"><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= esc(trim(($lab['empleador'] ?? '') . (! empty($lab['cargo']) ? ' · ' . $lab['cargo'] : '')) ?: '—') ?></td>
                    <td><?= esc($fmtDual($lab['renta_liquida'] ?? null)) ?></td>
                    <td><?= esc($fmtDual($lab['renta_liquida_2'] ?? null)) ?></td>
                    <td><strong><?= esc($fmtPesos($totLab > 0 ? $totLab : null)) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Carga mensual / vencimientos (<?= count($rows) ?>)</h3>
    <p class="muted" style="margin-top:0">
        Créditos con <strong>valor cuota mensual</strong> para revisar vencimientos.
        La carga usa solo deuda directa: Hipotecarios, Circulantes y Otros.
        <?php if ($rowsIndirecta !== []): ?>
            La Deuda indirecta aparece más abajo como referencia (no entra al total de cuotas).
        <?php endif; ?>
    </p>

    <?php if ($rows === []): ?>
        <p class="muted">Sin créditos directos registrados para el filtro actual.</p>
    <?php else: ?>
        <form method="post" action="<?= site_url('equity/situacion-financiera/cuotas') ?>">
            <?= csrf_field() ?>
            <?php if ($personaId !== ''): ?>
                <input type="hidden" name="persona_id" value="<?= esc($personaId) ?>">
            <?php endif; ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Institución financiera</th>
                            <th>Tipo de crédito</th>
                            <th>Fecha de otorgamiento</th>
                            <th>Total del crédito</th>
                            <th>Valor cuota mensual</th>
                            <th>Vigente</th>
                            <th>30 a 59 días</th>
                            <th>60 a 89 días</th>
                            <th>90 o más días</th>
                            <th>Vencimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $nombreP = $row['persona']
                                ? trim(($row['persona']['nombre'] ?? '') . ' ' . ($row['persona']['apellido'] ?? ''))
                                : '';
                        ?>
                        <tr>
                            <td>
                                <?= esc($row['banco_descripcion'] ?? '—') ?>
                                <?php if ($nombreP !== '' && $personaId === ''): ?>
                                    <div class="muted" style="font-size:.85em"><?= esc($nombreP) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['tipo_credito_label'] ?? '—') ?></td>
                            <td><?= esc($row['fecha_otorgamiento'] ?? '—') ?></td>
                            <td><?= esc($fmtPesos($row['total_clp'] ?? null)) ?></td>
                            <td>
                                <?php if (! empty($canManage)): ?>
                                    <label style="display:flex;align-items:center;gap:.35rem;margin:0">
                                        <span>$</span>
                                        <input
                                            type="text"
                                            name="cuota[<?= esc($row['id']) ?>]"
                                            inputmode="decimal"
                                            value="<?= esc($fmtInput($row['cuota_show'] ?? null)) ?>"
                                            placeholder="Ingrese la cuota mensual"
                                            style="min-width:9rem"
                                        >
                                    </label>
                                <?php else: ?>
                                    <?= esc($fmtPesos($row['cuota_show'] ?? null)) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($fmtPesos($row['vigente_show'] ?? null)) ?></td>
                            <td><?= esc($fmtPesos($row['mora_30_show'] ?? null)) ?></td>
                            <td><?= esc($fmtPesos($row['mora_60_show'] ?? null)) ?></td>
                            <td><?= esc($fmtPesos($row['mora_90_show'] ?? null)) ?></td>
                            <td><?= esc($row['fecha_vencimiento'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right"><strong>Total cuotas</strong></td>
                            <td><strong><?= esc($fmtPesos($totalCuotas ?? 0)) ?></strong></td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if (! empty($canManage)): ?>
                <div style="margin-top:1rem" class="no-print">
                    <button class="btn btn-primary" type="submit">Guardar cuotas mensuales</button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php if ($rowsIndirecta !== []): ?>
<div class="card section-card" style="margin-top:1rem">
    <h3>Referencias · Deuda indirecta (<?= count($rowsIndirecta) ?>)</h3>
    <p class="muted" style="margin-top:0">No forma parte de la carga financiera ni del patrimonio neto.</p>
    <div style="overflow-x:auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Institución</th>
                    <th>Tipo</th>
                    <th>Tomador</th>
                    <th>Total</th>
                    <th>Vigente</th>
                    <th>Vencimiento</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rowsIndirecta as $row): ?>
                <tr>
                    <td><?= esc($row['banco_descripcion'] ?? '—') ?></td>
                    <td><?= esc($row['tipo_credito_label'] ?? '—') ?></td>
                    <td>
                        <?= esc($row['tomador_nombre'] ?? '—') ?>
                        <?= ! empty($row['tomador_rut']) ? ' · ' . esc($row['tomador_rut']) : '' ?>
                    </td>
                    <td><?= esc($fmtPesos($row['total_clp'] ?? null)) ?></td>
                    <td><?= esc($fmtPesos($row['vigente_show'] ?? null)) ?></td>
                    <td><?= esc($row['fecha_vencimiento'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
