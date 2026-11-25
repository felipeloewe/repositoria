<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v) use ($ufRate): string {
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
$isEdit = is_array($editRow ?? null);
$e = static function (string $key, $default = '') use ($editRow, $fmtInput, $isEdit) {
    if (! $isEdit) {
        return (string) $default;
    }
    $v = $editRow[$key] ?? null;
    if ($v === null || $v === '') {
        return (string) $default;
    }
    if (in_array($key, ['monto_contrato_clp'], true)) {
        return $fmtInput($v);
    }

    return (string) $v;
};
$qs = '';
$periodos = EquityCatalogo::opcionesPeriodicidadIngreso();
$estados  = EquityCatalogo::opcionesEstadoContratoIngreso();
$reclasificable = ! empty($reclasificable);
$subtipoLabels = [];
foreach ($meta['campos'] as $c) {
    $subtipoLabels[$c['subtipo']] = $c['label'];
}
if ($reclasificable) {
    foreach (EquityCatalogo::subtiposIngresoReclasificables() as $st => $c) {
        $subtipoLabels[$st] = $c['label'];
    }
}
$tiposPorFuente = [];
if ($reclasificable) {
    foreach (EquityCatalogo::fuentesIngresoReclasificables() as $fClave) {
        $fMeta = EquityCatalogo::fuentesIngreso()[$fClave] ?? null;
        if ($fMeta === null) {
            continue;
        }
        $tiposPorFuente[$fClave] = $fMeta;
    }
}
$formAction = $isEdit
    ? site_url('equity/ingresos/' . $fuente . '/contratos/' . $editRow['id'])
    : site_url('equity/ingresos/' . $fuente . '/contratos');
$personaLabel = '';
foreach ($personas as $p) {
    if (($p['id'] ?? '') === $personaId) {
        $personaLabel = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        break;
    }
}
?>

<section class="banner">
    <h2>Ingresos · <?= esc($meta['label']) ?></h2>
    <p><?= esc($meta['descripcion']) ?>. Registra contraparte, contrato, periodicidad y monto<?= $personaLabel !== '' ? ' · ' . esc($personaLabel) : '' ?>.</p>
    <?php if ($fuente === 'inversiones'): ?>
        <p class="muted" style="margin-top:.6rem;margin-bottom:0;font-size:.9rem">
            Flujo: en el onboarding se carga el <strong>bien raíz</strong> (arrendatario / cánon) y luego se
            <strong>actualizan los contratos</strong> con esa información. La gestión diaria queda en el
            <strong>contrato</strong> (vigente → vencido → nuevo).
        </p>
    <?php endif; ?>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/reporte/ingresos') ?>">Reporte</a>
        <?php if ($fuente === 'inversiones' && ! empty($canManage) && $personaId !== ''): ?>
            <form method="post" action="<?= site_url('equity/ingresos/inversiones/sync-arriendos') ?>" style="display:inline" onsubmit="return confirm('¿Actualizar contratos de arriendo con arrendatario/cánon de los bienes raíces activos? Los vigentes se alinean; se crean los que falten.');">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit">Actualizar contratos desde bienes raíces</button>
            </form>
        <?php endif; ?>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/ingresos') ?>">← Resumen ingresos</a>
    <?php foreach (EquityCatalogo::fuentesIngreso() as $k => $m): ?>
        <a href="<?= site_url('equity/ingresos/' . $k) ?>" class="<?= $k === $fuente ? 'active' : '' ?>"><?= esc($m['label']) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($personaId === ''): ?>
    <div class="card section-card">
        <p class="muted">No hay persona activa en la sesión. La matriz debe inscribirte o, si representas a alguien, elige persona al ingresar.</p>
    </div>
<?php elseif (! empty($canManage)): ?>
    <div class="card section-card">
        <h3><?= $isEdit ? 'Editar contraparte / contrato' : 'Nueva contraparte / contrato' ?></h3>
        <?php if ($isEdit): ?>
            <p class="muted" style="margin-top:0">
                <a href="<?= site_url('equity/ingresos/' . $fuente . $qs) ?>">Cancelar edición</a>
            </p>
        <?php endif; ?>
        <form method="post" action="<?= $formAction ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="persona_id" value="<?= esc($personaId) ?>">

            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <div>
                    <label for="subtipo">Tipo *</label>
                    <select id="subtipo" name="subtipo" required>
                        <?php if ($reclasificable): ?>
                            <?php foreach ($tiposPorFuente as $fClave => $fMeta): ?>
                                <optgroup label="<?= esc($fMeta['label']) ?>">
                                    <?php foreach ($fMeta['campos'] as $c): ?>
                                        <option value="<?= esc($c['subtipo']) ?>" <?= $e('subtipo') === $c['subtipo'] ? 'selected' : '' ?>>
                                            <?= esc($c['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($meta['campos'] as $c): ?>
                                <option value="<?= esc($c['subtipo']) ?>" <?= $e('subtipo') === $c['subtipo'] ? 'selected' : '' ?>>
                                    <?= esc($c['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if ($reclasificable): ?>
                        <p class="muted" style="margin:.25rem 0 0;font-size:.85em">
                            Puedes cambiar el tipo entre <strong>Inversiones</strong> y <strong>Otros</strong>; el contrato se mueve a esa pestaña al guardar.
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="contraparte_rut">Contraparte · RUT</label>
                    <input id="contraparte_rut" name="contraparte_rut" type="text" placeholder="12.345.678-9" value="<?= esc($e('contraparte_rut')) ?>">
                </div>
                <div style="grid-column: span 2">
                    <label for="contraparte_nombre">Contraparte · Nombre completo / Razón social *</label>
                    <input id="contraparte_nombre" name="contraparte_nombre" type="text" required placeholder="Persona o empresa" value="<?= esc($e('contraparte_nombre')) ?>">
                </div>
                <div>
                    <label for="fecha_inicio_contrato">Fecha inicio contrato</label>
                    <input id="fecha_inicio_contrato" name="fecha_inicio_contrato" type="date" value="<?= esc($e('fecha_inicio_contrato')) ?>">
                </div>
                <div>
                    <label for="fecha_vencimiento">Fecha de vencimiento</label>
                    <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="<?= esc($e('fecha_vencimiento')) ?>">
                    <p class="muted" style="margin:.25rem 0 0;font-size:.85em">Para contratos vencidos (cualquier tipo): indica cuándo terminó.</p>
                </div>
                <div>
                    <label for="periodicidad">Periodicidad *</label>
                    <select id="periodicidad" name="periodicidad" required>
                        <?php foreach ($periodos as $k => $lab): ?>
                            <option value="<?= esc($k) ?>" <?= ($e('periodicidad', 'mensual') === $k) ? 'selected' : '' ?>><?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="monto_contrato_clp">Monto del contrato $ *</label>
                    <input id="monto_contrato_clp" name="monto_contrato_clp" type="text" inputmode="decimal" required placeholder="Según periodicidad" value="<?= esc($e('monto_contrato_clp')) ?>">
                </div>
                <div>
                    <label for="estado">Estado *</label>
                    <select id="estado" name="estado" required>
                        <?php foreach ($estados as $k => $lab): ?>
                            <option value="<?= esc($k) ?>" <?= ($e('estado', 'vigente') === $k) ? 'selected' : '' ?>><?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (($fuente === 'inversiones' || $reclasificable) && ($bienesOpciones ?? []) !== []): ?>
                    <div style="grid-column:1/-1">
                        <label for="bien_raiz_id">Bien raíz vinculado <span class="muted">(arriendos)</span></label>
                        <select id="bien_raiz_id" name="bien_raiz_id">
                            <option value="">— Sin vínculo —</option>
                            <?php foreach ($bienesOpciones as $bo): ?>
                                <?php
                                    $boId = (string) ($bo['id'] ?? '');
                                    $boLab = trim((string) (($bo['direccion'] ?? '') !== '' ? $bo['direccion'] : ($bo['descripcion'] ?? $boId)));
                                    if (! empty($bo['arrendatario'])) {
                                        $boLab .= ' · ' . $bo['arrendatario'];
                                    }
                                ?>
                                <option value="<?= esc($boId) ?>" <?= $e('bien_raiz_id') === $boId ? 'selected' : '' ?>><?= esc($boLab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div style="grid-column:1/-1">
                    <label for="contrato_archivo">Adjuntar contrato <?= $isEdit && ! empty($editRow['contrato_ruta_relativa']) ? '<span class="muted">(opcional · reemplaza el actual)</span>' : '' ?></label>
                    <input id="contrato_archivo" name="contrato_archivo" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip">
                    <?php if ($isEdit && ! empty($editRow['contrato_ruta_relativa'])): ?>
                        <div class="muted" style="margin-top:.35rem">
                            Actual:
                            <a href="<?= site_url('equity/ingresos/' . $fuente . '/contratos/' . $editRow['id'] . '/archivo') ?>">
                                <?= esc($editRow['contrato_archivo_nombre'] ?? 'Ver archivo') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="grid-column:1/-1">
                    <label for="notas">Notas</label>
                    <textarea id="notas" name="notas" rows="2"><?= esc($e('notas')) ?></textarea>
                </div>
            </div>
            <p class="muted" style="margin:.75rem 0 0">
                El monto se interpreta según la periodicidad (mensual = mes; anual = año). Los vigentes se consolidan a equivalente mensual en el resumen.
            </p>
            <div style="margin-top:1rem">
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Agregar contrato' ?></button>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="card section-card">
    <h3>Contratos / contrapartes (<?= count($contratos ?? []) ?>)</h3>
    <?php if (($contratos ?? []) === []): ?>
        <p class="muted">Sin contratos registrados en esta fuente<?= $personaId !== '' ? ' para la persona seleccionada' : '' ?>.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Contraparte</th>
                        <?php if ($fuente === 'inversiones'): ?><th>Bien raíz</th><?php endif; ?>
                        <th>RUT</th>
                        <th>Inicio</th>
                        <th>Vencimiento</th>
                        <th>Periodicidad</th>
                        <th>Monto contrato</th>
                        <th>Equiv. mensual</th>
                        <th>Estado</th>
                        <th>Contrato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($contratos as $c): ?>
                    <tr>
                        <td><?= esc($subtipoLabels[$c['subtipo'] ?? ''] ?? ($c['subtipo'] ?? '—')) ?></td>
                        <td><?= esc($c['contraparte_nombre'] ?? '—') ?></td>
                        <?php if ($fuente === 'inversiones'): ?>
                            <td>
                                <?php
                                    $bien = $c['bien'] ?? null;
                                    $bienLab = is_array($bien)
                                        ? trim((string) (($bien['direccion'] ?? '') !== '' ? $bien['direccion'] : ($bien['descripcion'] ?? '')))
                                        : '';
                                ?>
                                <?php if ($bienLab !== '' && is_array($bien)): ?>
                                    <a href="<?= site_url('equity/activos/bienes-raices/' . $bien['id'] . '/editar') ?>"><?= esc($bienLab) ?></a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><?= esc($c['contraparte_rut'] ?? '—') ?></td>
                        <td><?= esc($c['fecha_inicio_contrato'] ?? '—') ?></td>
                        <td><?= esc($c['fecha_vencimiento'] ?? '—') ?></td>
                        <td><?= esc($periodos[$c['periodicidad'] ?? ''] ?? ($c['periodicidad'] ?? '—')) ?></td>
                        <td><?= esc($fmt($c['monto_contrato_clp'] ?? null)) ?></td>
                        <td><strong><?= esc($fmt($c['monto_mensual'] ?? null)) ?></strong></td>
                        <td>
                            <?php $est = $c['estado'] ?? 'vigente'; ?>
                            <span class="<?= $est === 'vigente' ? '' : 'muted' ?>"><?= esc($estados[$est] ?? $est) ?></span>
                        </td>
                        <td>
                            <?php if (! empty($c['contrato_ruta_relativa'])): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos/' . $fuente . '/contratos/' . $c['id'] . '/archivo') ?>">Ver</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap">
                            <?php if (! empty($canManage) && $personaId !== ''): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos/' . $fuente . '?persona_id=' . rawurlencode($personaId) . '&edit=' . rawurlencode($c['id'])) ?>">Editar</a>
                                <form method="post" action="<?= site_url('equity/ingresos/' . $fuente . '/contratos/' . $c['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este contrato?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($fuente === 'inversiones' && ($arriendos ?? []) !== []): ?>
<div class="card section-card">
    <h3>Onboarding · Bienes con cánon (<?= count($arriendos) ?>)</h3>
    <p class="muted" style="margin-top:0">
        Datos del bien usados para alimentar contratos. Si el vínculo falta o el monto difiere, usa
        <strong>Actualizar contratos desde bienes raíces</strong>. Después gestiona el ciclo en la tabla de contratos.
    </p>
    <table class="table">
        <thead>
            <tr>
                <th>Dirección</th>
                <th>Arrendatario</th>
                <th>Pago</th>
                <th>Cánon (bien)</th>
                <th>Contrato</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($arriendos as $ar): ?>
            <?php
                $ct = $ar['contrato_vigente'] ?? null;
                $ctEstado = is_array($ct) ? (string) ($ct['estado'] ?? '') : '';
                $ctMonto  = is_array($ct) && isset($ct['monto_contrato_clp']) ? (float) $ct['monto_contrato_clp'] : null;
                $canonBien = (float) ($ar['canon_show'] ?? 0);
                $ok = is_array($ct) && $ctEstado === 'vigente' && $ctMonto !== null && abs($ctMonto - $canonBien) < 1;
            ?>
            <tr>
                <td><?= esc($ar['direccion'] ?? $ar['descripcion'] ?? '—') ?></td>
                <td><?= esc($ar['arrendatario'] ?? '—') ?></td>
                <td><?= esc($ar['medio_pago'] ?? '—') ?></td>
                <td><strong><?= esc($fmt($ar['canon_show'] ?? null)) ?></strong></td>
                <td>
                    <?php if ($ok): ?>
                        <span>Alineado · vigente</span>
                    <?php elseif (is_array($ct) && $ctEstado === 'vigente'): ?>
                        <span class="muted">Vigente · monto distinto (<?= esc($fmt($ctMonto)) ?>)</span>
                    <?php elseif (is_array($ct)): ?>
                        <span class="muted"><?= esc($estados[$ctEstado] ?? $ctEstado) ?></span>
                    <?php else: ?>
                        <span class="muted">Sin contrato vinculado</span>
                    <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                    <?php if (! empty($canManage)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/bienes-raices/' . $ar['id'] . '/editar') ?>">Editar bien</a>
                        <?php if (is_array($ct) && ! empty($ct['id'])): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos/inversiones?persona_id=' . rawurlencode($personaId) . '&edit=' . rawurlencode($ct['id'])) ?>">Editar contrato</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right"><strong>Total cánones (bienes)</strong></td>
                <td><strong><?= esc($fmt($totalCanon ?? 0)) ?></strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
