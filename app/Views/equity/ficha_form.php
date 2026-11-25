<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityAcl;
$isEdit = is_array($ficha ?? null);
$nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
$action = $isEdit
    ? site_url('equity/' . $persona['id'] . '/fichas/' . $ficha['id'])
    : site_url('equity/' . $persona['id'] . '/fichas');
$val = static function (string $key) use ($ficha): string {
    if (! is_array($ficha) || ! isset($ficha[$key]) || $ficha[$key] === null || $ficha[$key] === '') {
        return '';
    }

    return (string) $ficha[$key];
};
?>

<section class="banner">
    <h2><?= $isEdit ? 'Editar ficha financiera' : 'Nueva ficha financiera' ?></h2>
    <p>
        <?= esc($nombre) ?>
        · <?= esc(EquityAcl::labelTipo((string) ($persona['tipo'] ?? 'socio'))) ?>
    </p>
</section>

<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/' . $persona['id']) ?>">← Volver a persona</a>
</div>

<div class="card section-card">
    <form method="post" action="<?= $action ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="fecha_corte">Fecha de corte</label>
                <input id="fecha_corte" type="date" name="fecha_corte" value="<?= esc($val('fecha_corte')) ?>">
            </div>
            <div>
                <label for="moneda">Moneda</label>
                <input id="moneda" type="text" name="moneda" value="<?= esc($val('moneda') !== '' ? $val('moneda') : 'CLP') ?>" maxlength="8">
            </div>
        </div>

        <h3 style="margin-top:1.25rem">1. Renta</h3>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div>
                <label for="ingreso_fijo">Fija</label>
                <input id="ingreso_fijo" type="text" name="ingreso_fijo" inputmode="decimal" value="<?= esc($val('ingreso_fijo')) ?>">
            </div>
            <div>
                <label for="ingreso_variable">Variable</label>
                <input id="ingreso_variable" type="text" name="ingreso_variable" inputmode="decimal" value="<?= esc($val('ingreso_variable')) ?>">
            </div>
            <div>
                <label for="renta_liquida">Renta líquida <span class="muted">(opc.)</span></label>
                <input id="renta_liquida" type="text" name="renta_liquida" inputmode="decimal" value="<?= esc($val('renta_liquida')) ?>">
            </div>
        </div>

        <h3 style="margin-top:1.25rem">2. Honorarios</h3>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div>
                <label for="ingreso_honorarios">Honorarios</label>
                <input id="ingreso_honorarios" type="text" name="ingreso_honorarios" inputmode="decimal" value="<?= esc($val('ingreso_honorarios')) ?>">
            </div>
            <div>
                <label for="ingreso_comisiones">Comisiones</label>
                <input id="ingreso_comisiones" type="text" name="ingreso_comisiones" inputmode="decimal" value="<?= esc($val('ingreso_comisiones')) ?>">
            </div>
            <div>
                <label for="ingreso_bonos">Bonos</label>
                <input id="ingreso_bonos" type="text" name="ingreso_bonos" inputmode="decimal" value="<?= esc($val('ingreso_bonos')) ?>">
            </div>
        </div>

        <h3 style="margin-top:1.25rem">3. Inversiones</h3>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div>
                <label for="ingreso_arriendos">Arriendos</label>
                <input id="ingreso_arriendos" type="text" name="ingreso_arriendos" inputmode="decimal" value="<?= esc($val('ingreso_arriendos')) ?>">
            </div>
            <div>
                <label for="ingreso_mutuos">Mutuos</label>
                <input id="ingreso_mutuos" type="text" name="ingreso_mutuos" inputmode="decimal" value="<?= esc($val('ingreso_mutuos')) ?>">
            </div>
            <div>
                <label for="ingreso_dividendos">Dividendos</label>
                <input id="ingreso_dividendos" type="text" name="ingreso_dividendos" inputmode="decimal" value="<?= esc($val('ingreso_dividendos')) ?>">
            </div>
        </div>

        <h3 style="margin-top:1.25rem">4. Otros</h3>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div>
                <label for="ingreso_pensiones">Pensiones</label>
                <input id="ingreso_pensiones" type="text" name="ingreso_pensiones" inputmode="decimal" value="<?= esc($val('ingreso_pensiones')) ?>">
            </div>
            <div>
                <label for="ingreso_donaciones">Donaciones</label>
                <input id="ingreso_donaciones" type="text" name="ingreso_donaciones" inputmode="decimal" value="<?= esc($val('ingreso_donaciones')) ?>">
            </div>
            <div>
                <label for="ingreso_indemnizaciones">Indemnizaciones</label>
                <input id="ingreso_indemnizaciones" type="text" name="ingreso_indemnizaciones" inputmode="decimal" value="<?= esc($val('ingreso_indemnizaciones')) ?>">
            </div>
            <div>
                <label for="ingreso_capital_er">Capital ER</label>
                <input id="ingreso_capital_er" type="text" name="ingreso_capital_er" inputmode="decimal" value="<?= esc($val('ingreso_capital_er')) ?>">
            </div>
            <div>
                <label for="ingreso_intereses_er">Intereses ER</label>
                <input id="ingreso_intereses_er" type="text" name="ingreso_intereses_er" inputmode="decimal" value="<?= esc($val('ingreso_intereses_er')) ?>">
            </div>
            <div>
                <label for="ingreso_otros">Otros <span class="muted">(legacy)</span></label>
                <input id="ingreso_otros" type="text" name="ingreso_otros" inputmode="decimal" value="<?= esc($val('ingreso_otros')) ?>">
            </div>
            <div>
                <label for="ingreso_mensual_total">Total ingreso mensual</label>
                <input id="ingreso_mensual_total" type="text" name="ingreso_mensual_total" inputmode="decimal" value="<?= esc($val('ingreso_mensual_total')) ?>" placeholder="Se calcula si se deja vacío">
            </div>
        </div>

        <h3 style="margin-top:1.25rem">Patrimonio</h3>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div>
                <label for="activos_total">Activos</label>
                <input id="activos_total" type="text" name="activos_total" inputmode="decimal" value="<?= esc($val('activos_total')) ?>">
            </div>
            <div>
                <label for="pasivos_total">Pasivos</label>
                <input id="pasivos_total" type="text" name="pasivos_total" inputmode="decimal" value="<?= esc($val('pasivos_total')) ?>">
            </div>
            <div>
                <label for="patrimonio_neto">Patrimonio neto</label>
                <input id="patrimonio_neto" type="text" name="patrimonio_neto" inputmode="decimal" value="<?= esc($val('patrimonio_neto')) ?>" placeholder="Activos − pasivos si se deja vacío">
            </div>
        </div>

        <label for="notas" style="margin-top:1rem">Notas</label>
        <textarea id="notas" name="notas" rows="3"><?= esc($val('notas')) ?></textarea>

        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar ficha' : 'Registrar ficha' ?></button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
