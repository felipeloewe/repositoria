<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return EquityUf::fmtAnalisisPesos((float) $v, $ufRate, 0, 2);
};
$personaLabel = '';
foreach ($personas as $p) {
    if (($p['id'] ?? '') === $personaId) {
        $personaLabel = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        break;
    }
}
?>

<section class="banner">
    <h2>Egresos</h2>
    <p>
        Carga mensual en <strong>pesos</strong> (análisis). Si el crédito es UF, la cuota se convierte con la UF vigente;
        si es $, se muestra la equivalencia UF.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>
        <?= $personaLabel !== '' ? ' · ' . esc($personaLabel) : '' ?>.
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/reporte/balance') ?>">Reporte balance</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/situacion-financiera') ?>">Situación financiera</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/egresos') ?>" class="active">Resumen</a>
    <?php foreach ($fuentes as $clave => $meta): ?>
        <a href="<?= site_url('equity/egresos/' . $clave) ?>"><?= esc($meta['label']) ?></a>
    <?php endforeach; ?>
</div>

<div class="grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <?php foreach ($fuentes as $clave => $meta): ?>
        <a class="card" href="<?= site_url('equity/egresos/' . $clave) ?>" style="text-decoration:none;color:inherit">
            <h3><?= esc($meta['label']) ?></h3>
            <div class="value" style="font-size:1.25rem"><?= esc($fmt($totalesFuente[$clave] ?? 0)) ?></div>
            <div class="hint">
                <?= (int) ($conteosFuente[$clave] ?? 0) ?> registro(s)
                <?php if ($clave === 'lineas'): ?> · cupo (sin cuota)<?php else: ?> · cuota mes<?php endif; ?>
            </div>
            <div style="margin-top:.75rem">
                <span class="btn btn-ghost btn-sm">Abrir →</span>
            </div>
        </a>
    <?php endforeach; ?>
    <div class="card">
        <h3>Total egresos mes</h3>
        <div class="value" style="font-size:1.25rem"><strong><?= esc($fmt($totalGeneral ?? 0)) ?></strong></div>
        <div class="hint">Suma de cuotas en $ (sin líneas)</div>
    </div>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Detalle por tipo</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Registros</th>
                <th>Cuota / mes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fuentes as $clave => $meta): ?>
            <tr>
                <td>
                    <strong><?= esc($meta['label']) ?></strong>
                    <div class="muted" style="font-size:.85em"><?= esc($meta['descripcion']) ?></div>
                </td>
                <td><?= (int) ($conteosFuente[$clave] ?? 0) ?></td>
                <td><?= esc($fmt($totalesFuente[$clave] ?? 0)) ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/egresos/' . $clave) ?>">Ver</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
