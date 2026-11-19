<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php use App\Libraries\EquityAcl; ?>

<?php
$bannerTitle = 'Grupo Familiar';
$bannerLead  = 'Antecedentes personales, laborales y cónyuge/conviviente.';
if (($filters['tipo'] ?? '') === EquityAcl::TIPO_SOCIO) {
    $bannerTitle = 'Antecedentes Personales';
    $bannerLead  = 'Información personal y financiera del grupo familiar.';
} elseif (($filters['tipo'] ?? '') === EquityAcl::TIPO_GERENTE) {
    $bannerTitle = 'Antecedentes Laborales';
    $bannerLead  = 'Información laboral y financiera del grupo familiar.';
} elseif (($filters['tipo'] ?? '') === EquityAcl::TIPO_FAMILIAR) {
    $bannerTitle = 'Antecedentes Grupo Familiar';
    $bannerLead  = 'Datos de cónyuge o conviviente civil.';
}
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
    <h2><?= esc($bannerTitle) ?></h2>
    <p><?= esc($bannerLead) ?></p>
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
    <?php if (($filters['tipo'] ?? '') === EquityAcl::TIPO_GERENTE): ?>
        <h3>Antecedentes laborales</h3>
        <p class="muted" style="margin-top:0">Se recuperan por RUT desde Antecedentes Personales (mismos nombres, apellidos y RUT).</p>
        <a class="btn btn-primary" href="<?= site_url('equity/nueva?tipo=gerente') ?>">Cargar / actualizar laborales</a>
    <?php else: ?>
        <h3>Nuevo integrante</h3>
        <p class="muted" style="margin-top:0">Registra un integrante del grupo familiar.</p>
        <a class="btn btn-primary" href="<?= site_url('equity/nueva' . (($filters['tipo'] ?? '') !== '' ? '?tipo=' . rawurlencode((string) $filters['tipo']) : '')) ?>">Agregar al grupo</a>
    <?php endif; ?>
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
                    <?php
                        $labelTipo = (($filters['tipo'] ?? '') === EquityAcl::TIPO_GERENTE)
                            ? EquityAcl::labelTipo(EquityAcl::TIPO_GERENTE)
                            : EquityAcl::labelTipo((string) ($p['tipo'] ?? 'socio'));
                    ?>
                    <span class="tag tag-oficial"><?= esc($labelTipo) ?></span>
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
                    <?php if (! empty($canManage) && ($filters['tipo'] ?? '') === EquityAcl::TIPO_GERENTE): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $p['id'] . '/editar?vista=laboral') ?>">Laborales</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
