<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityAcl;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;

$nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '') . ' ' . ($persona['apellido_materno'] ?? ''));
$tipo = (string) ($persona['tipo'] ?? 'socio');
$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $clp = (float) $v;
    $uf  = $ufRate > 0 ? round($clp / $ufRate, 2) : null;

    return number_format($clp, 0, ',', '.') . ($uf !== null ? ' · ' . number_format($uf, 2, ',', '.') . ' UF' : '');
};
$mesAnio = static function ($mes, $anio): string {
    if (($mes === null || $mes === '') && ($anio === null || $anio === '')) {
        return '—';
    }

    return trim(($mes !== null && $mes !== '' ? (string) (int) $mes : '—') . '/' . ($anio !== null && $anio !== '' ? (string) (int) $anio : '—'));
};
$row = static function (string $label, string $value): void {
    echo '<div><label>' . esc($label) . '</label><div>' . $value . '</div></div>';
};
?>

<style>
@media print {
  .sidebar, .topbar, .report-actions, .nav-exit, .no-print { display:none !important; }
  .shell { display:block !important; }
  .main { margin:0 !important; width:100% !important; }
  .content { padding:0 !important; }
  .card, .section-card, .banner { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }
  a { text-decoration:none; color:inherit; }
}
</style>

<section class="banner">
    <h2><?= esc($nombre) ?></h2>
    <p>
        <?= esc(EquityAcl::labelTipo($tipo)) ?>
        <?php if (! empty($persona['cargo'])): ?>
            · <?= esc($persona['cargo']) ?>
        <?php endif; ?>
        <?php if (! empty($persona['rut'])): ?>
            · RUT <?= esc($persona['rut']) ?>
        <?php endif; ?>
        <?php if ((int) ($persona['activo'] ?? 1) !== 1): ?>
            · <strong>Inactivo</strong>
        <?php endif; ?>
    </p>
    <p class="report-actions no-print" style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">Reporte</button>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Balance</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity') ?>">← Volver a Grupo Familiar</a>
    <?php if (! empty($canManage)): ?>
        <a href="<?= site_url('equity/' . $persona['id'] . '/editar') ?>">Editar personales</a>
        <?php if ($tipo === EquityAcl::TIPO_SOCIO): ?>
            <a href="<?= site_url('equity/' . $persona['id'] . '/editar?vista=laboral') ?>">Editar laborales</a>
        <?php endif; ?>
    <?php endif; ?>
    <a href="<?= site_url('equity/' . $persona['id'] . '/bancarios') ?>">Antecedentes bancarios</a>
</div>

<?php if ($tipo === EquityAcl::TIPO_SOCIO || $tipo === EquityAcl::TIPO_GERENTE): ?>
<?php if ($tipo === EquityAcl::TIPO_SOCIO): ?>
<div class="card section-card">
    <h3>Antecedentes Personales</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <?php
            $row('Fecha nacimiento', esc($persona['fecha_nacimiento'] ?? '—'));
            $row('Sexo', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesSexo(), $persona['sexo'] ?? null)));
            $row('Nacionalidad', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesNacionalidad(), $persona['nacionalidad'] ?? null)));
            $row('Nacionalidad detalle', esc($persona['nacionalidad_detalle'] ?? '—'));
            $row('N° integrantes', esc((string) ($persona['grupo_familiar_n'] ?? '—')));
            $row('Estado civil', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesEstadoCivil(), $persona['estado_civil'] ?? null)));
            $row('Régimen', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesRegimenPatrimonial(), $persona['regimen_patrimonial'] ?? null)));
            $row('Lugar habita', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesLugarHabita(), $persona['lugar_habita'] ?? null)));
            $row('Antigüedad domicilio', esc($mesAnio($persona['antiguedad_domicilio_meses'] ?? null, $persona['antiguedad_domicilio_anos'] ?? null)));
            $row('Nivel estudios', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesNivelEstudios(), $persona['nivel_estudios'] ?? null)));
            $row('Profesión', esc($persona['profesion'] ?? '—'));
            $row('Universidad / Instituto', esc($persona['universidad_instituto'] ?? '—'));
            $row('Dirección', esc(trim(($persona['dir_calle'] ?? '') . ' ' . ($persona['dir_numero'] ?? '') . ' ' . ($persona['dir_otros'] ?? '')) ?: '—'));
            $row('Comuna / Ciudad / Región', esc(trim(($persona['dir_comuna'] ?? '') . ' · ' . ($persona['dir_ciudad'] ?? '') . ' · ' . ($persona['dir_region'] ?? ''), ' ·') ?: '—'));
            $row('Tel. particular', esc($persona['telefono_particular'] ?? '—'));
            $row('Celular', esc($persona['telefono_celular'] ?? '—'));
            $row('E-mail', esc($persona['email'] ?? '—'));
        ?>
    </div>
    <p class="no-print" style="margin-top:1rem">
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $persona['id'] . '/bancarios') ?>">Antecedentes bancarios</a>
    </p>
</div>
<?php endif; ?>
<div class="card section-card">
    <h3>Antecedentes Laborales</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <?php
            $row('Tipo actividad', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoActividad(), $persona['tipo_actividad'] ?? null)));
            $row('Tipo contrato', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoContrato(), $persona['tipo_contrato'] ?? null)));
            $row('Tipo renta', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoRenta(), $persona['tipo_renta'] ?? null)));
            $row('Fecha ingreso', esc($mesAnio($persona['fecha_ingreso_mes'] ?? null, $persona['fecha_ingreso_anio'] ?? null)));
            $row('Cargo / actividad', esc($persona['cargo'] ?? '—'));
            $row('Antigüedad cargo', esc($mesAnio($persona['antiguedad_cargo_meses'] ?? null, $persona['antiguedad_cargo_anos'] ?? null)));
            $row('RUT empleador', esc($persona['rut_empleador'] ?? '—'));
            $row('Nombre empleador', esc($persona['nombre_empleador'] ?? '—'));
            $row('Giro / rubro', esc($persona['giro_empleador'] ?? '—'));
            $row('Renta líquida $', esc($fmt($persona['renta_liquida_persona'] ?? null)));
            $row('Dir. comercial', esc(trim(($persona['dir_com_calle'] ?? '') . ' ' . ($persona['dir_com_numero'] ?? '') . ' ' . ($persona['dir_com_otros'] ?? '')) ?: '—'));
            $row('Comuna / Ciudad / Región', esc(trim(($persona['dir_com_comuna'] ?? '') . ' · ' . ($persona['dir_com_ciudad'] ?? '') . ' · ' . ($persona['dir_com_region'] ?? ''), ' ·') ?: '—'));
            $row('Tel. comercial', esc(trim(($persona['tel_comercial'] ?? '') . (($persona['tel_anexo'] ?? '') !== '' && ($persona['tel_anexo'] ?? '') !== null ? ' anexo ' . $persona['tel_anexo'] : '')) ?: '—'));
            $row('2° act. remunerada', esc($persona['segunda_actividad'] ?? '—'));
            $row('Inicio 2° act.', esc($mesAnio($persona['segunda_actividad_mes'] ?? null, $persona['segunda_actividad_anio'] ?? null)));
            $row('Renta líquida 2° $', esc($fmt($persona['renta_liquida_2'] ?? null)));
            $row('E-mail', esc($persona['email'] ?? '—'));
        ?>
    </div>
</div>
<div class="card section-card">
    <h3>Empleo Anterior</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <?php
            $row('Cargo o actividad', esc($persona['empleo_ant_cargo'] ?? '—'));
            $row('Nombre empleador', esc($persona['empleo_ant_empleador'] ?? '—'));
            $row('Fecha ingreso', esc($mesAnio($persona['empleo_ant_ingreso_mes'] ?? null, $persona['empleo_ant_ingreso_anio'] ?? null)));
            $row('Fecha salida', esc($mesAnio($persona['empleo_ant_salida_mes'] ?? null, $persona['empleo_ant_salida_anio'] ?? null)));
            $row('Renta líquida $', esc($fmt($persona['empleo_ant_renta'] ?? null)));
        ?>
    </div>
</div>
<?php else: ?>
<div class="card section-card">
    <h3>Datos Cónyuge o Conviviente Civil</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <?php
            $row('Fecha nacimiento', esc($persona['fecha_nacimiento'] ?? '—'));
            $row('Tipo actividad', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoActividad(), $persona['tipo_actividad'] ?? null)));
            $row('Cargo / actividad', esc($persona['cargo'] ?? '—'));
            $row('Fecha ingreso', esc($mesAnio($persona['fecha_ingreso_mes'] ?? null, $persona['fecha_ingreso_anio'] ?? null)));
            $row('Complementa renta', esc(EquityCatalogo::labelOpcion(EquityCatalogo::opcionesComplementaRenta(), $persona['complementa_renta'] ?? null)));
            $row('RUT empleador', esc($persona['rut_empleador'] ?? '—'));
            $row('Nombre empleador', esc($persona['nombre_empleador'] ?? '—'));
            $row('Teléfono comercial', esc($persona['tel_comercial'] ?? '—'));
            $row('Renta líquida $', esc($fmt($persona['renta_liquida_persona'] ?? null)));
        ?>
    </div>
</div>
<?php endif; ?>

<?php if (! empty($persona['notas'])): ?>
<div class="card section-card">
    <h3>Notas</h3>
    <p class="muted"><?= esc($persona['notas']) ?></p>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Fichas financieras (<?= count($fichas) ?>)</h3>
    <?php if (! empty($canManage)): ?>
        <p style="margin-top:0">
            <a class="btn btn-primary btn-sm" href="<?= site_url('equity/' . $persona['id'] . '/fichas/nueva') ?>">Nueva ficha</a>
        </p>
    <?php endif; ?>
    <?php if ($fichas === []): ?>
        <p class="muted">Sin fichas registradas.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Corte</th>
                <th>Ingreso mensual</th>
                <th>Renta líquida</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fichas as $f): ?>
            <tr>
                <td><?= esc($f['fecha_corte'] ?? '—') ?></td>
                <td><?= esc($fmt($f['ingreso_mensual_total'] ?? null)) ?></td>
                <td><?= esc($fmt($f['renta_liquida'] ?? null)) ?></td>
                <td>
                    <?php if (! empty($canManage)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $persona['id'] . '/fichas/' . $f['id'] . '/editar') ?>">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (! empty($canUpload)): ?>
<div class="card section-card">
    <h3>Subir respaldo</h3>
    <form method="post" action="<?= site_url('equity/' . $persona['id'] . '/respaldos') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="up_titulo">Título</label>
        <input id="up_titulo" type="text" name="titulo" required>
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="up_tipo_doc">Tipo de documento</label>
                <select id="up_tipo_doc" name="tipo_id" required>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= esc($t['id']) ?>"><?= esc($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_tipo_res">Tipo de respaldo</label>
                <select id="up_tipo_res" name="tipo_respaldo">
                    <option value="">—</option>
                    <?php foreach ($tiposRespaldo as $tr): ?>
                        <option value="<?= esc($tr) ?>"><?= esc($tr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_ficha">Vincular a ficha (opcional)</label>
                <select id="up_ficha" name="ficha_id">
                    <option value="">Ninguna</option>
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= esc($f['id']) ?>">
                            <?= esc($f['fecha_corte'] ?? $f['created_at'] ?? $f['id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_desc">Descripción</label>
                <input id="up_desc" type="text" name="descripcion" placeholder="Opcional">
            </div>
        </div>
        <label for="up_file">Archivo (máx. 15 MB)</label>
        <input id="up_file" type="file" name="archivo" required style="margin-bottom:1rem">
        <button class="btn btn-primary" type="submit">Subir respaldo</button>
    </form>
</div>
<?php elseif (empty($tipos)): ?>
<div class="alert alert-info">Necesitas al menos un <a href="<?= site_url('documentos/tipos') ?>">tipo de documento</a> activo para subir respaldos.</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Respaldos (<?= count($respaldos) ?>)</h3>
    <?php if ($respaldos === []): ?>
        <p class="muted">Sin respaldos cargados.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Documento</th>
                <th>Tipo respaldo</th>
                <th>Fecha</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($respaldos as $r): ?>
            <?php $doc = $r['documento'] ?? null; ?>
            <tr>
                <td>
                    <?php if ($doc): ?>
                        <a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a><br>
                        <span class="muted" style="font-size:.8rem"><?= esc($doc['nombre_archivo'] ?? '') ?></span>
                    <?php else: ?>
                        <span class="muted">Documento no disponible</span>
                    <?php endif; ?>
                    <?php if (! empty($r['descripcion'])): ?>
                        <br><span class="muted"><?= esc($r['descripcion']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= esc($r['tipo_respaldo'] ?? '—') ?></td>
                <td class="muted"><?= esc($r['created_at'] ?? '') ?></td>
                <td style="white-space:nowrap">
                    <?php if ($doc): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id']) ?>">Abrir</a>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
                    <?php endif; ?>
                    <?php if (! empty($r['can_delete']) && $doc): ?>
                        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este respaldo?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
