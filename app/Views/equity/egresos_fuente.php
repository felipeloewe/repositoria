<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$fmt = static function ($v) use ($ufRate): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return EquityUf::fmtAnalisisPesos((float) $v, $ufRate, 0, 2);
};
$fmtUf = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 4, ',', '.') . ' UF';
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
    <h2>Egresos · <?= esc($meta['label']) ?></h2>
    <p>
        <?= esc($meta['descripcion']) ?>
        <?= $personaLabel !== '' ? ' · ' . esc($personaLabel) : '' ?>.
        Cuota mes: <strong><?= esc($fmt($totalFuente ?? 0)) ?></strong>
        · <?= (int) count($items ?? []) ?> registro(s).
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/reporte/egresos') ?>">Reporte · Imprimir / PDF</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim($meta['pasivo_path'], '/')) ?>">Editar pasivos</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/egresos') ?>">Resumen egresos</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/egresos') ?>">← Resumen egresos</a>
    <?php foreach (EquityCatalogo::fuentesEgreso() as $k => $m): ?>
        <a href="<?= site_url('equity/egresos/' . $k) ?>" class="<?= $k === $fuente ? 'active' : '' ?>"><?= esc($m['label']) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($personaId === ''): ?>
    <div class="card section-card">
        <p class="muted">No hay persona activa en la sesión. La matriz debe inscribirte o, si representas a alguien, elige persona al ingresar.</p>
    </div>
<?php elseif (($items ?? []) === []): ?>
    <div class="card section-card">
        <p class="muted" style="margin:0">
            No hay pasivos de este tipo.
            <a href="<?= site_url(ltrim($meta['pasivo_path'], '/')) ?>">Cargar en Pasivos →</a>
        </p>
    </div>
<?php else: ?>

        <div class="card section-card">
            <h3>Créditos · cuota actual</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Institución</th>
                        <th>Nº</th>
                        <th>Moneda</th>
                        <th>Reg. / venc.</th>
                        <th>Div.</th>
                        <th>Saldo</th>
                        <th>Cuota mes ($)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?= esc($row['banco_descripcion'] ?? '—') ?></td>
                        <td><?= esc($row['numero_credito'] ?? '—') ?></td>
                        <td><?= esc($row['_moneda'] ?? 'UF') ?></td>
                        <td>
                            <?= esc($row['fecha_registro'] ?? '—') ?>
                            <?php if (! empty($row['fecha_vencimiento']) && ($row['fecha_vencimiento'] ?? '') !== ($row['fecha_registro'] ?? '')): ?>
                                <div class="muted" style="font-size:.85em">Venc. <?= esc($row['fecha_vencimiento']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($row['dividendo_n'] ?? '—') ?></td>
                        <td>
                            <?= esc($fmtUf($row['saldo_uf'] ?? null)) ?>
                            <?php if (! empty($row['saldo_clp'])): ?>
                                <div class="muted" style="font-size:.85em"><?= esc(EquityUf::fmtPesos((float) $row['saldo_clp'], 0)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($fmt($row['_cuota_clp'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align:right"><strong>Total cuota mes ($)</strong></td>
                        <td><strong><?= esc($fmt($totalFuente ?? 0)) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card section-card">
            <h3>Próximos vencimientos (<?= (int) ($mesesProyeccion ?? 12) ?> meses)</h3>
            <p class="muted" style="margin-top:0">
                Totales en <strong>$</strong> (análisis). Créditos en UF: cuota = UF × tasa vigente. Créditos en $: se muestra equivalencia UF.
                Hipotecarios parten de la fecha de registro del dividendo.
            </p>
            <?php if (($proyeccionMeses ?? []) === []): ?>
                <p class="muted">Sin fechas o cuotas suficientes para proyectar.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Vencimientos</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proyeccionMeses as $mes => $bloque): ?>
                        <tr>
                            <td><strong><?= esc($mes) ?></strong></td>
                            <td>
                                <?php foreach ($bloque['items'] as $it): ?>
                                    <div>
                                        <?= esc($it['label']) ?>
                                        · <?= esc($it['fecha']) ?>
                                        <?php if (! empty($it['dividendo_n'])): ?>
                                            · Div. <?= (int) $it['dividendo_n'] ?>
                                        <?php endif; ?>
                                        · <?= esc($fmt($it['cuota_clp'] ?? null)) ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td><?= esc($fmt($bloque['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if (($desarrollos ?? []) !== []): ?>
            <?php foreach ($desarrollos as $dev): ?>
                <?php $devMoneda = strtoupper((string) ($dev['moneda'] ?? 'UF')) === 'CLP' ? 'CLP' : 'UF'; ?>
                <div class="card section-card">
                    <h3>
                        Desarrollo · <?= esc($dev['label']) ?>
                        <?php if (! empty($dev['numero'])): ?>
                            · <?= esc($dev['numero']) ?>
                        <?php endif; ?>
                        · moneda <?= esc($devMoneda === 'CLP' ? '$' : 'UF') ?>
                    </h3>
                    <p class="muted" style="margin-top:0">
                        Desarrollo en <?= $devMoneda === 'CLP' ? '<strong>$</strong> (equivalencia UF)' : '<strong>UF</strong> (equivalencia $)' ?>.
                        El análisis consolidado de egresos sigue en pesos.
                    </p>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Div.</th>
                                <?php if ($devMoneda === 'UF'): ?>
                                    <th>Cuota UF</th>
                                    <th>Cuota $</th>
                                    <th>Amort. UF</th>
                                    <th>Amort. $</th>
                                    <th>Saldo UF</th>
                                    <th>Saldo $</th>
                                <?php else: ?>
                                    <th>Cuota $</th>
                                    <th>Cuota UF</th>
                                    <th>Amort. $</th>
                                    <th>Amort. UF</th>
                                    <th>Saldo $</th>
                                    <th>Saldo UF</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dev['cuotas'] as $c): ?>
                            <tr>
                                <td><?= esc($c['fecha']) ?></td>
                                <td><?= esc($c['dividendo_n'] ?? '—') ?></td>
                                <?php if ($devMoneda === 'UF'): ?>
                                    <td><strong><?= esc($fmtUf($c['cuota_uf'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmt($c['cuota_clp'] ?? null)) ?></td>
                                    <td><strong><?= esc($fmtUf($c['amortizacion_uf'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmt($c['amortizacion_clp'] ?? null)) ?></td>
                                    <td><strong><?= esc($fmtUf($c['saldo_uf'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmt($c['saldo_clp'] ?? null)) ?></td>
                                <?php else: ?>
                                    <td><strong><?= esc($fmt($c['cuota_clp'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmtUf($c['cuota_uf'] ?? null)) ?></td>
                                    <td><strong><?= esc($fmt($c['amortizacion_clp'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmtUf($c['amortizacion_uf'] ?? null)) ?></td>
                                    <td><strong><?= esc($fmt($c['saldo_clp'] ?? null)) ?></strong></td>
                                    <td><?= esc($fmtUf($c['saldo_uf'] ?? null)) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>
