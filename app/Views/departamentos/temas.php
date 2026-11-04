<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Temas de Interés</h2>
    <p>Tags de <strong>tema de interés</strong> de la empresa. Puedes crearlos sin departamento y relacionarlos después, o vincularlos ahora a un departamento activo.</p>
</section>

<?= view('departamentos/_tabs', ['tab' => 'temas']) ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Nuevo tema de interés</h3>
    <form method="post" action="<?= site_url('departamentos/temas') ?>" class="form-row" style="grid-template-columns:1.2fr 1fr auto;align-items:end">
        <?= csrf_field() ?>
        <div>
            <label for="tema_nombre">Nombre</label>
            <input id="tema_nombre" type="text" name="nombre" required placeholder="p.ej. Sostenibilidad">
        </div>
        <div>
            <label for="tema_depto">Departamento (opcional)</label>
            <select id="tema_depto" name="departamento_id">
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
    <h3>Temas de interés (<?= count($temas) ?>)</h3>
    <?php if ($temas === []): ?>
        <p class="muted">Aún no hay temas. Crea el primero arriba.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Departamento</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($temas as $t): ?>
                <tr>
                    <td><span class="tag"><?= esc($t['nombre']) ?></span></td>
                    <td>
                        <?php if (! empty($t['departamento_id']) && isset($deptoMap[$t['departamento_id']])): ?>
                            <?= esc($deptoMap[$t['departamento_id']]) ?>
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
