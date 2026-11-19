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
?>

<section class="banner">
    <h2>Situación financiera</h2>
    <p>
        Reporte de ingresos informados y carga mensual de créditos (cuotas / vencimientos).
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= ! empty($ufVigente['fecha_cierre']) ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <form method="get" action="<?= site_url('equity/situacion-financiera') ?>" class="form-row" style="grid-template-columns:1fr auto">
        <div>
            <label for="persona_id">Persona</label>
            <select id="persona_id" name="persona_id">
                <option value="">Todas</option>
                <?php foreach ($personas as $p): ?>
                    <option value="<?= esc($p['id']) ?>" <?= $personaId === $p['id'] ? 'selected' : '' ?>>
                        <?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<div class="grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <div class="card">
        <h3>Ingresos reportados</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($totalIngresosReportados ?? 0)) ?></div>
        <div class="hint"><?= count($ingresos ?? []) ?> ficha(s)</div>
    </div>
    <div class="card">
        <h3>Ingreso de referencia</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($ingresoMensual)) ?></div>
        <div class="hint"><?= $personaId !== '' ? 'Última ficha / laboral de la persona' : 'Filtra por persona' ?></div>
    </div>
    <div class="card">
        <h3>Total cuotas mensuales</h3>
        <div class="value" style="font-size:1.25rem"><?= esc($fmtPesos($totalCuotas ?? 0)) ?></div>
        <div class="hint"><?= count($rows) ?> crédito(s)</div>
    </div>
    <div class="card">
        <h3>Carga financiera</h3>
        <div class="value" style="font-size:1.25rem"><?= $cargaPct !== null ? esc((string) $cargaPct) . '%' : '—' ?></div>
        <div class="hint">Cuotas ÷ ingreso de referencia</div>
    </div>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Reporte de ingresos informados (<?= count($ingresos ?? []) ?>)</h3>
    <p class="muted" style="margin-top:0">Fichas de ingreso registradas. Luego se podrá ampliar con más fuentes.</p>
    <?php if (($ingresos ?? []) === []): ?>
        <p class="muted">Sin fichas de ingreso. Cárgalas en Ingresos o desde la ficha de cada persona.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Persona</th>
                        <th>Fecha corte</th>
                        <th>Renta líquida</th>
                        <th>Fijo</th>
                        <th>Honorarios</th>
                        <th>Arriendos</th>
                        <th>Variable</th>
                        <th>Comisiones</th>
                        <th>Pensiones</th>
                        <th>Bonos</th>
                        <th>Otros</th>
                        <th>Total ingreso mensual</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ingresos as $f): ?>
                    <?php $p = $f['persona'] ?? null; ?>
                    <tr>
                        <td>
                            <?php if ($p): ?>
                                <a href="<?= site_url('equity/' . $p['id']) ?>"><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= esc($f['fecha_corte'] ?? '—') ?></td>
                        <td><?= esc($fmtDual($f['renta_liquida'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_fijo'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_honorarios'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_arriendos'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_variable'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_comisiones'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_pensiones'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_bonos'] ?? null)) ?></td>
                        <td><?= esc($fmtPesos($f['ingreso_otros'] ?? null)) ?></td>
                        <td><strong><?= esc($fmtDual($f['ingreso_mensual_total'] ?? $f['renta_liquida'] ?? null)) ?></strong></td>
                        <td>
                            <?php if ($p && ! empty($canManage)): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $p['id'] . '/fichas/' . $f['id'] . '/editar') ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="11" style="text-align:right"><strong>Suma totales fichas</strong></td>
                        <td><strong><?= esc($fmtPesos($totalIngresosReportados ?? 0)) ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if (($ingresosLaborales ?? []) !== []): ?>
        <h3 style="margin-top:1.5rem">Rentas desde antecedentes laborales (<?= count($ingresosLaborales) ?>)</h3>
        <p class="muted" style="margin-top:0">Informadas en la ficha personal / laboral (referencia adicional).</p>
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
        Los montos se toman de Hipotecarios, Circulantes, Otros pasivos y Deuda indirecta.
    </p>

    <?php if ($rows === []): ?>
        <p class="muted">Sin créditos registrados para el filtro actual.</p>
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
                <div style="margin-top:1rem">
                    <button class="btn btn-primary" type="submit">Guardar cuotas mensuales</button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
