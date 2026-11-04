<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Actividades</h2>
    <p>Tags de <strong>actividad</strong> de la empresa. Puedes crearlos sin departamento y relacionarlos después, o vincularlos ahora a un departamento activo.</p>
</section>

<?= view('departamentos/_tabs', ['tab' => 'actividades']) ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Nueva actividad</h3>
    <form method="post" action="<?= site_url('departamentos/actividades') ?>" class="form-row" style="grid-template-columns:1.2fr 1fr auto;align-items:end">
        <?= csrf_field() ?>
        <div>
            <label for="act_nombre">Nombre</label>
            <input id="act_nombre" type="text" name="nombre" required placeholder="p.ej. Capacitación interna">
        </div>
        <div>
            <label for="act_depto">Departamento (opcional)</label>
            <select id="act_depto" name="departamento_id">
                <option value="">Sin relacionar</option>
                <?php foreach ($deptos as $d): ?>
                    <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button class="btn btn-primary btn-sm" type="submit">Crear</button>
        </div>
    </form>
    <p class="hint" style="margin-top:.65rem">La relación con departamentos se podrá ajustar con más detalle más adelante.</p>
</div>

<div class="card section-card">
    <h3>Actividades (<?= count($actividades) ?>)</h3>
    <?php if ($actividades === []): ?>
        <p class="muted">Aún no hay actividades. Crea la primera arriba.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Departamento</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($actividades as $a): ?>
                <tr>
                    <td><span class="tag"><?= esc($a['nombre']) ?></span></td>
                    <td>
                        <?php if (! empty($a['departamento_id']) && isset($deptoMap[$a['departamento_id']])): ?>
                            <?= esc($deptoMap[$a['departamento_id']]) ?>
                        <?php else: ?>
                            <span class="muted">Sin relacionar</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
