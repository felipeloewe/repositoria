<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityActivoBitacora;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;

$clave = $meta['clave'];
$ufVigente = $ufVigente ?? null;
$ufRate = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 0, ',', '.');
};
$pairMoney = static function (?float $uf, ?float $clp, float $rate): array {
    if ($uf !== null) {
        return [$uf, round($uf * $rate, 2)];
    }
    if ($clp !== null && $rate > 0) {
        return [round($clp / $rate, 4), $clp];
    }

    return [null, null];
};
$listAction = match ($clave) {
    'bienes-raices'       => site_url('equity/activos/bienes-raices'),
    'vehiculos'           => site_url('equity/activos/vehiculos'),
    'sociedades'          => site_url('equity/activos/sociedades'),
    'otros-activos'       => site_url('equity/activos/otros'),
    'pasivos-inmuebles'   => site_url('equity/pasivos/inmuebles'),
    'pasivos-circulantes' => site_url('equity/pasivos/circulantes'),
    'otros-pasivos'       => site_url('equity/pasivos/otros'),
    'pasivos-indirecta'   => site_url('equity/pasivos/indirecta'),
    'pasivos-lineas'      => site_url('equity/pasivos/lineas'),
    default               => site_url('dashboard'),
};
$reportAction = match ($clave) {
    'bienes-raices'       => site_url('equity/activos/bienes-raices/reporte'),
    'vehiculos'           => site_url('equity/activos/vehiculos/reporte'),
    'sociedades'          => site_url('equity/activos/sociedades/reporte'),
    'otros-activos'       => site_url('equity/activos/otros/reporte'),
    'pasivos-inmuebles'   => site_url('equity/pasivos/inmuebles/reporte'),
    'pasivos-circulantes' => site_url('equity/pasivos/circulantes/reporte'),
    'otros-pasivos'       => site_url('equity/pasivos/otros/reporte'),
    'pasivos-indirecta'   => site_url('equity/pasivos/indirecta/reporte'),
    'pasivos-lineas'      => site_url('equity/pasivos/lineas/reporte'),
    default               => site_url('equity/reporte/balance'),
};
$grupoReportAction = str_starts_with($clave, 'pasivos') || $clave === 'otros-pasivos'
    ? site_url('equity/reporte/pasivos')
    : site_url('equity/reporte/activos');
$esActivo = in_array($clave, ['bienes-raices', 'vehiculos', 'sociedades', 'otros-activos'], true);
$bitacoraPath = static function (string $id) use ($clave, $esActivo): string {
    if (! $esActivo) {
        return '#';
    }
    $seg = match ($clave) {
        'bienes-raices' => 'bienes-raices',
        'vehiculos'     => 'vehiculos',
        'sociedades'    => 'sociedades',
        'otros-activos' => 'otros',
        default         => '',
    };

    return site_url('equity/activos/' . $seg . '/' . $id . '/bitacora');
};
$edit = $edit ?? null;
$isEdit = is_array($edit) && ! empty($edit['id']);
$action = $isEdit ? rtrim($listAction, '/') . '/' . $edit['id'] : $listAction;
$editPath = static function (string $id) use ($listAction): string {
    return rtrim($listAction, '/') . '/' . $id . '/editar';
};
$eliminarPath = static function (string $id) use ($listAction): string {
    return rtrim($listAction, '/') . '/' . $id . '/eliminar';
};
$puedeDarDeBaja = $clave === 'bienes-raices';
$val = static function (string $key, ?int $dec = null) use ($edit): string {
    if (! is_array($edit) || ! array_key_exists($key, $edit) || $edit[$key] === null || $edit[$key] === '') {
        return '';
    }
    $v = $edit[$key];
    if ($dec !== null && is_numeric($v)) {
        return number_format((float) $v, $dec, ',', '');
    }

    return (string) $v;
};
$sel = static function (string $key, $expect) use ($edit): string {
    if (! is_array($edit) || ! array_key_exists($key, $edit)) {
        return '';
    }

    return (string) $edit[$key] === (string) $expect ? 'selected' : '';
};
$chk = static function (string $key) use ($edit): string {
    if (! is_array($edit)) {
        return '';
    }

    return ! empty($edit[$key]) ? 'checked' : '';
};
?>

<section class="banner">
    <h2><?= esc($meta['label']) ?></h2>
    <p>
        <?= esc($meta['grupo']) ?> del estado de situación patrimonial.
        Montos en <strong>UF</strong> (fijos); $ con UF vigente
        <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= $ufVigente ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= esc($reportAction) ?>">Reporte</a>
        <a class="btn btn-ghost btn-sm" href="<?= esc($grupoReportAction) ?>">Reporte <?= esc(str_starts_with($clave, 'pasivos') || $clave === 'otros-pasivos' ? 'pasivos' : 'activos') ?></a>
    </p>
    <?php if ($esActivo): ?>
        <p class="muted" style="margin-top:.6rem;margin-bottom:0;font-size:.9rem">
            Valor comercial<?= $clave === 'bienes-raices' ? ' y avalúo fiscal' : '' ?>:
            los cambios se atribuyen al <strong>cierre del mes en curso</strong>; el mes anterior conserva el valor previo.
        </p>
    <?php endif; ?>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php
$personaLabelSec = '';
foreach ($personas as $p) {
    if (($p['id'] ?? '') === $personaId) {
        $personaLabelSec = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        break;
    }
}
?>
<?php if ($personaLabelSec !== ''): ?>
<div class="card section-card">
    <p style="margin:0">Declarando como <strong><?= esc($personaLabelSec) ?></strong>.</p>
</div>
<?php endif; ?>

<?php if (! empty($canManage) && $personaId !== ''): ?>
<div class="card section-card" id="form-registro">
    <h3><?= $isEdit ? 'Editar registro' : 'Agregar' ?></h3>
    <?php if ($isEdit): ?>
        <p class="muted" style="margin-top:0">
            Editando registro.
            <a href="<?= esc($listAction) ?>">Cancelar</a>
        </p>
    <?php endif; ?>
    <form method="post" action="<?= esc($action) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="persona_id" value="<?= esc($personaId) ?>">

        <?php if ($clave === 'bienes-raices'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="tipo_inmueble">Tipo *</label>
                    <select id="tipo_inmueble" name="tipo_inmueble" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_inmueble', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label for="direccion">Dirección *</label>
                    <input id="direccion" name="direccion" type="text" required placeholder="calle, número, comuna, otros datos" value="<?= esc($val('direccion') !== '' ? $val('direccion') : $val('descripcion')) ?>">
                </div>
                <div>
                    <label for="rol">Rol</label>
                    <input id="rol" name="rol" type="text" value="<?= esc($val('rol')) ?>">
                </div>
                <div>
                    <label for="avaluo_fiscal">Avalúo fiscal $</label>
                    <input id="avaluo_fiscal" name="avaluo_fiscal" type="text" inputmode="decimal" placeholder="41.516.722" value="<?= esc($val('avaluo_fiscal', 0)) ?>">
                </div>
                <div>
                    <label for="garantia">Garantía</label>
                    <select id="garantia" name="garantia">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesGarantiaInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('garantia', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="asegurado">Está asegurado</label>
                    <select id="asegurado" name="asegurado">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('asegurado', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="valor_uf">Valor comercial UF</label>
                    <input id="valor_uf" name="valor_uf" type="text" inputmode="decimal" placeholder="UF fijas" value="<?= esc($val('valor_uf', 4)) ?>">
                </div>
                <div>
                    <label for="valor_clp">Valor comercial $ <span class="muted">(opcional)</span></label>
                    <input id="valor_clp" name="valor_clp" type="text" inputmode="decimal" placeholder="si no ingresas UF" value="<?= esc($val('valor_clp', 0)) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="arrendatario">Arrendatario</label>
                    <input id="arrendatario" name="arrendatario" type="text" placeholder="Nombre del arrendatario" value="<?= esc($val('arrendatario')) ?>">
                </div>
                <div>
                    <label for="canon_mensual_clp">Cánon / arriendo mensual $</label>
                    <input id="canon_mensual_clp" name="canon_mensual_clp" type="text" inputmode="decimal" placeholder="340.000" value="<?= esc($val('canon_mensual_clp', 0)) ?>">
                </div>
                <div>
                    <label for="canon_mensual_uf">Cánon mensual UF <span class="muted">(opc.)</span></label>
                    <input id="canon_mensual_uf" name="canon_mensual_uf" type="text" inputmode="decimal" value="<?= esc($val('canon_mensual_uf', 4)) ?>">
                </div>
                <div>
                    <label for="mes_canon">Mes (cánon)</label>
                    <select id="mes_canon" name="mes_canon">
                        <option value="">—</option>
                        <?php
                        $mesesCanon = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                        foreach ($mesesCanon as $n => $lab):
                        ?>
                            <option value="<?= $n ?>" <?= $sel('mes_canon', $n) ?>><?= $n ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="medio_pago">Medio / banco de pago</label>
                    <input id="medio_pago" name="medio_pago" type="text" placeholder="BCI, MUNAY…" value="<?= esc($val('medio_pago')) ?>">
                </div>
            </div>
        <?php elseif ($clave === 'vehiculos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div>
                    <label for="tipo_vehiculo">Tipo *</label>
                    <select id="tipo_vehiculo" name="tipo_vehiculo" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoVehiculo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_vehiculo', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="marca">Marca</label><input id="marca" name="marca" type="text" value="<?= esc($val('marca')) ?>"></div>
                <div><label for="modelo">Modelo</label><input id="modelo" name="modelo" type="text" value="<?= esc($val('modelo')) ?>"></div>
                <div><label for="anio">Año</label><input id="anio" name="anio" type="number" value="<?= esc($val('anio')) ?>"></div>
                <div><label for="patente">Patente</label><input id="patente" name="patente" type="text" value="<?= esc($val('patente')) ?>"></div>
                <div>
                    <label for="garantia_v">Garantía</label>
                    <select id="garantia_v" name="garantia">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesGarantiaInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('garantia', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="asegurado_v">Está asegurado</label>
                    <select id="asegurado_v" name="asegurado">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('asegurado', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="valor_uf_v">Valor comercial UF *</label><input id="valor_uf_v" name="valor_uf" type="text" inputmode="decimal" value="<?= esc($val('valor_uf', 4)) ?>"></div>
                <div><label for="valor_clp_v">Valor comercial $ <span class="muted">(opc.)</span></label><input id="valor_clp_v" name="valor_clp" type="text" inputmode="decimal" value="<?= esc($val('valor_clp', 0)) ?>"></div>
            </div>
        <?php elseif ($clave === 'sociedades'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="nombre_sociedad">Razón social *</label>
                    <input id="nombre_sociedad" name="nombre_sociedad" type="text" required value="<?= esc($val('nombre_sociedad')) ?>">
                </div>
                <div>
                    <label for="rut_sociedad">RUT sociedad</label>
                    <input id="rut_sociedad" name="rut_sociedad" type="text" value="<?= esc($val('rut_sociedad')) ?>">
                </div>
                <div>
                    <label for="giro_rubro">Giro / Rubro</label>
                    <input id="giro_rubro" name="giro_rubro" type="text" value="<?= esc($val('giro_rubro')) ?>">
                </div>
                <div>
                    <label for="porcentaje">% participación</label>
                    <input id="porcentaje" name="porcentaje" type="text" inputmode="decimal" value="<?= esc($val('porcentaje', 2)) ?>">
                </div>
                <div>
                    <label for="valor_uf_s">Valor comercial UF *</label>
                    <input id="valor_uf_s" name="valor_uf" type="text" inputmode="decimal" value="<?= esc($val('valor_uf', 4)) ?>">
                </div>
                <div>
                    <label for="valor_clp_s">Valor comercial $ <span class="muted">(opc.)</span></label>
                    <input id="valor_clp_s" name="valor_clp" type="text" inputmode="decimal" value="<?= esc($val('valor_clp', 0)) ?>">
                </div>
                <div>
                    <label for="emp_relacionada">Emp. relacionada</label>
                    <select id="emp_relacionada" name="emp_relacionada">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('emp_relacionada', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label style="display:flex;gap:.5rem;align-items:center;margin:.75rem 0">
                <input type="checkbox" name="cumple_articulo_84" value="1" <?= $chk('cumple_articulo_84') ?>> Cumple artículo N° 84
            </label>
        <?php elseif ($clave === 'otros-activos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="tipo_activo">Tipo *</label>
                    <select id="tipo_activo" name="tipo_activo" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoOtroActivo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_activo', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="institucion">Institución</label>
                    <input id="institucion" name="institucion" type="text" value="<?= esc($val('institucion')) ?>">
                </div>
                <div>
                    <label for="saldo_uf_oa">Saldo UF *</label>
                    <input id="saldo_uf_oa" name="saldo_uf" type="text" inputmode="decimal" value="<?= esc($val('saldo_uf', 4)) ?>">
                </div>
                <div>
                    <label for="saldo_clp">Saldo $ <span class="muted">(opc.)</span></label>
                    <input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal" value="<?= esc($val('saldo_clp', 0)) ?>">
                </div>
            </div>
        <?php elseif ($clave === 'pasivos-inmuebles'): ?>
            <p class="muted" style="margin-top:0">
                Datos del crédito hipotecario (recibo de dividendo). La <strong>moneda</strong> define el valor canónico:
                si es UF se calcula el $ (cuota se paga en pesos); si es CLP se calcula la UF. Ingresos/Egresos analizan siempre en $.
            </p>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="banco_descripcion">Institución / Banco *</label>
                    <input id="banco_descripcion" name="banco_descripcion" type="text" required placeholder="Scotiabank, Santander, BCI, Itaú…" value="<?= esc($val('banco_descripcion')) ?>">
                </div>
                <div>
                    <label for="numero_credito">N° crédito / préstamo</label>
                    <input id="numero_credito" name="numero_credito" type="text" placeholder="0224059" value="<?= esc($val('numero_credito')) ?>">
                </div>
                <div>
                    <label for="sucursal">Sucursal (SUC)</label>
                    <input id="sucursal" name="sucursal" type="text" placeholder="143" value="<?= esc($val('sucursal')) ?>">
                </div>
                <div>
                    <label for="moneda">Moneda del crédito</label>
                    <select id="moneda" name="moneda">
                        <option value="UF" <?= ($val('moneda') === '' || $val('moneda') === 'UF') ? 'selected' : '' ?>>UF (calcula $)</option>
                        <option value="CLP" <?= $sel('moneda', 'CLP') ?>>$ / CLP (calcula UF)</option>
                    </select>
                </div>
                <div>
                    <label for="saldo_uf">Saldo deuda UF (Capital) *</label>
                    <input id="saldo_uf" name="saldo_uf" type="text" inputmode="decimal" placeholder="923,4434" value="<?= esc($val('saldo_uf', 4)) ?>">
                </div>
                <div>
                    <label for="saldo_clp_h">Saldo deuda $ <span class="muted">(opc.)</span></label>
                    <input id="saldo_clp_h" name="saldo_clp" type="text" inputmode="decimal" value="<?= esc($val('saldo_clp', 0)) ?>">
                </div>
                <div>
                    <label for="plazo_pactado">Plazo pactado (cuotas)</label>
                    <input id="plazo_pactado" name="plazo_pactado" type="number" min="1" placeholder="240" value="<?= esc($val('plazo_pactado')) ?>">
                </div>
                <div>
                    <label for="cuotas_pagadas">Dividendos cancelados</label>
                    <input id="cuotas_pagadas" name="cuotas_pagadas" type="number" min="0" placeholder="110" value="<?= esc($val('cuotas_pagadas')) ?>">
                </div>
                <div>
                    <label for="dividendo_n">N° dividendo actual</label>
                    <input id="dividendo_n" name="dividendo_n" type="number" min="1" value="<?= esc($val('dividendo_n')) ?>">
                </div>
                <div>
                    <label for="fecha_registro">Fecha de registro (dividendo)</label>
                    <input id="fecha_registro" name="fecha_registro" type="date" value="<?= esc($val('fecha_registro')) ?>">
                    <p class="muted" style="margin:0.25rem 0 0;font-size:0.8rem">Periodo del recibo actual (p. ej. oct / nov / dic). No altera saldos; sirve para normalizar y proyectar egresos.</p>
                </div>
                <div>
                    <label for="fecha_otorgamiento_h">Fecha de otorgamiento</label>
                    <input id="fecha_otorgamiento_h" name="fecha_otorgamiento" type="date" value="<?= esc($val('fecha_otorgamiento')) ?>">
                </div>
                <div>
                    <label for="fecha_vencimiento">Fecha de vencimiento</label>
                    <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="<?= esc($val('fecha_vencimiento')) ?>">
                </div>
                <div>
                    <label for="total_credito_uf_h">Monto otorgado UF</label>
                    <input id="total_credito_uf_h" name="total_credito_uf" type="text" inputmode="decimal" placeholder="1.457" value="<?= esc($val('total_credito_uf', 4)) ?>">
                </div>
                <div>
                    <label for="dividendo_uf">Total dividendo UF</label>
                    <input id="dividendo_uf" name="dividendo_uf" type="text" inputmode="decimal" placeholder="8,5686" value="<?= esc($val('dividendo_uf', 4)) ?>">
                </div>
                <div>
                    <label for="dividendo_clp">Total dividendo $ <span class="muted">(opc.)</span></label>
                    <input id="dividendo_clp" name="dividendo_clp" type="text" inputmode="decimal" value="<?= esc($val('dividendo_clp', 0)) ?>">
                </div>
                <div>
                    <label for="amortizacion_uf">Amortización UF</label>
                    <input id="amortizacion_uf" name="amortizacion_uf" type="text" inputmode="decimal" value="<?= esc($val('amortizacion_uf', 4)) ?>">
                </div>
                <div>
                    <label for="interes_uf">Interés UF</label>
                    <input id="interes_uf" name="interes_uf" type="text" inputmode="decimal" value="<?= esc($val('interes_uf', 4)) ?>">
                </div>
                <div>
                    <label for="comision_uf">Comisión UF</label>
                    <input id="comision_uf" name="comision_uf" type="text" inputmode="decimal" value="<?= esc($val('comision_uf', 4)) ?>">
                </div>
                <div>
                    <label for="seg_incendio_uf">Seg. incendio / propiedad UF</label>
                    <input id="seg_incendio_uf" name="seg_incendio_uf" type="text" inputmode="decimal" value="<?= esc($val('seg_incendio_uf', 4)) ?>">
                </div>
                <div>
                    <label for="seg_desgravamen_uf">Seg. desgravamen UF</label>
                    <input id="seg_desgravamen_uf" name="seg_desgravamen_uf" type="text" inputmode="decimal" value="<?= esc($val('seg_desgravamen_uf', 4)) ?>">
                </div>
                <div>
                    <label for="seg_otros_complementarios_uf">Otros seg. complementarios UF <span class="muted">(opc.)</span></label>
                    <input id="seg_otros_complementarios_uf" name="seg_otros_complementarios_uf" type="text" inputmode="decimal" value="<?= esc($val('seg_otros_complementarios_uf', 4)) ?>">
                </div>
                <div>
                    <label for="seg_adicionales_uf">Seg. adicionales UF</label>
                    <input id="seg_adicionales_uf" name="seg_adicionales_uf" type="text" inputmode="decimal" value="<?= esc($val('seg_adicionales_uf', 4)) ?>">
                </div>
                <div>
                    <label for="cuenta_pac">Cuenta PAC <span class="muted">(opc.)</span></label>
                    <input id="cuenta_pac" name="cuenta_pac" type="text" placeholder="CUENTA PAC ITAÚ" value="<?= esc($val('cuenta_pac')) ?>">
                </div>
                <div>
                    <label for="dia_cargo_pac">Día cargo PAC <span class="muted">(opc.)</span></label>
                    <input id="dia_cargo_pac" name="dia_cargo_pac" type="number" min="1" max="31" placeholder="10" value="<?= esc($val('dia_cargo_pac')) ?>">
                </div>
                <div>
                    <label for="asociacion">Asociación</label>
                    <input id="asociacion" name="asociacion" type="text" placeholder="CASA / INV" value="<?= esc($val('asociacion')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="direccion_garantia">Dirección garantía / inmueble</label>
                    <input id="direccion_garantia" name="direccion_garantia" type="text" placeholder="Pinares de Montemar 138, Concón" value="<?= esc($val('direccion_garantia')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="seguros_info">Seguros contratados</label>
                    <textarea id="seguros_info" name="seguros_info" rows="2" placeholder="Compañía y póliza colectiva"><?= esc($val('seguros_info')) ?></textarea>
                </div>
            </div>
        <?php elseif ($clave === 'pasivos-indirecta'): ?>
            <p class="muted" style="margin-top:0">Deudas adquiridas por un tercero donde eres codeudor, fiador o avalista.</p>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="banco_descripcion">Institución financiera *</label>
                    <input id="banco_descripcion" name="banco_descripcion" type="text" required placeholder="Banco Consorcio, Banco Bice…" value="<?= esc($val('banco_descripcion')) ?>">
                </div>
                <div>
                    <label for="tipo_credito">Tipo de crédito *</label>
                    <input id="tipo_credito" name="tipo_credito" type="text" required placeholder="Comercial" value="<?= esc($val('tipo')) ?>">
                </div>
                <div>
                    <label for="fecha_otorgamiento">Fecha de otorgamiento</label>
                    <input id="fecha_otorgamiento" name="fecha_otorgamiento" type="date" value="<?= esc($val('fecha_otorgamiento')) ?>">
                </div>
                <div>
                    <label for="total_credito_uf">Total del crédito UF</label>
                    <input id="total_credito_uf" name="total_credito_uf" type="text" inputmode="decimal" value="<?= esc($val('total_credito_uf', 4)) ?>">
                </div>
                <div>
                    <label for="total_credito_clp">Total del crédito $</label>
                    <input id="total_credito_clp" name="total_credito_clp" type="text" inputmode="decimal" value="<?= esc($val('total_credito_clp', 0)) ?>">
                </div>
                <div>
                    <label for="vigente_uf">Vigente UF</label>
                    <input id="vigente_uf" name="vigente_uf" type="text" inputmode="decimal" value="<?= esc($val('vigente_uf', 4)) ?>">
                </div>
                <div>
                    <label for="vigente_clp">Vigente $</label>
                    <input id="vigente_clp" name="vigente_clp" type="text" inputmode="decimal" value="<?= esc($val('vigente_clp', 0)) ?>">
                </div>
                <div>
                    <label for="mora_30_59_uf">30 a 59 días UF</label>
                    <input id="mora_30_59_uf" name="mora_30_59_uf" type="text" inputmode="decimal" value="<?= esc($val('mora_30_59_uf', 4)) ?>">
                </div>
                <div>
                    <label for="mora_30_59_clp">30 a 59 días $</label>
                    <input id="mora_30_59_clp" name="mora_30_59_clp" type="text" inputmode="decimal" value="<?= esc($val('mora_30_59_clp', 0)) ?>">
                </div>
                <div>
                    <label for="mora_60_89_uf">60 a 89 días UF</label>
                    <input id="mora_60_89_uf" name="mora_60_89_uf" type="text" inputmode="decimal" value="<?= esc($val('mora_60_89_uf', 4)) ?>">
                </div>
                <div>
                    <label for="mora_60_89_clp">60 a 89 días $</label>
                    <input id="mora_60_89_clp" name="mora_60_89_clp" type="text" inputmode="decimal" value="<?= esc($val('mora_60_89_clp', 0)) ?>">
                </div>
                <div>
                    <label for="mora_90_mas_uf">90 o más días UF</label>
                    <input id="mora_90_mas_uf" name="mora_90_mas_uf" type="text" inputmode="decimal" value="<?= esc($val('mora_90_mas_uf', 4)) ?>">
                </div>
                <div>
                    <label for="mora_90_mas_clp">90 o más días $</label>
                    <input id="mora_90_mas_clp" name="mora_90_mas_clp" type="text" inputmode="decimal" value="<?= esc($val('mora_90_mas_clp', 0)) ?>">
                </div>
            </div>
        <?php elseif ($clave === 'pasivos-lineas'): ?>
            <p class="muted" style="margin-top:0">Créditos disponibles (montos no utilizados): líneas de crédito y otros créditos. Directos e indirectos.</p>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="tipo_linea">Tipo *</label>
                    <select id="tipo_linea" name="tipo_linea" required>
                        <option value="">—</option>
                        <?php
                        $tipoLineaSel = '';
                        if ($val('tipo') !== '') {
                            foreach (EquityCatalogo::opcionesTipoLineaDisponible() as $k => $lab) {
                                if ($lab === $val('tipo') || (string) $k === $val('tipo')) {
                                    $tipoLineaSel = (string) $k;
                                    break;
                                }
                            }
                        }
                        foreach (EquityCatalogo::opcionesTipoLineaDisponible() as $k => $lab):
                        ?>
                            <option value="<?= (int) $k ?>" <?= ((string) $k === $tipoLineaSel || $sel('tipo', $k) !== '') ? 'selected' : '' ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label for="banco_descripcion">Institución financiera *</label>
                    <input id="banco_descripcion" name="banco_descripcion" type="text" required placeholder="Banco Itaú Chile, Scotiabank…" value="<?= esc($val('banco_descripcion')) ?>">
                </div>
                <div>
                    <label for="monto_directo_uf">Directos UF</label>
                    <input id="monto_directo_uf" name="monto_directo_uf" type="text" inputmode="decimal" value="<?= esc($val('monto_directo_uf', 4)) ?>">
                </div>
                <div>
                    <label for="monto_directo_clp">Directos $</label>
                    <input id="monto_directo_clp" name="monto_directo_clp" type="text" inputmode="decimal" placeholder="ej. 4.777.712" value="<?= esc($val('monto_directo_clp', 0)) ?>">
                </div>
                <div>
                    <label for="monto_indirecto_uf">Indirectos UF</label>
                    <input id="monto_indirecto_uf" name="monto_indirecto_uf" type="text" inputmode="decimal" value="<?= esc($val('monto_indirecto_uf', 4)) ?>">
                </div>
                <div>
                    <label for="monto_indirecto_clp">Indirectos $</label>
                    <input id="monto_indirecto_clp" name="monto_indirecto_clp" type="text" inputmode="decimal" placeholder="ej. 3.000.000" value="<?= esc($val('monto_indirecto_clp', 0)) ?>">
                </div>
            </div>
        <?php else: ?>
            <p class="muted" style="margin-top:0">
                Moneda del crédito: si es UF se calcula el $; si es CLP se calcula la UF. La cuota de análisis (Egresos) queda en pesos.
            </p>
            <label for="banco_descripcion">Banco / descripción *</label>
            <input id="banco_descripcion" name="banco_descripcion" type="text" required value="<?= esc($val('banco_descripcion')) ?>">
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div>
                    <label for="moneda_otro">Moneda</label>
                    <select id="moneda_otro" name="moneda">
                        <option value="CLP" <?= ($val('moneda') === '' || $val('moneda') === 'CLP') ? 'selected' : '' ?>>$ / CLP (calcula UF)</option>
                        <option value="UF" <?= $sel('moneda', 'UF') ?>>UF (calcula $)</option>
                    </select>
                </div>
                <div><label for="saldo_uf">Saldo UF</label><input id="saldo_uf" name="saldo_uf" type="text" inputmode="decimal" value="<?= esc($val('saldo_uf', 4)) ?>"></div>
                <div><label for="saldo_clp">Saldo $</label><input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal" value="<?= esc($val('saldo_clp', 0)) ?>"></div>
                <div><label for="cuotas_totales">Cuotas totales</label><input id="cuotas_totales" name="cuotas_totales" type="number" value="<?= esc($val('cuotas_totales')) ?>"></div>
                <div><label for="cuotas_pagadas">Cuotas pagadas</label><input id="cuotas_pagadas" name="cuotas_pagadas" type="number" value="<?= esc($val('cuotas_pagadas')) ?>"></div>
                <div><label for="cuota_uf">Cuota mensual UF</label><input id="cuota_uf" name="cuota_uf" type="text" inputmode="decimal" value="<?= esc($val('dividendo_uf', 4)) ?>"></div>
                <div><label for="cuota_mensual">Cuota mensual $</label><input id="cuota_mensual" name="cuota_mensual" type="text" inputmode="decimal" value="<?= esc($val('cuota_mensual', 0)) ?>"></div>
                <div><label for="asociacion">Asociación</label><input id="asociacion" name="asociacion" type="text" placeholder="CASA / INV" value="<?= esc($val('asociacion')) ?>"></div>
                <div><label for="tipo">Tipo</label><input id="tipo" name="tipo" type="text" value="<?= esc($val('tipo')) ?>"></div>
            </div>
        <?php endif; ?>

        <label for="notas">Notas</label>
        <textarea id="notas" name="notas" rows="2"><?= esc($val('notas')) ?></textarea>
        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Actualizar' : 'Guardar' ?></button>
            <?php if ($isEdit): ?>
                <a class="btn btn-ghost" href="<?= esc($listAction) ?>">Cancelar</a>
                <?php if ($esActivo): ?>
                    <a class="btn btn-ghost" href="<?= esc($bitacoraPath((string) $edit['id'])) ?>">Bitácora de valores</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($isEdit && $puedeDarDeBaja): ?>
        <form method="post" action="<?= esc($eliminarPath((string) $edit['id'])) ?>" style="margin-top:.75rem" onsubmit="return confirm('¿Dar de baja este bien raíz? Saldrá de listados y totales; el historial se conserva.');">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit">Dar de baja</button>
        </form>
    <?php endif; ?>
</div>
<?php if ($isEdit && $esActivo): ?>
    <?php $bitacoraRows = $bitacora ?? []; ?>
    <div class="card section-card">
        <h3>Bitácora de valores (cierres de mes)</h3>
        <p class="muted" style="margin-top:0">
            Si cambias el valor hoy, queda como corte de fin de este mes; el mes anterior mantiene el monto anterior.
        </p>
        <?php if ($bitacoraRows === []): ?>
            <p class="muted">Sin cortes aún. Se crearán al guardar un valor comercial<?= $clave === 'bienes-raices' ? ' o avalúo fiscal' : '' ?>.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Cierre</th>
                        <th>Campo</th>
                        <th>UF</th>
                        <th>$</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($bitacoraRows, 0, 8) as $h): ?>
                    <tr>
                        <td><?= esc((string) ($h['fecha_cierre'] ?? '')) ?></td>
                        <td><?= esc(EquityActivoBitacora::labelCampo((string) ($h['campo'] ?? ''))) ?></td>
                        <td>
                            <?php if (($h['campo'] ?? '') === EquityActivoBitacora::CAMPO_AVALUO_FISCAL): ?>
                                —
                            <?php else: ?>
                                <?= esc(($h['valor_uf'] ?? null) !== null && $h['valor_uf'] !== '' ? EquityUf::fmtUf((float) $h['valor_uf'], 2) : '—') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= esc(($h['valor_clp'] ?? null) !== null && $h['valor_clp'] !== '' ? EquityUf::fmtPesos((float) $h['valor_clp'], 0) : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:.5rem">
                <a class="btn btn-ghost btn-sm" href="<?= esc($bitacoraPath((string) $edit['id'])) ?>">Ver bitácora completa</a>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php elseif ($personaId === ''): ?>
    <div class="alert alert-info">No hay persona activa en la sesión. La matriz debe inscribirte en <a href="<?= site_url('equity/grupo') ?>">Grupo / Usuarios</a>, o elige a quién representas al iniciar sesión.</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Registros (<?= count($rows) ?>)</h3>
    <?php if ($rows === []): ?>
        <p class="muted">Sin registros en esta sección.</p>
    <?php else: ?>
        <?php
            $totalUf = 0.0;
            $totalClp = 0.0;
            foreach ($rows as $rTot) {
                $ufRaw = match ($clave) {
                    'bienes-raices', 'vehiculos', 'sociedades' => $rTot['valor_uf'] ?? null,
                    default => $rTot['saldo_uf'] ?? null,
                };
                $clpRaw = match ($clave) {
                    'bienes-raices', 'vehiculos', 'sociedades' => $rTot['valor_clp'] ?? null,
                    default => $rTot['saldo_clp'] ?? null,
                };
                [$u, $c] = $pairMoney(
                    ($ufRaw !== null && $ufRaw !== '') ? (float) $ufRaw : null,
                    ($clpRaw !== null && $clpRaw !== '') ? (float) $clpRaw : null,
                    $ufRate
                );
                if ($u !== null) {
                    $totalUf += $u;
                }
                if ($c !== null) {
                    $totalClp += $c;
                }
            }
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Detalle</th>
                    <th>UF</th>
                    <th>$ (vigente)</th>
                    <?php if (! empty($canManage)): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $persona = $row['persona'] ?? null;
                    $nombreP = $persona ? trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')) : '—';
                    $detalle = match ($clave) {
                        'bienes-raices' => trim(
                            EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoInmueble(), $row['tipo_inmueble'] ?? null)
                            . ' · '
                            . (($row['direccion'] ?? '') !== '' ? (string) $row['direccion'] : (string) ($row['descripcion'] ?? ''))
                            . (! empty($row['arrendatario']) ? ' · Arr. ' . $row['arrendatario'] : '')
                            . (! empty($row['canon_mensual_clp']) ? ' · Cánon $' . number_format((float) $row['canon_mensual_clp'], 0, ',', '.') : '')
                            . (! empty($row['medio_pago']) ? ' · Pago ' . $row['medio_pago'] : '')
                            . (! empty($row['mes_canon']) ? ' · Mes ' . $row['mes_canon'] : '')
                            . (! empty($row['rol']) ? ' · Rol ' . $row['rol'] : '')
                            . (! empty($row['garantia']) ? ' · ' . EquityCatalogo::labelOpcion(EquityCatalogo::opcionesGarantiaInmueble(), $row['garantia']) : ''),
                            ' ·'
                        ),
                        'vehiculos' => trim(
                            EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoVehiculo(), $row['tipo_vehiculo'] ?? null)
                            . ' · '
                            . trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '') . ' ' . ($row['patente'] ?? ''))
                            . (! empty($row['garantia']) ? ' · ' . EquityCatalogo::labelOpcion(EquityCatalogo::opcionesGarantiaInmueble(), $row['garantia']) : ''),
                            ' ·'
                        ),
                        'sociedades' => trim(
                            (string) ($row['nombre_sociedad'] ?? '')
                            . (! empty($row['rut_sociedad']) ? ' · ' . $row['rut_sociedad'] : '')
                            . (! empty($row['porcentaje']) ? ' · ' . $row['porcentaje'] . '%' : '')
                            . (! empty($row['emp_relacionada']) ? ' · Relacionada: ' . EquityCatalogo::labelOpcion(EquityCatalogo::opcionesSiNo(), $row['emp_relacionada']) : '')
                            . (! empty($row['cumple_articulo_84']) ? ' · Art. 84' : ''),
                            ' ·'
                        ),
                        'otros-activos' => trim(
                            EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoOtroActivo(), $row['tipo_activo'] ?? null)
                            . ' · '
                            . (string) ($row['institucion'] ?? ''),
                            ' ·'
                        ),
                        'pasivos-inmuebles' => trim(
                            (string) ($row['banco_descripcion'] ?? '')
                            . (! empty($row['numero_credito']) ? ' · Crédito ' . $row['numero_credito'] : '')
                            . (! empty($row['dividendo_n']) ? ' · Div. ' . $row['dividendo_n'] : '')
                            . (! empty($row['fecha_registro']) ? ' · Reg. ' . $row['fecha_registro'] : '')
                            . (! empty($row['plazo_pactado']) ? ' · Plazo ' . $row['plazo_pactado'] : '')
                            . (! empty($row['cuotas_pagadas']) ? ' · Cancelados ' . $row['cuotas_pagadas'] : '')
                            . (! empty($row['dividendo_uf']) ? ' · Div. ' . number_format((float) $row['dividendo_uf'], 4, ',', '.') . ' UF' : '')
                            . (! empty($row['asociacion']) ? ' · ' . $row['asociacion'] : '')
                            . (! empty($row['direccion_garantia']) ? ' · ' . $row['direccion_garantia'] : ''),
                            ' ·'
                        ),
                        'pasivos-indirecta' => trim(
                            (string) ($row['banco_descripcion'] ?? '')
                            . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                            . (! empty($row['fecha_otorgamiento']) ? ' · ' . $row['fecha_otorgamiento'] : '')
                            . (! empty($row['total_credito_clp']) ? ' · Total $' . number_format((float) $row['total_credito_clp'], 0, ',', '.') : '')
                            . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
                            ' ·'
                        ),
                        'pasivos-lineas' => trim(
                            (string) ($row['tipo'] ?? 'Línea')
                            . ' · '
                            . (string) ($row['banco_descripcion'] ?? '')
                            . (! empty($row['monto_directo_clp']) ? ' · Dir. $' . number_format((float) $row['monto_directo_clp'], 0, ',', '.') : '')
                            . (! empty($row['monto_indirecto_clp']) ? ' · Ind. $' . number_format((float) $row['monto_indirecto_clp'], 0, ',', '.') : ''),
                            ' ·'
                        ),
                        default => (string) ($row['banco_descripcion'] ?? ''),
                    };
                    $ufRaw = match ($clave) {
                        'bienes-raices', 'vehiculos', 'sociedades' => $row['valor_uf'] ?? null,
                        default => $row['saldo_uf'] ?? null,
                    };
                    $clpRaw = match ($clave) {
                        'bienes-raices', 'vehiculos', 'sociedades' => $row['valor_clp'] ?? null,
                        default => $row['saldo_clp'] ?? null,
                    };
                    [$ufShow, $clpShow] = $pairMoney(
                        ($ufRaw !== null && $ufRaw !== '') ? (float) $ufRaw : null,
                        ($clpRaw !== null && $clpRaw !== '') ? (float) $clpRaw : null,
                        $ufRate
                    );
                ?>
                <tr>
                    <td>
                        <?php if ($persona): ?>
                            <a href="<?= site_url('equity/' . $persona['id']) ?>"><?= esc($nombreP) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= esc($detalle !== '' ? $detalle : '—') ?></td>
                    <td><?= esc($ufShow !== null ? EquityUf::fmtUf($ufShow, 2) : '—') ?></td>
                    <td><?= esc($clpShow !== null ? EquityUf::fmtPesos($clpShow, 0) : '—') ?></td>
                    <?php if (! empty($canManage)): ?>
                        <td style="white-space:nowrap">
                            <a class="btn btn-ghost btn-sm" href="<?= esc($editPath((string) $row['id'])) ?>">Editar</a>
                            <?php if ($esActivo): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= esc($bitacoraPath((string) $row['id'])) ?>">Bitácora</a>
                            <?php endif; ?>
                            <?php if ($puedeDarDeBaja): ?>
                                <form method="post" action="<?= esc($eliminarPath((string) $row['id'])) ?>" style="display:inline" onsubmit="return confirm('¿Dar de baja este bien raíz? Saldrá de listados y totales; el historial se conserva.');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit">Dar de baja</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Total</strong></td>
                    <td><strong><?= esc(EquityUf::fmtUf($totalUf, 2)) ?></strong></td>
                    <td><strong><?= esc(EquityUf::fmtPesos($totalClp, 0)) ?></strong></td>
                    <?php if (! empty($canManage)): ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
