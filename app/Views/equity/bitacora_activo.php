<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityActivoBitacora;
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$nombreP = $persona
    ? trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''))
    : '—';
?>

<section class="banner">
    <h2>Bitácora de valores</h2>
    <p>
        <?= esc($meta['label'] ?? '') ?>
        · <?= esc($detalle !== '' ? $detalle : 'Activo') ?>
        <?= $nombreP !== '—' ? ' · ' . esc($nombreP) : '' ?>.
        Los cambios se atribuyen al <strong>cierre de mes</strong>
        (corte actual <?= esc($cierreActual) ?>).
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $backUrl, '/')) ?>">← Volver</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $editUrl, '/')) ?>">Editar activo</a>
    </p>
</section>

<div class="card section-card">
    <h3>Campos con seguimiento</h3>
    <p class="muted" style="margin-top:0">
        <?php
        $labels = array_map(static fn ($c) => EquityActivoBitacora::labelCampo($c), $campos ?? []);
        echo esc($labels !== [] ? implode(' · ', $labels) : 'Sin campos de valor en esta sección');
        ?>.
        UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>.
    </p>
</div>

<div class="card section-card">
    <h3>Histórico por cierre de mes (<?= count($historial ?? []) ?>)</h3>
    <?php if (($historial ?? []) === []): ?>
        <p class="muted">Aún no hay cortes registrados. Al crear o cambiar el valor comercial<?= in_array(EquityActivoBitacora::CAMPO_AVALUO_FISCAL, $campos ?? [], true) ? ' o el avalúo fiscal' : '' ?>, quedará el registro mensual.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Cierre</th>
                    <th>Campo</th>
                    <th>UF</th>
                    <th>$</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= esc((string) ($h['fecha_cierre'] ?? '')) ?></td>
                    <td><?= esc(EquityActivoBitacora::labelCampo((string) ($h['campo'] ?? ''))) ?></td>
                    <td>
                        <?php if (($h['campo'] ?? '') === EquityActivoBitacora::CAMPO_AVALUO_FISCAL): ?>
                            —
                        <?php else: ?>
                            <?= esc(($h['valor_uf'] ?? null) !== null && $h['valor_uf'] !== '' ? EquityUf::fmtUf((float) $h['valor_uf'], 2) : '—') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(($h['valor_clp'] ?? null) !== null && $h['valor_clp'] !== '' ? EquityUf::fmtPesos((float) $h['valor_clp'], 0) : '—') ?></td>
                    <td class="muted"><?= esc(($h['nota'] ?? '') !== '' ? (string) $h['nota'] : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
