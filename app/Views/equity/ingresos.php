<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $clp = (float) $v;
    $uf  = $ufRate > 0 ? round($clp / $ufRate, 2) : null;

    return '$' . number_format($clp, 0, ',', '.') . ($uf !== null ? ' · ' . number_format($uf, 2, ',', '.') . ' UF' : '');
};
$qs = '';
$personaLabel = '';
foreach ($personas as $p) {
    if (($p['id'] ?? '') === $personaId) {
        $personaLabel = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        break;
    }
}
?>

<section class="banner">
    <h2>Ingresos</h2>
    <p>
        Fuentes de ingreso mensual en <strong>pesos</strong> (análisis); se muestra la equivalencia UF.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>
        <?= $personaLabel !== '' ? ' · ' . esc($personaLabel) : '' ?>.
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/reporte/ingresos') ?>">Reporte · Imprimir / PDF</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/situacion-financiera') ?>">Situación financiera</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="grid" style="margin-top:1rem;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <?php foreach ($fuentes as $clave => $meta): ?>
        <a class="card" href="<?= site_url('equity/ingresos/' . $clave . $qs) ?>" style="text-decoration:none;color:inherit">
            <h3><?= esc($meta['label']) ?></h3>
            <div class="value" style="font-size:1.25rem"><?= esc($fmt($totalesFuente[$clave] ?? 0)) ?></div>
            <div class="hint"><?= esc($meta['descripcion']) ?></div>
            <div style="margin-top:.75rem">
                <span class="btn btn-ghost btn-sm">Abrir fuente →</span>
            </div>
        </a>
    <?php endforeach; ?>
    <div class="card">
        <h3>Total ingresos</h3>
        <div class="value" style="font-size:1.25rem"><strong><?= esc($fmt($totalGeneral ?? 0)) ?></strong></div>
        <div class="hint">Suma de las 4 fuentes<?= $personaId === '' ? ' (consolidado)' : '' ?></div>
    </div>
</div>

<div class="card section-card" style="margin-top:1rem">
    <h3>Detalle por fuente</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Componentes</th>
                <th>Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fuentes as $clave => $meta): ?>
            <tr>
                <td><strong><?= esc($meta['label']) ?></strong></td>
                <td>
                    <?php
                    $parts = [];
                    foreach ($meta['campos'] as $c) {
                        $v = null;
                        if ($personaId !== '' && is_array($ficha ?? null)) {
                            $v = $ficha[$c['key']] ?? null;
                        }
                        $parts[] = $c['label'] . (
                            ($v !== null && $v !== '')
                                ? ' · $' . number_format((float) $v, 0, ',', '.')
                                : ''
                        );
                    }
                    echo esc(implode(' · ', $parts));
                    ?>
                </td>
                <td><?= esc($fmt($totalesFuente[$clave] ?? 0)) ?></td>
                <td>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/ingresos/' . $clave . $qs) ?>">Cargar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (($fichas ?? []) !== []): ?>
<div class="card section-card">
    <h3>Fichas registradas (<?= count($fichas) ?>)</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Persona</th>
                <th>Corte</th>
                <th>Total mensual</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fichas as $f): ?>
            <?php $p = $f['persona'] ?? null; ?>
            <tr>
                <td>
                    <?php if ($p): ?>
                        <a href="<?= site_url('equity/' . $p['id']) ?>"><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= esc($f['fecha_corte'] ?? '—') ?></td>
                <td><?= esc($fmt($f['ingreso_mensual_total'] ?? null)) ?></td>
                <td>
                    <?php if ($p && ! empty($canManage)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $p['id'] . '/fichas/' . $f['id'] . '/editar') ?>">Editar ficha</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
