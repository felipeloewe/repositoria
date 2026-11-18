<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityAcl;
$isEdit = is_array($persona ?? null);
$action = $isEdit ? site_url('equity/' . $persona['id']) : site_url('equity');
?>

<section class="banner">
    <h2><?= $isEdit ? 'Editar integrante' : 'Nuevo integrante' ?></h2>
    <p>Miembro del grupo familiar (antecedentes personales o laborales) con ficha financiera y respaldos.</p>
</section>

<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= $isEdit ? site_url('equity/' . $persona['id']) : site_url('equity') ?>">← Volver</a>
</div>

<div class="card section-card">
    <form method="post" action="<?= $action ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= esc($t) ?>" <?= ($persona['tipo'] ?? 'socio') === $t ? 'selected' : '' ?>>
                            <?= esc(EquityAcl::labelTipo($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="cargo">Cargo</label>
                <input id="cargo" type="text" name="cargo" value="<?= esc($persona['cargo'] ?? '') ?>" placeholder="Ej. Gerente General">
            </div>
            <div>
                <label for="nombre">Nombre *</label>
                <input id="nombre" type="text" name="nombre" required value="<?= esc($persona['nombre'] ?? '') ?>">
            </div>
            <div>
                <label for="apellido">Apellido</label>
                <input id="apellido" type="text" name="apellido" value="<?= esc($persona['apellido'] ?? '') ?>">
            </div>
            <div>
                <label for="rut">RUT</label>
                <input id="rut" type="text" name="rut" value="<?= esc($persona['rut'] ?? '') ?>" placeholder="12.345.678-9">
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= esc($persona['email'] ?? '') ?>">
            </div>
        </div>
        <?php if ($isEdit): ?>
            <label style="display:flex;align-items:center;gap:.5rem;margin:1rem 0">
                <input type="checkbox" name="activo" value="1" <?= (int) ($persona['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                Activo
            </label>
        <?php endif; ?>
        <label for="notas">Notas</label>
        <textarea id="notas" name="notas" rows="3"><?= esc($persona['notas'] ?? '') ?></textarea>
        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar' : 'Crear' ?></button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
