<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityAcl;
use App\Libraries\EquityCatalogo;

$isEdit = is_array($persona ?? null) && ! empty($persona['id']);
$action = $isEdit ? site_url('equity/' . $persona['id']) : site_url('equity');
$p = $persona ?? [];
$tipoActual = $p['tipo'] ?? 'socio';
$vistaLaboral = ! empty($vistaLaboral) || $tipoActual === 'gerente';
$buscarRutUrl = $buscarRutUrl ?? site_url('equity/buscar-rut');
$personaIdReal = $isEdit ? (string) ($persona['id'] ?? '') : '';
// Si venimos de personales a laborales, el id real sigue siendo el de socio
if ($vistaLaboral && $isEdit && ($persona['tipo'] ?? '') === 'gerente') {
    // tipo forzado solo en vista; id sigue siendo válido
}
$val = static function (string $key, $default = '') use ($p) {
    $v = $p[$key] ?? $default;

    return $v === null ? '' : $v;
};
$sel = static function (string $key, $opt) use ($p): string {
    $v = $p[$key] ?? null;
    if ($v === null || $v === '') {
        return '';
    }

    return (string) $v === (string) $opt ? 'selected' : '';
};
?>

<section class="banner">
    <h2><?php
        if ($vistaLaboral) {
            echo $isEdit ? 'Editar antecedentes laborales' : 'Antecedentes laborales';
        } else {
            echo $isEdit ? 'Editar integrante' : 'Nuevo integrante';
        }
    ?></h2>
    <p><?= $vistaLaboral
        ? 'Busca por RUT la ficha de Antecedentes Personales; nombres, apellidos y RUT se toman de ahí.'
        : 'Antecedentes personales o laborales del grupo familiar.' ?></p>
</section>

<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= $isEdit ? site_url('equity/' . $persona['id']) : site_url('equity') ?>">← Volver</a>
</div>

<div class="card section-card">
    <form method="post" action="<?= $action ?>" id="form-antecedentes">
        <?= csrf_field() ?>

        <input type="hidden" name="vista_laboral" id="vista_laboral" value="<?= $vistaLaboral ? '1' : '0' ?>">
        <input type="hidden" name="persona_id_laboral" id="persona_id_laboral" value="<?= esc($personaIdReal) ?>">
        <?php if ($vistaLaboral): ?>
            <input type="hidden" name="tipo" id="tipo" value="gerente">
            <input type="hidden" name="rut" id="rut" value="<?= esc($val('rut')) ?>">
            <input type="hidden" name="nombre" id="nombre" value="<?= esc($val('nombre')) ?>">
            <input type="hidden" name="apellido" id="apellido" value="<?= esc($val('apellido')) ?>">
            <input type="hidden" name="apellido_materno" id="apellido_materno" value="<?= esc($val('apellido_materno')) ?>">
            <input type="hidden" name="email" id="email" value="<?= esc($val('email')) ?>">
        <?php else: ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="tipo">Tipo *</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= esc($t) ?>" <?= $tipoActual === $t ? 'selected' : '' ?>>
                            <?= esc(EquityAcl::labelTipo($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="rut">RUT</label>
                <input id="rut" type="text" name="rut" value="<?= esc($val('rut')) ?>" placeholder="12.345.678-9">
            </div>
        </div>
        <?php endif; ?>

        <div id="bloque-personales" data-tipo="socio" style="<?= (! $vistaLaboral && $tipoActual === 'socio') ? '' : 'display:none' ?>">
            <?php if (! $vistaLaboral): ?>
            <h3 style="margin:1.25rem 0 .75rem">Antecedentes Personales</h3>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <div style="grid-column:1/-1">
                    <label for="nombre">Nombres *</label>
                    <input id="nombre" type="text" name="nombre" required value="<?= esc($val('nombre')) ?>">
                </div>
                <div>
                    <label for="apellido">Apellido Paterno</label>
                    <input id="apellido" type="text" name="apellido" value="<?= esc($val('apellido')) ?>">
                </div>
                <div>
                    <label for="apellido_materno">Apellido Materno</label>
                    <input id="apellido_materno" type="text" name="apellido_materno" value="<?= esc($val('apellido_materno')) ?>">
                </div>
                <div>
                    <label for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" value="<?= esc($val('fecha_nacimiento')) ?>">
                </div>
                <div>
                    <label for="sexo">Sexo</label>
                    <select id="sexo" name="sexo">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesSexo() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('sexo', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nacionalidad">Nacionalidad</label>
                    <select id="nacionalidad" name="nacionalidad">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesNacionalidad() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('nacionalidad', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="nacionalidad_detalle">Nacionalidad (si es extranjero)</label>
                    <input id="nacionalidad_detalle" type="text" name="nacionalidad_detalle" value="<?= esc($val('nacionalidad_detalle')) ?>">
                </div>
                <div>
                    <label for="grupo_familiar_n">Grupo familiar · N° integrantes</label>
                    <input id="grupo_familiar_n" type="number" min="0" name="grupo_familiar_n" value="<?= esc($val('grupo_familiar_n')) ?>">
                </div>
                <div>
                    <label for="estado_civil">Estado civil</label>
                    <select id="estado_civil" name="estado_civil">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesEstadoCivil() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('estado_civil', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="regimen_patrimonial">Régimen patrimonial</label>
                    <select id="regimen_patrimonial" name="regimen_patrimonial">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesRegimenPatrimonial() as $k => $lab): ?>
                            <option value="<?= esc($k) ?>" <?= $sel('regimen_patrimonial', $k) ?>><?= esc($k) ?> · <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="lugar_habita">Lugar donde habita</label>
                    <select id="lugar_habita" name="lugar_habita">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesLugarHabita() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('lugar_habita', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="antiguedad_domicilio_anos">Antigüedad domicilio · Años</label>
                    <input id="antiguedad_domicilio_anos" type="number" min="0" name="antiguedad_domicilio_anos" value="<?= esc($val('antiguedad_domicilio_anos')) ?>">
                </div>
                <div>
                    <label for="antiguedad_domicilio_meses">Antigüedad domicilio · Meses</label>
                    <input id="antiguedad_domicilio_meses" type="number" min="0" max="11" name="antiguedad_domicilio_meses" value="<?= esc($val('antiguedad_domicilio_meses')) ?>">
                </div>
                <div>
                    <label for="nivel_estudios">Nivel de estudios</label>
                    <select id="nivel_estudios" name="nivel_estudios">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesNivelEstudios() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('nivel_estudios', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="profesion">Profesión</label>
                    <input id="profesion" type="text" name="profesion" value="<?= esc($val('profesion')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="universidad_instituto">Nombre Universidad o Instituto Profesional</label>
                    <input id="universidad_instituto" type="text" name="universidad_instituto" value="<?= esc($val('universidad_instituto')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="dir_calle">Dirección particular (calle)</label>
                    <input id="dir_calle" type="text" name="dir_calle" value="<?= esc($val('dir_calle')) ?>">
                </div>
                <div>
                    <label for="dir_numero">Número</label>
                    <input id="dir_numero" type="text" name="dir_numero" value="<?= esc($val('dir_numero')) ?>">
                </div>
                <div>
                    <label for="dir_otros">Otros datos dirección</label>
                    <input id="dir_otros" type="text" name="dir_otros" value="<?= esc($val('dir_otros')) ?>">
                </div>
                <div>
                    <label for="dir_comuna">Comuna</label>
                    <input id="dir_comuna" type="text" name="dir_comuna" value="<?= esc($val('dir_comuna')) ?>">
                </div>
                <div>
                    <label for="dir_ciudad">Ciudad</label>
                    <input id="dir_ciudad" type="text" name="dir_ciudad" value="<?= esc($val('dir_ciudad')) ?>">
                </div>
                <div>
                    <label for="dir_region">Región</label>
                    <input id="dir_region" type="text" name="dir_region" value="<?= esc($val('dir_region')) ?>">
                </div>
                <div>
                    <label for="telefono_particular">Teléfono particular</label>
                    <input id="telefono_particular" type="text" name="telefono_particular" value="<?= esc($val('telefono_particular')) ?>">
                </div>
                <div>
                    <label for="telefono_celular">Teléfono celular</label>
                    <input id="telefono_celular" type="text" name="telefono_celular" value="<?= esc($val('telefono_celular')) ?>">
                </div>
                <div>
                    <label for="email">E-mail</label>
                    <input id="email" type="email" name="email" value="<?= esc($val('email')) ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div id="bloque-laborales" data-tipo="gerente" style="<?= ($vistaLaboral || $tipoActual === 'gerente') ? '' : 'display:none' ?>">
            <h3 style="margin:1.25rem 0 .75rem">Antecedentes Laborales</h3>
            <p class="muted">Recupera la persona por RUT desde Antecedentes Personales. Nombres, apellidos y RUT deben coincidir.</p>
            <div class="form-row" style="grid-template-columns:1fr auto;align-items:end;margin-bottom:.75rem">
                <div>
                    <label for="rut_laboral">RUT (Antecedentes Personales) *</label>
                    <input id="rut_laboral" type="text" value="<?= esc($val('rut')) ?>" placeholder="12.345.678-9" <?= $isEdit ? 'readonly' : '' ?>>
                </div>
                <?php if (! $isEdit): ?>
                <div>
                    <button type="button" class="btn btn-primary" id="btn-buscar-rut">Buscar</button>
                </div>
                <?php endif; ?>
            </div>
            <p id="rut-lookup-msg" class="muted" style="margin-top:0"></p>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <div>
                    <label for="nombre_laboral">Nombres</label>
                    <input id="nombre_laboral" type="text" name="nombre_laboral" value="<?= esc($val('nombre')) ?>" readonly>
                </div>
                <div>
                    <label for="apellido_laboral">Apellido Paterno</label>
                    <input id="apellido_laboral" type="text" name="apellido_laboral" value="<?= esc($val('apellido')) ?>" readonly>
                </div>
                <div>
                    <label for="apellido_materno_laboral">Apellido Materno</label>
                    <input id="apellido_materno_laboral" type="text" name="apellido_materno_laboral" value="<?= esc($val('apellido_materno')) ?>" readonly>
                </div>
                <div>
                    <label for="tipo_actividad">Tipo de actividad</label>
                    <select id="tipo_actividad" name="tipo_actividad">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoActividad() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_actividad', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tipo_contrato">Tipo contrato</label>
                    <select id="tipo_contrato" name="tipo_contrato">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoContrato() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_contrato', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tipo_renta">Tipo de renta</label>
                    <select id="tipo_renta" name="tipo_renta">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoRenta() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_renta', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fecha_ingreso_mes">Fecha ingreso · Mes</label>
                    <input id="fecha_ingreso_mes" type="number" min="1" max="12" name="fecha_ingreso_mes" value="<?= esc($val('fecha_ingreso_mes')) ?>">
                </div>
                <div>
                    <label for="fecha_ingreso_anio">Fecha ingreso · Año</label>
                    <input id="fecha_ingreso_anio" type="number" min="1950" max="2100" name="fecha_ingreso_anio" value="<?= esc($val('fecha_ingreso_anio')) ?>">
                </div>
                <div>
                    <label for="cargo">Cargo o actividad</label>
                    <input id="cargo" type="text" name="cargo" value="<?= esc($val('cargo')) ?>">
                </div>
                <div>
                    <label for="antiguedad_cargo_anos">Antigüedad cargo · Años</label>
                    <input id="antiguedad_cargo_anos" type="number" min="0" name="antiguedad_cargo_anos" value="<?= esc($val('antiguedad_cargo_anos')) ?>">
                </div>
                <div>
                    <label for="antiguedad_cargo_meses">Antigüedad cargo · Meses</label>
                    <input id="antiguedad_cargo_meses" type="number" min="0" max="11" name="antiguedad_cargo_meses" value="<?= esc($val('antiguedad_cargo_meses')) ?>">
                </div>
                <div>
                    <label for="rut_empleador">RUT empleador</label>
                    <input id="rut_empleador" type="text" name="rut_empleador" value="<?= esc($val('rut_empleador')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="nombre_empleador">Nombre empleador</label>
                    <input id="nombre_empleador" type="text" name="nombre_empleador" value="<?= esc($val('nombre_empleador')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="giro_empleador">Giro o rubro empleador</label>
                    <input id="giro_empleador" type="text" name="giro_empleador" value="<?= esc($val('giro_empleador')) ?>">
                </div>
                <div>
                    <label for="renta_liquida_persona">Renta líquida $</label>
                    <input id="renta_liquida_persona" type="text" name="renta_liquida_persona" inputmode="decimal" value="<?= esc($val('renta_liquida_persona')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="dir_com_calle">Dirección comercial (calle)</label>
                    <input id="dir_com_calle" type="text" name="dir_com_calle" value="<?= esc($val('dir_com_calle')) ?>">
                </div>
                <div>
                    <label for="dir_com_numero">Número</label>
                    <input id="dir_com_numero" type="text" name="dir_com_numero" value="<?= esc($val('dir_com_numero')) ?>">
                </div>
                <div>
                    <label for="dir_com_otros">Otros datos dirección</label>
                    <input id="dir_com_otros" type="text" name="dir_com_otros" value="<?= esc($val('dir_com_otros')) ?>">
                </div>
                <div>
                    <label for="dir_com_comuna">Comuna</label>
                    <input id="dir_com_comuna" type="text" name="dir_com_comuna" value="<?= esc($val('dir_com_comuna')) ?>">
                </div>
                <div>
                    <label for="dir_com_ciudad">Ciudad</label>
                    <input id="dir_com_ciudad" type="text" name="dir_com_ciudad" value="<?= esc($val('dir_com_ciudad')) ?>">
                </div>
                <div>
                    <label for="dir_com_region">Región</label>
                    <input id="dir_com_region" type="text" name="dir_com_region" value="<?= esc($val('dir_com_region')) ?>">
                </div>
                <div>
                    <label for="tel_comercial">Tel. comercial</label>
                    <input id="tel_comercial" type="text" name="tel_comercial" value="<?= esc($val('tel_comercial')) ?>">
                </div>
                <div>
                    <label for="tel_anexo">Anexo</label>
                    <input id="tel_anexo" type="text" name="tel_anexo" value="<?= esc($val('tel_anexo')) ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="segunda_actividad">2° act. remunerada</label>
                    <input id="segunda_actividad" type="text" name="segunda_actividad" value="<?= esc($val('segunda_actividad')) ?>">
                </div>
                <div>
                    <label for="segunda_actividad_mes">Fecha inicio 2° · Mes</label>
                    <input id="segunda_actividad_mes" type="number" min="1" max="12" name="segunda_actividad_mes" value="<?= esc($val('segunda_actividad_mes')) ?>">
                </div>
                <div>
                    <label for="segunda_actividad_anio">Fecha inicio 2° · Año</label>
                    <input id="segunda_actividad_anio" type="number" min="1950" max="2100" name="segunda_actividad_anio" value="<?= esc($val('segunda_actividad_anio')) ?>">
                </div>
                <div>
                    <label for="renta_liquida_2">Renta líquida 2° act. $</label>
                    <input id="renta_liquida_2" type="text" name="renta_liquida_2" inputmode="decimal" value="<?= esc($val('renta_liquida_2')) ?>">
                </div>
                <div>
                    <label for="email_lab">E-mail</label>
                    <input id="email_lab" type="email" name="email_lab" value="<?= esc($val('email')) ?>">
                </div>
            </div>

            <h3 style="margin:1.5rem 0 .75rem">Empleo Anterior</h3>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="empleo_ant_cargo">Cargo o actividad</label>
                    <input id="empleo_ant_cargo" type="text" name="empleo_ant_cargo" value="<?= esc($val('empleo_ant_cargo')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_empleador">Nombre empleador</label>
                    <input id="empleo_ant_empleador" type="text" name="empleo_ant_empleador" value="<?= esc($val('empleo_ant_empleador')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_ingreso_mes">Fecha ingreso · Mes</label>
                    <input id="empleo_ant_ingreso_mes" type="number" min="1" max="12" name="empleo_ant_ingreso_mes" value="<?= esc($val('empleo_ant_ingreso_mes')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_ingreso_anio">Fecha ingreso · Año</label>
                    <input id="empleo_ant_ingreso_anio" type="number" min="1950" max="2100" name="empleo_ant_ingreso_anio" value="<?= esc($val('empleo_ant_ingreso_anio')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_salida_mes">Fecha salida · Mes</label>
                    <input id="empleo_ant_salida_mes" type="number" min="1" max="12" name="empleo_ant_salida_mes" value="<?= esc($val('empleo_ant_salida_mes')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_salida_anio">Fecha salida · Año</label>
                    <input id="empleo_ant_salida_anio" type="number" min="1950" max="2100" name="empleo_ant_salida_anio" value="<?= esc($val('empleo_ant_salida_anio')) ?>">
                </div>
                <div>
                    <label for="empleo_ant_renta">Renta líquida $</label>
                    <input id="empleo_ant_renta" type="text" name="empleo_ant_renta" inputmode="decimal" value="<?= esc($val('empleo_ant_renta')) ?>">
                </div>
            </div>
        </div>

        <div id="bloque-familiar" data-tipo="familiar" style="<?= $tipoActual === 'familiar' ? '' : 'display:none' ?>">
            <h3 style="margin:1.25rem 0 .35rem">Antecedentes Grupo Familiar</h3>
            <p class="muted" style="margin-top:0">Datos cónyuge o conviviente civil. Sólo completar si tercero se obliga, de forma directa o indirecta, a cumplir con alguna de las obligaciones del contrato.</p>
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
                <div>
                    <label for="nombre_fam">Nombres *</label>
                    <input id="nombre_fam" type="text" name="nombre_fam" value="<?= esc($val('nombre')) ?>">
                </div>
                <div>
                    <label for="apellido_fam">Apellido Paterno</label>
                    <input id="apellido_fam" type="text" name="apellido_fam" value="<?= esc($val('apellido')) ?>">
                </div>
                <div>
                    <label for="apellido_materno_fam">Apellido Materno</label>
                    <input id="apellido_materno_fam" type="text" name="apellido_materno_fam" value="<?= esc($val('apellido_materno')) ?>">
                </div>
                <div>
                    <label for="rut_fam">RUT</label>
                    <input id="rut_fam" type="text" name="rut_fam" value="<?= esc($val('rut')) ?>">
                </div>
                <div>
                    <label for="fecha_nacimiento_fam">Fecha de nacimiento</label>
                    <input id="fecha_nacimiento_fam" type="date" name="fecha_nacimiento_fam" value="<?= esc($val('fecha_nacimiento')) ?>">
                </div>
                <div>
                    <label for="tipo_actividad_fam">Tipo de actividad</label>
                    <select id="tipo_actividad_fam" name="tipo_actividad_fam">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesTipoActividad() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('tipo_actividad', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="cargo_fam">Cargo / Actividad</label>
                    <input id="cargo_fam" type="text" name="cargo_fam" value="<?= esc($val('cargo')) ?>">
                </div>
                <div>
                    <label for="fecha_ingreso_mes_fam">Fecha de ingreso · Mes</label>
                    <input id="fecha_ingreso_mes_fam" type="number" min="1" max="12" name="fecha_ingreso_mes_fam" value="<?= esc($val('fecha_ingreso_mes')) ?>">
                </div>
                <div>
                    <label for="fecha_ingreso_anio_fam">Fecha de ingreso · Año</label>
                    <input id="fecha_ingreso_anio_fam" type="number" min="1950" max="2100" name="fecha_ingreso_anio_fam" value="<?= esc($val('fecha_ingreso_anio')) ?>">
                </div>
                <div>
                    <label for="complementa_renta">Complementa renta</label>
                    <select id="complementa_renta" name="complementa_renta">
                        <option value="">—</option>
                        <?php foreach (EquityCatalogo::opcionesComplementaRenta() as $k => $lab): ?>
                            <option value="<?= (int) $k ?>" <?= $sel('complementa_renta', $k) ?>><?= (int) $k ?>. <?= esc($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="rut_empleador_fam">RUT empleador</label>
                    <input id="rut_empleador_fam" type="text" name="rut_empleador_fam" value="<?= esc($val('rut_empleador')) ?>">
                </div>
                <div>
                    <label for="nombre_empleador_fam">Nombre empleador</label>
                    <input id="nombre_empleador_fam" type="text" name="nombre_empleador_fam" value="<?= esc($val('nombre_empleador')) ?>">
                </div>
                <div>
                    <label for="tel_comercial_fam">Teléfono comercial</label>
                    <input id="tel_comercial_fam" type="text" name="tel_comercial_fam" value="<?= esc($val('tel_comercial')) ?>">
                </div>
                <div>
                    <label for="renta_liquida_fam">Renta líquida $</label>
                    <input id="renta_liquida_fam" type="text" name="renta_liquida_fam" inputmode="decimal" value="<?= esc($val('renta_liquida_persona')) ?>">
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>

            <label style="display:flex;align-items:center;gap:.5rem;margin:1rem 0">
                <input type="checkbox" name="activo" value="1" <?= (int) ($p['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                Activo
            </label>
        <?php endif; ?>
        <label for="notas">Notas</label>
        <textarea id="notas" name="notas" rows="3"><?= esc($val('notas')) ?></textarea>
        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar' : 'Crear' ?></button>
        </div>
    </form>
</div>

<script>
(function () {
  var tipo = document.getElementById('tipo');
  var per = document.getElementById('bloque-personales');
  var lab = document.getElementById('bloque-laborales');
  var fam = document.getElementById('bloque-familiar');
  var nombre = document.getElementById('nombre');
  var nombreLab = document.getElementById('nombre_laboral');
  var nombreFam = document.getElementById('nombre_fam');
  var apellido = document.getElementById('apellido');
  var apellidoLab = document.getElementById('apellido_laboral');
  var apellidoMatLab = document.getElementById('apellido_materno_laboral');
  var apellidoFam = document.getElementById('apellido_fam');
  var apellidoMat = document.getElementById('apellido_materno');
  var apellidoMatFam = document.getElementById('apellido_materno_fam');
  var email = document.getElementById('email');
  var emailLab = document.getElementById('email_lab');
  var rut = document.getElementById('rut');
  var rutLab = document.getElementById('rut_laboral');
  var rutFam = document.getElementById('rut_fam');
  var personaIdLab = document.getElementById('persona_id_laboral');
  var vistaLab = document.getElementById('vista_laboral');
  var msg = document.getElementById('rut-lookup-msg');
  var buscarUrl = <?= json_encode($buscarRutUrl, JSON_UNESCAPED_SLASHES) ?>;

  function setDisabled(block, disabled) {
    if (!block) return;
    block.querySelectorAll('input,select,textarea').forEach(function (el) {
      // Identidad laboral siempre editable=false (readonly) pero no disabled para que viaje en POST si hace falta
      if (el.readOnly) return;
      el.disabled = disabled;
    });
  }
  function sync() {
    var t = tipo.value;
    if (vistaLab && vistaLab.value === '1') t = 'gerente';
    per.style.display = t === 'socio' ? '' : 'none';
    lab.style.display = t === 'gerente' ? '' : 'none';
    fam.style.display = t === 'familiar' ? '' : 'none';
    setDisabled(per, t !== 'socio');
    setDisabled(lab, t !== 'gerente');
    setDisabled(fam, t !== 'familiar');
    if (nombre) nombre.required = t === 'socio';
    if (nombreFam) nombreFam.required = t === 'familiar';
    if (vistaLab) vistaLab.value = t === 'gerente' ? '1' : '0';
  }

  function setVal(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.value = value == null ? '' : String(value);
  }

  function applyPersona(p) {
    if (personaIdLab) personaIdLab.value = p.id || '';
    if (rut) rut.value = p.rut || '';
    if (rutLab) rutLab.value = p.rut || '';
    if (nombre) nombre.value = p.nombre || '';
    if (nombreLab) nombreLab.value = p.nombre || '';
    if (apellido) apellido.value = p.apellido || '';
    if (apellidoLab) apellidoLab.value = p.apellido || '';
    if (apellidoMat) apellidoMat.value = p.apellido_materno || '';
    if (apellidoMatLab) apellidoMatLab.value = p.apellido_materno || '';
    if (email) email.value = p.email || '';
    if (emailLab) emailLab.value = p.email || '';
    [
      'tipo_actividad','tipo_contrato','tipo_renta','fecha_ingreso_mes','fecha_ingreso_anio','cargo',
      'antiguedad_cargo_anos','antiguedad_cargo_meses','rut_empleador','nombre_empleador','giro_empleador',
      'renta_liquida_persona','dir_com_calle','dir_com_numero','dir_com_otros','dir_com_comuna','dir_com_ciudad',
      'dir_com_region','tel_comercial','tel_anexo','segunda_actividad','segunda_actividad_mes',
      'segunda_actividad_anio','renta_liquida_2','empleo_ant_cargo','empleo_ant_empleador',
      'empleo_ant_ingreso_mes','empleo_ant_ingreso_anio','empleo_ant_salida_mes','empleo_ant_salida_anio','empleo_ant_renta'
    ].forEach(function (k) {
      if (p[k] != null && p[k] !== '') setVal(k, p[k]);
    });
  }

  function buscarRut() {
    var q = (rutLab && rutLab.value) ? rutLab.value.trim() : '';
    if (!q) {
      if (msg) msg.textContent = 'Ingresa un RUT.';
      return;
    }
    if (msg) msg.textContent = 'Buscando…';
    fetch(buscarUrl + '?rut=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          if (msg) msg.textContent = data.error || 'No encontrado';
          if (personaIdLab) personaIdLab.value = '';
          return;
        }
        applyPersona(data.persona || {});
        if (msg) msg.textContent = 'Persona recuperada desde Antecedentes Personales.';
      })
      .catch(function () {
        if (msg) msg.textContent = 'Error al buscar el RUT.';
      });
  }

  var btn = document.getElementById('btn-buscar-rut');
  if (btn) btn.addEventListener('click', buscarRut);
  if (rutLab && !rutLab.readOnly) {
    rutLab.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); buscarRut(); }
    });
  }

  if (tipo && !tipo.disabled) tipo.addEventListener('change', sync);
  sync();

  document.getElementById('form-antecedentes').addEventListener('submit', function (e) {
    var t = tipo.value;
    if (vistaLab && vistaLab.value === '1') t = 'gerente';
    if (nombre) { nombre.disabled = false; nombre.required = true; }
    if (apellido) apellido.disabled = false;
    if (apellidoMat) apellidoMat.disabled = false;
    if (rut) rut.disabled = false;
    if (email) email.disabled = false;

    if (t === 'gerente') {
      if (!personaIdLab || !personaIdLab.value) {
        if (msg) msg.textContent = 'Debes buscar un RUT de Antecedentes Personales.';
        e.preventDefault();
        return;
      }
      if (nombreLab && nombreLab.value && nombre) nombre.value = nombreLab.value;
      if (apellidoLab && apellidoLab.value && apellido) apellido.value = apellidoLab.value;
      if (apellidoMatLab && apellidoMat && apellidoMatLab.value) apellidoMat.value = apellidoMatLab.value;
      if (rutLab && rutLab.value && rut) rut.value = rutLab.value;
      if (emailLab && email && emailLab.value) email.value = emailLab.value;
      // habilitar campos laborales disabled
      setDisabled(lab, false);
      // identidad readonly se mantiene
    }
    if (t === 'familiar') {
      if (nombreFam && nombreFam.value && nombre) nombre.value = nombreFam.value;
      if (apellidoFam && apellidoFam.value && apellido) apellido.value = apellidoFam.value;
      if (apellidoMatFam && apellidoMatFam.value && apellidoMat) apellidoMat.value = apellidoMatFam.value;
      if (rutFam && rutFam.value && rut) rut.value = rutFam.value;
      var copies = {
        fecha_nacimiento_fam: 'fecha_nacimiento',
        tipo_actividad_fam: 'tipo_actividad',
        cargo_fam: 'cargo',
        fecha_ingreso_mes_fam: 'fecha_ingreso_mes',
        fecha_ingreso_anio_fam: 'fecha_ingreso_anio',
        rut_empleador_fam: 'rut_empleador',
        nombre_empleador_fam: 'nombre_empleador',
        tel_comercial_fam: 'tel_comercial',
        renta_liquida_fam: 'renta_liquida_persona'
      };
      Object.keys(copies).forEach(function (srcId) {
        var src = document.getElementById(srcId);
        var dstName = copies[srcId];
        var dst = document.getElementsByName(dstName)[0];
        if (!dst) {
          dst = document.createElement('input');
          dst.type = 'hidden';
          dst.name = dstName;
          document.getElementById('form-antecedentes').appendChild(dst);
        }
        dst.disabled = false;
        if (src) dst.value = src.value;
      });
      var comp = document.getElementById('complementa_renta');
      if (comp) comp.disabled = false;
    }
  });
})();
</script>


<?= $this->endSection() ?>
