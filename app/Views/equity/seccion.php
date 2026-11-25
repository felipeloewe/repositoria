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
            <?php
            $moneyPair = static function (
                string $base,
                string $label,
                string $idSuffix = '',
                bool $opcAlways = false
            ) use ($val): void {
                $idUf  = $base . '_uf' . $idSuffix;
                $idClp = $base . '_clp' . $idSuffix;
                ?>
                <div data-money-side="uf">
                    <label for="<?= esc($idUf) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="UF"><?= esc($label) ?> UF</label>
                    <input id="<?= esc($idUf) ?>" name="<?= esc($base) ?>_uf" type="text" inputmode="decimal" value="<?= esc($val($base . '_uf', 4)) ?>">
                </div>
                <div data-money-side="clp">
                    <label for="<?= esc($idClp) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="CLP"><?= esc($label) ?> $<?= $opcAlways ? ' <span class="muted">(opc.)</span>' : '' ?></label>
                    <input id="<?= esc($idClp) ?>" name="<?= esc($base) ?>_clp" type="text" inputmode="decimal" value="<?= esc($val($base . '_clp', 0)) ?>">
                </div>
                <?php
            };
            ?>
            <p class="muted" style="margin-top:0">
                Datos del crédito hipotecario (recibo de dividendo). La <strong>moneda</strong> define el desarrollo canónico:
                si es UF, saldo/dividendo/amortización van en UF (se calcula $); si es $, van en $ (se calcula UF).
                Pares paralelos UF/$; Ingresos/Egresos analizan siempre en $.
            </p>
            <div class="form-row js-pasivo-moneda" data-moneda-select="moneda" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
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
                        <option value="UF" <?= ($val('moneda') === '' || $val('moneda') === 'UF') ? 'selected' : '' ?>>UF (desarrollo en UF)</option>
                        <option value="CLP" <?= $sel('moneda', 'CLP') ?>>$ / CLP (desarrollo en $)</option>
                    </select>
                </div>
                <?php $moneyPair('saldo', 'Saldo deuda (Capital)', '_h'); ?>
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
                <?php $moneyPair('total_credito', 'Monto otorgado', '_h'); ?>
                <?php $moneyPair('dividendo', 'Total dividendo'); ?>
                <?php $moneyPair('amortizacion', 'Amortización'); ?>
                <?php $moneyPair('interes', 'Interés'); ?>
                <?php $moneyPair('comision', 'Comisión'); ?>
                <?php $moneyPair('seg_incendio', 'Seg. incendio / propiedad'); ?>
                <?php $moneyPair('seg_desgravamen', 'Seg. desgravamen'); ?>
                <?php $moneyPair('seg_otros_complementarios', 'Otros seg. complementarios', '', true); ?>
                <?php $moneyPair('seg_adicionales', 'Seg. adicionales'); ?>
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
        <?php elseif ($clave === 'pasivos-indirecta' || $clave === 'pasivos-lineas'): ?>
            <?php
            $esIndirecta = ($clave === 'pasivos-indirecta');
            $monedaDefaultCupo = ($val('moneda') === '') ? 'CLP' : $val('moneda');
            $moneyPairCupo = static function (
                string $base,
                string $label,
                string $idSuffix = '_cupo'
            ) use ($val): void {
                $idUf  = $base . '_uf' . $idSuffix;
                $idClp = $base . '_clp' . $idSuffix;
                ?>
                <div data-money-side="uf">
                    <label for="<?= esc($idUf) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="UF"><?= esc($label) ?> UF</label>
                    <input id="<?= esc($idUf) ?>" name="<?= esc($base) ?>_uf" type="text" inputmode="decimal" value="<?= esc($val($base . '_uf', 4)) ?>">
                </div>
                <div data-money-side="clp">
                    <label for="<?= esc($idClp) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="CLP"><?= esc($label) ?> $</label>
                    <input id="<?= esc($idClp) ?>" name="<?= esc($base) ?>_clp" type="text" inputmode="decimal" value="<?= esc($val($base . '_clp', 0)) ?>">
                </div>
                <?php
            };
            $tipoLineaSel = '';
            if (! $esIndirecta && $val('tipo') !== '') {
                foreach (EquityCatalogo::opcionesTipoLineaDisponible() as $k => $lab) {
                    if ($lab === $val('tipo') || (string) $k === $val('tipo')) {
                        $tipoLineaSel = (string) $k;
                        break;
                    }
                }
            }
            ?>
            <p class="muted" style="margin-top:0">
                <?php if ($esIndirecta): ?>
                    Deudas adquiridas por un tercero donde eres codeudor, fiador o avalista.
                    Indica el <strong>tomador</strong> (nombre o razón social y RUT).
                <?php else: ?>
                    Créditos disponibles / cupos no utilizados. Misma grilla que deuda indirecta:
                    institución, tipo de línea, fechas, total, vigente y tramos de mora.
                <?php endif; ?>
                La <strong>moneda</strong> define el valor canónico (UF o $) con par paralelo.
            </p>
            <div class="form-row js-pasivo-moneda" data-moneda-select="moneda_cupo" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="banco_descripcion">Institución financiera *</label>
                    <input id="banco_descripcion" name="banco_descripcion" type="text" required placeholder="Banco Consorcio, Banco Bice, BCI…" value="<?= esc($val('banco_descripcion')) ?>">
                </div>
                <?php if ($esIndirecta): ?>
                    <div style="grid-column:1/-1">
                        <label for="tomador_nombre">Nombre o razón social del tomador *</label>
                        <input id="tomador_nombre" name="tomador_nombre" type="text" required placeholder="Persona o empresa titular del crédito" value="<?= esc($val('tomador_nombre')) ?>">
                    </div>
                    <div>
                        <label for="tomador_rut">RUT del tomador *</label>
                        <input id="tomador_rut" name="tomador_rut" type="text" required placeholder="12.345.678-9" value="<?= esc($val('tomador_rut')) ?>">
                    </div>
                    <div>
                        <label for="tipo_credito">Tipo de crédito *</label>
                        <input id="tipo_credito" name="tipo_credito" type="text" required placeholder="Comercial" value="<?= esc($val('tipo')) ?>">
                    </div>
                <?php else: ?>
                    <div>
                        <label for="tipo_linea">Tipo de línea *</label>
                        <select id="tipo_linea" name="tipo_linea" required>
                            <option value="">—</option>
                            <?php foreach (EquityCatalogo::opcionesTipoLineaDisponible() as $k => $lab): ?>
                                <option value="<?= (int) $k ?>" <?= ((string) $k === $tipoLineaSel) ? 'selected' : '' ?>><?= esc($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="moneda_cupo">Moneda</label>
                    <select id="moneda_cupo" name="moneda">
                        <option value="CLP" <?= $monedaDefaultCupo === 'CLP' ? 'selected' : '' ?>>$ / CLP (desarrollo en $)</option>
                        <option value="UF" <?= $monedaDefaultCupo === 'UF' ? 'selected' : '' ?>>UF (desarrollo en UF)</option>
                    </select>
                </div>
                <div>
                    <label for="fecha_otorgamiento_cupo">Fecha de otorgamiento</label>
                    <input id="fecha_otorgamiento_cupo" name="fecha_otorgamiento" type="date" value="<?= esc($val('fecha_otorgamiento')) ?>">
                </div>
                <?php $moneyPairCupo('total_credito', 'Total del crédito'); ?>
                <?php $moneyPairCupo('vigente', 'Vigente'); ?>
                <?php $moneyPairCupo('mora_30_59', '30 a 59 días'); ?>
                <?php $moneyPairCupo('mora_60_89', '60 a 89 días'); ?>
                <?php $moneyPairCupo('mora_90_mas', '90 o más días'); ?>
            </div>
        <?php else: ?>
            <?php
            $esCirculante = ($clave === 'pasivos-circulantes');
            $monedaDefaultOtro = ($val('moneda') === '') ? 'CLP' : $val('moneda');
            $moneyPairOtro = static function (
                string $base,
                string $label,
                string $idSuffix = '_otro'
            ) use ($val): void {
                $idUf  = $base . '_uf' . $idSuffix;
                $idClp = $base . '_clp' . $idSuffix;
                $clpVal = $val($base . '_clp', 0);
                if ($base === 'dividendo' && $clpVal === '' && $val('cuota_mensual') !== '') {
                    $clpVal = $val('cuota_mensual', 0);
                }
                ?>
                <div data-money-side="uf">
                    <label for="<?= esc($idUf) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="UF"><?= esc($label) ?> UF</label>
                    <input id="<?= esc($idUf) ?>" name="<?= esc($base) ?>_uf" type="text" inputmode="decimal" value="<?= esc($val($base . '_uf', 4)) ?>">
                </div>
                <div data-money-side="clp">
                    <label for="<?= esc($idClp) ?>" data-money-label data-base="<?= esc($label) ?>" data-unit="CLP"><?= esc($label) ?> $</label>
                    <input id="<?= esc($idClp) ?>" name="<?= esc($base) ?>_clp" type="text" inputmode="decimal" value="<?= esc($clpVal) ?>">
                </div>
                <?php
            };
            ?>
            <p class="muted" style="margin-top:0">
                Misma estructura que hipotecarios. La <strong>moneda</strong> define el desarrollo canónico (UF o $);
                el otro valor se calcula en paralelo. Seguro de esta sección: <strong>desgravamen</strong>.
                Ingresos/Egresos analizan siempre en $.
            </p>
            <div class="form-row js-pasivo-moneda" data-moneda-select="moneda_otro" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="banco_descripcion">Institución / Banco *</label>
                    <input id="banco_descripcion" name="banco_descripcion" type="text" required placeholder="Banco, casa comercial, financiera…" value="<?= esc($val('banco_descripcion')) ?>">
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
                    <label for="moneda_otro">Moneda del crédito</label>
                    <select id="moneda_otro" name="moneda">
                        <option value="UF" <?= $monedaDefaultOtro === 'UF' ? 'selected' : '' ?>>UF (desarrollo en UF)</option>
                        <option value="CLP" <?= $monedaDefaultOtro === 'CLP' ? 'selected' : '' ?>>$ / CLP (desarrollo en $)</option>
                    </select>
                </div>
                <?php $moneyPairOtro('saldo', 'Saldo deuda (Capital)'); ?>
                <div>
                    <label for="plazo_pactado">Plazo pactado (cuotas)</label>
                    <input id="plazo_pactado" name="plazo_pactado" type="number" min="1" placeholder="240" value="<?= esc($val('plazo_pactado') !== '' ? $val('plazo_pactado') : $val('cuotas_totales')) ?>">
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
                    <label for="fecha_otorgamiento_otro">Fecha de otorgamiento</label>
                    <input id="fecha_otorgamiento_otro" name="fecha_otorgamiento" type="date" value="<?= esc($val('fecha_otorgamiento')) ?>">
                </div>
                <div>
                    <label for="fecha_vencimiento_otro">Fecha de vencimiento</label>
                    <input id="fecha_vencimiento_otro" name="fecha_vencimiento" type="date" value="<?= esc($val('fecha_vencimiento')) ?>">
                </div>
                <?php $moneyPairOtro('total_credito', 'Monto otorgado'); ?>
                <?php $moneyPairOtro('dividendo', 'Total dividendo'); ?>
                <?php $moneyPairOtro('amortizacion', 'Amortización'); ?>
                <?php $moneyPairOtro('interes', 'Interés'); ?>
                <?php $moneyPairOtro('comision', 'Comisión'); ?>
                <?php $moneyPairOtro('seg_desgravamen', 'Seguro desgravamen'); ?>
                <div>
                    <label for="tipo_otro">Tipo</label>
                    <input id="tipo_otro" name="tipo" type="text" placeholder="<?= esc($esCirculante ? 'Consumo / comercial…' : 'Otro pasivo…') ?>" value="<?= esc($val('tipo')) ?>">
                </div>
                <div>
                    <label for="asociacion_otro">Asociación</label>
                    <input id="asociacion_otro" name="asociacion" type="text" placeholder="CASA / INV" value="<?= esc($val('asociacion')) ?>">
                </div>
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
<?php if (in_array($clave, ['pasivos-inmuebles', 'pasivos-circulantes', 'otros-pasivos', 'pasivos-indirecta', 'pasivos-lineas'], true)): ?>
<script>
(function () {
  function syncPasivoMoneda(root) {
    var selId = root.getAttribute('data-moneda-select') || 'moneda';
    var sel = document.getElementById(selId);
    if (!sel) return;
    var moneda = (sel.value || 'UF').toUpperCase();
    var canon = moneda === 'CLP' ? 'clp' : 'uf';
    root.querySelectorAll('[data-money-label]').forEach(function (lab) {
      var base = lab.getAttribute('data-base') || '';
      var unit = lab.getAttribute('data-unit') || '';
      var isCanon = (unit === 'UF' && canon === 'uf') || (unit === 'CLP' && canon === 'clp');
      var unitTxt = unit === 'CLP' ? '$' : 'UF';
      lab.innerHTML = base + ' ' + unitTxt + (isCanon ? '' : ' <span class="muted">(opc.)</span>');
    });
  }
  document.querySelectorAll('.js-pasivo-moneda').forEach(function (root) {
    var selId = root.getAttribute('data-moneda-select') || 'moneda';
    var sel = document.getElementById(selId);
    if (!sel) return;
    sel.addEventListener('change', function () { syncPasivoMoneda(root); });
    syncPasivoMoneda(root);
  });
})();
</script>
<?php endif; ?>
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
                            . (! empty($row['tomador_nombre']) ? ' · Tomador ' . $row['tomador_nombre'] : '')
                            . (! empty($row['tomador_rut']) ? ' · RUT ' . $row['tomador_rut'] : '')
                            . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                            . (! empty($row['fecha_otorgamiento']) ? ' · ' . $row['fecha_otorgamiento'] : '')
                            . (! empty($row['total_credito_clp']) ? ' · Total $' . number_format((float) $row['total_credito_clp'], 0, ',', '.') : '')
                            . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
                            ' ·'
                        ),
                        'pasivos-lineas' => trim(
                            (string) ($row['banco_descripcion'] ?? '')
                            . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                            . (! empty($row['fecha_otorgamiento']) ? ' · ' . $row['fecha_otorgamiento'] : '')
                            . (! empty($row['total_credito_clp']) ? ' · Total $' . number_format((float) $row['total_credito_clp'], 0, ',', '.') : '')
                            . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
                            ' ·'
                        ),
                        'pasivos-circulantes', 'otros-pasivos' => trim(
                            (string) ($row['banco_descripcion'] ?? '')
                            . (! empty($row['numero_credito']) ? ' · Crédito ' . $row['numero_credito'] : '')
                            . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                            . (! empty($row['dividendo_n']) ? ' · Div. ' . $row['dividendo_n'] : '')
                            . (! empty($row['fecha_registro']) ? ' · Reg. ' . $row['fecha_registro'] : '')
                            . (! empty($row['plazo_pactado']) ? ' · Plazo ' . $row['plazo_pactado'] : '')
                            . (! empty($row['cuotas_pagadas']) ? ' · Cancelados ' . $row['cuotas_pagadas'] : '')
                            . (! empty($row['dividendo_uf']) ? ' · Div. ' . number_format((float) $row['dividendo_uf'], 4, ',', '.') . ' UF' : '')
                            . (! empty($row['cuota_mensual']) ? ' · Cuota $' . number_format((float) $row['cuota_mensual'], 0, ',', '.') : '')
                            . (! empty($row['asociacion']) ? ' · ' . $row['asociacion'] : ''),
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
