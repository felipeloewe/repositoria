<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
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
$action = match ($clave) {
    'bienes-raices'       => site_url('equity/activos/bienes-raices'),
    'vehiculos'           => site_url('equity/activos/vehiculos'),
    'sociedades'          => site_url('equity/activos/sociedades'),
    'otros-activos'       => site_url('equity/activos/otros'),
    'pasivos-inmuebles'   => site_url('equity/pasivos/inmuebles'),
    'pasivos-circulantes' => site_url('equity/pasivos/circulantes'),
    'otros-pasivos'       => site_url('equity/pasivos/otros'),
    default               => site_url('dashboard'),
};
?>

<section class="banner">
    <h2><?= esc($meta['label']) ?></h2>
    <p>
        <?= esc($meta['grupo']) ?> del estado de situación patrimonial.
        Montos en <strong>UF</strong> (fijos); $ con UF vigente
        <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?><?= $ufVigente ? ' · cierre ' . esc($ufVigente['fecha_cierre']) : '' ?>.
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <form method="get" action="<?= esc($action) ?>" class="form-row" style="grid-template-columns:1fr auto">
        <div>
            <label for="f_persona">Persona</label>
            <select id="f_persona" name="persona_id">
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

<?php if (! empty($canManage) && $personas !== []): ?>
<div class="card section-card">
    <h3>Agregar</h3>
    <form method="post" action="<?= esc($action) ?>">
        <?= csrf_field() ?>
        <label for="persona_id">Persona *</label>
        <select id="persona_id" name="persona_id" required>
            <?php foreach ($personas as $p): ?>
                <option value="<?= esc($p['id']) ?>" <?= $personaId === $p['id'] ? 'selected' : '' ?>>
                    <?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($clave === 'bienes-raices'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="tipo_inmueble">Tipo *</label>
                    <select id="tipo_inmueble" name="tipo_inmueble" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column:1/-1">
                    <label for="direccion">Dirección *</label>
                    <input id="direccion" name="direccion" type="text" required placeholder="calle, número, comuna, otros datos">
                </div>
                <div>
                    <label for="rol">Rol</label>
                    <input id="rol" name="rol" type="text">
                </div>
                <div>
                    <label for="garantia">Garantía</label>
                    <select id="garantia" name="garantia">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesGarantiaInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="asegurado">Está asegurado</label>
                    <select id="asegurado" name="asegurado">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="valor_uf">Valor comercial UF *</label>
                    <input id="valor_uf" name="valor_uf" type="text" inputmode="decimal" placeholder="UF fijas">
                </div>
                <div>
                    <label for="valor_clp">Valor comercial $ <span class="muted">(opcional)</span></label>
                    <input id="valor_clp" name="valor_clp" type="text" inputmode="decimal" placeholder="si no ingresas UF">
                </div>
            </div>
        <?php elseif ($clave === 'vehiculos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div>
                    <label for="tipo_vehiculo">Tipo *</label>
                    <select id="tipo_vehiculo" name="tipo_vehiculo" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoVehiculo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="marca">Marca</label><input id="marca" name="marca" type="text"></div>
                <div><label for="modelo">Modelo</label><input id="modelo" name="modelo" type="text"></div>
                <div><label for="anio">Año</label><input id="anio" name="anio" type="number"></div>
                <div><label for="patente">Patente</label><input id="patente" name="patente" type="text"></div>
                <div>
                    <label for="garantia_v">Garantía</label>
                    <select id="garantia_v" name="garantia">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesGarantiaInmueble() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="asegurado_v">Está asegurado</label>
                    <select id="asegurado_v" name="asegurado">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label for="valor_uf_v">Valor comercial UF *</label><input id="valor_uf_v" name="valor_uf" type="text" inputmode="decimal"></div>
                <div><label for="valor_clp_v">Valor comercial $ <span class="muted">(opc.)</span></label><input id="valor_clp_v" name="valor_clp" type="text" inputmode="decimal"></div>
            </div>
        <?php elseif ($clave === 'sociedades'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="nombre_sociedad">Razón social *</label>
                    <input id="nombre_sociedad" name="nombre_sociedad" type="text" required>
                </div>
                <div>
                    <label for="rut_sociedad">RUT sociedad</label>
                    <input id="rut_sociedad" name="rut_sociedad" type="text">
                </div>
                <div>
                    <label for="giro_rubro">Giro / Rubro</label>
                    <input id="giro_rubro" name="giro_rubro" type="text">
                </div>
                <div>
                    <label for="porcentaje">% participación</label>
                    <input id="porcentaje" name="porcentaje" type="text" inputmode="decimal">
                </div>
                <div>
                    <label for="valor_uf_s">Valor comercial UF *</label>
                    <input id="valor_uf_s" name="valor_uf" type="text" inputmode="decimal">
                </div>
                <div>
                    <label for="valor_clp_s">Valor comercial $ <span class="muted">(opc.)</span></label>
                    <input id="valor_clp_s" name="valor_clp" type="text" inputmode="decimal">
                </div>
                <div>
                    <label for="emp_relacionada">Emp. relacionada</label>
                    <select id="emp_relacionada" name="emp_relacionada">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSiNo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label style="display:flex;gap:.5rem;align-items:center;margin:.75rem 0">
                <input type="checkbox" name="cumple_articulo_84" value="1"> Cumple artículo N° 84
            </label>
        <?php elseif ($clave === 'otros-activos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="tipo_activo">Tipo *</label>
                    <select id="tipo_activo" name="tipo_activo" required>
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoOtroActivo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>"><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="institucion">Institución</label>
                    <input id="institucion" name="institucion" type="text">
                </div>
                <div>
                    <label for="saldo_uf_oa">Saldo UF *</label>
                    <input id="saldo_uf_oa" name="saldo_uf" type="text" inputmode="decimal">
                </div>
                <div>
                    <label for="saldo_clp">Saldo $ <span class="muted">(opc.)</span></label>
                    <input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal">
                </div>
            </div>
        <?php else: ?>
            <label for="banco_descripcion">Banco / descripción *</label>
            <input id="banco_descripcion" name="banco_descripcion" type="text" required>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div><label for="saldo_uf">Saldo UF *</label><input id="saldo_uf" name="saldo_uf" type="text" inputmode="decimal"></div>
                <div><label for="saldo_clp">Saldo $ <span class="muted">(opc.)</span></label><input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal"></div>
                <div><label for="cuotas_totales">Cuotas totales</label><input id="cuotas_totales" name="cuotas_totales" type="number"></div>
                <div><label for="cuotas_pagadas">Cuotas pagadas</label><input id="cuotas_pagadas" name="cuotas_pagadas" type="number"></div>
                <div><label for="cuota_mensual">Cuota mensual</label><input id="cuota_mensual" name="cuota_mensual" type="text" inputmode="decimal"></div>
                <div><label for="asociacion">Asociación</label><input id="asociacion" name="asociacion" type="text" placeholder="CASA / INV"></div>
                <div><label for="tipo">Tipo</label><input id="tipo" name="tipo" type="text"></div>
            </div>
        <?php endif; ?>

        <label for="notas">Notas</label>
        <textarea id="notas" name="notas" rows="2"></textarea>
        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
    </form>
</div>
<?php elseif (empty($personas)): ?>
    <div class="alert alert-info">Primero <a href="<?= site_url('equity/nueva') ?>">agrega un integrante</a> al grupo familiar.</div>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right"><strong>Total</strong></td>
                    <td><strong><?= esc(EquityUf::fmtUf($totalUf, 2)) ?></strong></td>
                    <td><strong><?= esc(EquityUf::fmtPesos($totalClp, 0)) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
