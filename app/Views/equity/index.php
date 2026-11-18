<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php use App\Libraries\EquityAcl; ?>

<?php
$bannerTitle = 'Grupo Familiar';
$bannerLead  = 'Antecedentes personales y laborales con ficha financiera y respaldos.';
if (($filters['tipo'] ?? '') === EquityAcl::TIPO_SOCIO) {
    $bannerTitle = 'Antecedentes Personales';
    $bannerLead  = 'Información personal y financiera del grupo familiar.';
} elseif (($filters['tipo'] ?? '') === EquityAcl::TIPO_GERENTE) {
    $bannerTitle = 'Antecedentes Laborales';
    $bannerLead  = 'Información laboral y financiera del grupo familiar.';
}
?>
<section class="banner">
    <h2><?= esc($bannerTitle) ?></h2>
    <p><?= esc($bannerLead) ?></p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Filtros</h3>
    <form method="get" action="<?= site_url('equity') ?>" class="form-row" style="grid-template-columns:1fr 1fr auto">
        <div>
            <label for="f_tipo">Tipo</label>
            <select id="f_tipo" name="tipo">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= esc($t) ?>" <?= ($filters['tipo'] ?? '') === $t ? 'selected' : '' ?>>
                        <?= esc(EquityAcl::labelTipo($t)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f_activo">Estado</label>
            <select id="f_activo" name="activo">
                <option value="">Todos</option>
                <option value="1" <?= ($filters['activo'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= ($filters['activo'] ?? '') === '0' ? 'selected' : '' ?>>Inactivos</option>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<?php if (! empty($canManage)): ?>
<div class="card section-card">
    <h3>Nuevo integrante</h3>
    <p class="muted" style="margin-top:0">Registra un integrante del grupo familiar (antecedentes personales o laborales).</p>
    <a class="btn btn-primary" href="<?= site_url('equity/nueva') ?>">Agregar al grupo</a>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Grupo Familiar (<?= count($personas) ?>)</h3>
    <?php if ($personas === []): ?>
        <p class="muted">Aún no hay socios ni gerentes en Equity.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>RUT / Cargo</th>
                <th>Fichas</th>
                <th>Respaldos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($personas as $p): ?>
            <tr>
                <td>
                    <a href="<?= site_url('equity/' . $p['id']) ?>">
                        <strong><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></strong>
                    </a>
                    <?php if ((int) ($p['activo'] ?? 1) !== 1): ?>
                        <span class="tag tag-borrador-tipo">Inactivo</span>
                    <?php endif; ?>
                    <?php if (! empty($p['email'])): ?>
                        <br><span class="muted" style="font-size:.8rem"><?= esc($p['email']) ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="tag tag-oficial"><?= esc(EquityAcl::labelTipo((string) ($p['tipo'] ?? 'socio'))) ?></span>
                </td>
                <td class="muted">
                    <?= esc($p['rut'] ?? '—') ?>
                    <?php if (! empty($p['cargo'])): ?>
                        <br><?= esc($p['cargo']) ?>
                    <?php endif; ?>
                </td>
                <td><?= (int) ($p['fichas_count'] ?? 0) ?></td>
                <td><?= (int) ($p['respaldos_count'] ?? 0) ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $p['id']) ?>">Abrir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
