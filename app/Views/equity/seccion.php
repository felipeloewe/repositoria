<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
$clave = $meta['clave'];
$fmt = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 0, ',', '.');
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
    <p><?= esc($meta['grupo']) ?> del estado de situación patrimonial.</p>
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
            <label for="descripcion">Descripción *</label>
            <input id="descripcion" name="descripcion" type="text" required>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div><label for="valor_clp">Valor CLP</label><input id="valor_clp" name="valor_clp" type="text" inputmode="decimal"></div>
                <div><label for="valor_uf">Valor UF</label><input id="valor_uf" name="valor_uf" type="text" inputmode="decimal"></div>
                <div><label for="fecha_tasacion">Fecha tasación</label><input id="fecha_tasacion" name="fecha_tasacion" type="date"></div>
                <div><label for="rol">Rol</label><input id="rol" name="rol" type="text"></div>
                <div><label for="avaluo_fiscal">Avalúo fiscal</label><input id="avaluo_fiscal" name="avaluo_fiscal" type="text" inputmode="decimal"></div>
                <div><label for="contribuciones">Contribuciones</label><input id="contribuciones" name="contribuciones" type="text" placeholder="afecto / exento"></div>
            </div>
        <?php elseif ($clave === 'vehiculos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div><label for="tipo">Tipo</label><input id="tipo" name="tipo" type="text" placeholder="Automóvil, camioneta…"></div>
                <div><label for="marca">Marca</label><input id="marca" name="marca" type="text"></div>
                <div><label for="modelo">Modelo</label><input id="modelo" name="modelo" type="text"></div>
                <div><label for="anio">Año</label><input id="anio" name="anio" type="number"></div>
                <div><label for="patente">Patente</label><input id="patente" name="patente" type="text"></div>
                <div><label for="valor_clp">Valor CLP</label><input id="valor_clp" name="valor_clp" type="text" inputmode="decimal"></div>
            </div>
            <label style="display:flex;gap:.5rem;align-items:center;margin:.75rem 0">
                <input type="checkbox" name="asegurado" value="1"> Asegurado
            </label>
        <?php elseif ($clave === 'sociedades'): ?>
            <label for="nombre_sociedad">Nombre sociedad *</label>
            <input id="nombre_sociedad" name="nombre_sociedad" type="text" required>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div>
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="Directo">Directo</option>
                        <option value="Indirecto">Indirecto</option>
                    </select>
                </div>
                <div><label for="porcentaje">% participación</label><input id="porcentaje" name="porcentaje" type="text" inputmode="decimal"></div>
                <div><label for="valor_clp">Valor CLP</label><input id="valor_clp" name="valor_clp" type="text" inputmode="decimal"></div>
                <div><label for="valor_uf">Valor UF</label><input id="valor_uf" name="valor_uf" type="text" inputmode="decimal"></div>
                <div><label for="pais_detalle">País / detalle</label><input id="pais_detalle" name="pais_detalle" type="text"></div>
            </div>
        <?php elseif ($clave === 'otros-activos'): ?>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div><label for="tipo">Tipo</label><input id="tipo" name="tipo" type="text" placeholder="Depósito, acciones…"></div>
                <div><label for="institucion">Institución</label><input id="institucion" name="institucion" type="text"></div>
                <div><label for="saldo_clp">Saldo CLP</label><input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal"></div>
            </div>
        <?php else: ?>
            <label for="banco_descripcion">Banco / descripción *</label>
            <input id="banco_descripcion" name="banco_descripcion" type="text" required>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                <div><label for="saldo_clp">Saldo CLP</label><input id="saldo_clp" name="saldo_clp" type="text" inputmode="decimal"></div>
                <div><label for="saldo_uf">Saldo UF</label><input id="saldo_uf" name="saldo_uf" type="text" inputmode="decimal"></div>
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
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Detalle</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $persona = $row['persona'] ?? null;
                    $nombreP = $persona ? trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')) : '—';
                    $detalle = match ($clave) {
                        'bienes-raices' => (string) ($row['descripcion'] ?? ''),
                        'vehiculos' => trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '') . ' ' . ($row['patente'] ?? '')),
                        'sociedades' => (string) ($row['nombre_sociedad'] ?? ''),
                        'otros-activos' => trim(($row['tipo'] ?? '') . ' · ' . ($row['institucion'] ?? ''), ' ·'),
                        default => (string) ($row['banco_descripcion'] ?? ''),
                    };
                    $valor = match ($clave) {
                        'bienes-raices', 'vehiculos', 'sociedades' => $row['valor_clp'] ?? null,
                        'otros-activos' => $row['saldo_clp'] ?? null,
                        default => $row['saldo_clp'] ?? null,
                    };
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
                    <td><?= esc($fmt($valor)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
