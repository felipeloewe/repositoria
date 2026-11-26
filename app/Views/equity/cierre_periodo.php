<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCierre;
use App\Libraries\EquityUf;

$label = EquityCierre::labelMes((int) $periodo['mes']) . ' ' . (int) $periodo['anio'];
$ufRate = (float) ($periodo['uf_valor_clp'] ?? 0);
?>

<section class="banner">
    <h2>Historial · <?= esc($label) ?></h2>
    <p>
        Reportes congelados al cierre con V.B.
        <?php if ($ufRate > 0): ?>
            · UF <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>
        <?php endif; ?>
        <?php if (! empty($periodo['vb_at'])): ?>
            · V.B. <?= esc($periodo['vb_at']) ?>
        <?php endif; ?>
    </p>
    <p style="margin-top:.75rem">
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/cierre') ?>">← Cierre mensual</a>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (! empty($periodo['vb_nota'])): ?>
<div class="card section-card">
    <h3>V.B.</h3>
    <p style="margin:0"><?= esc($periodo['vb_nota']) ?></p>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Reportes del periodo</h3>
    <?php if ($reportes === []): ?>
        <p class="muted">Sin reportes congelados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Generado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><?= esc($tipos[$r['tipo'] ?? ''] ?? ($r['tipo'] ?? '—')) ?></td>
                    <td><?= esc($r['titulo'] ?? '—') ?></td>
                    <td><?= esc($r['created_at'] ?? '—') ?></td>
                    <td>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/cierre/reporte/' . $r['id']) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Desarrollo de créditos · <?= esc($label) ?></h3>
    <p class="muted" style="margin-top:0">
        Cuota, amortización e intereses del mes cerrado (pares UF / $ según moneda del crédito).
    </p>
    <?php if ($desarrollo === []): ?>
        <p class="muted">Sin desarrollo congelado para este periodo.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Crédito</th>
                    <th>Moneda</th>
                    <th>N°</th>
                    <th>Cuota</th>
                    <th>Amortización</th>
                    <th>Interés</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($desarrollo as $d): ?>
                <?php
                $moneda = strtoupper((string) ($d['moneda'] ?? 'UF'));
                $fmtPar = static function (?float $uf, ?float $clp) use ($moneda): string {
                    if ($moneda === 'CLP') {
                        $a = $clp !== null ? EquityUf::fmtPesos($clp, 0) : '—';
                        $b = $uf !== null ? EquityUf::fmtUf($uf, 2) : null;
                    } else {
                        $a = $uf !== null ? EquityUf::fmtUf($uf, 2) : '—';
                        $b = $clp !== null ? EquityUf::fmtPesos($clp, 0) : null;
                    }

                    return $a . ($b !== null ? ' · ' . $b : '');
                };
                $cuotaUf = isset($d['cuota_uf']) && $d['cuota_uf'] !== null && $d['cuota_uf'] !== '' ? (float) $d['cuota_uf'] : null;
                $cuotaClp = isset($d['cuota_clp']) && $d['cuota_clp'] !== null && $d['cuota_clp'] !== '' ? (float) $d['cuota_clp'] : null;
                $amortUf = isset($d['amortizacion_uf']) && $d['amortizacion_uf'] !== null && $d['amortizacion_uf'] !== '' ? (float) $d['amortizacion_uf'] : null;
                $amortClp = isset($d['amortizacion_clp']) && $d['amortizacion_clp'] !== null && $d['amortizacion_clp'] !== '' ? (float) $d['amortizacion_clp'] : null;
                $intUf = isset($d['interes_uf']) && $d['interes_uf'] !== null && $d['interes_uf'] !== '' ? (float) $d['interes_uf'] : null;
                $intClp = isset($d['interes_clp']) && $d['interes_clp'] !== null && $d['interes_clp'] !== '' ? (float) $d['interes_clp'] : null;
                $saldoUf = isset($d['saldo_uf']) && $d['saldo_uf'] !== null && $d['saldo_uf'] !== '' ? (float) $d['saldo_uf'] : null;
                $saldoClp = isset($d['saldo_clp']) && $d['saldo_clp'] !== null && $d['saldo_clp'] !== '' ? (float) $d['saldo_clp'] : null;
                ?>
                <tr>
                    <td>
                        <?= esc($d['banco_descripcion'] ?? '—') ?>
                        <?php if (! empty($d['numero_credito'])): ?>
                            <div class="muted" style="font-size:.8rem"><?= esc($d['numero_credito']) ?></div>
                        <?php endif; ?>
                        <div class="muted" style="font-size:.8rem"><?= esc($d['categoria'] ?? '') ?></div>
                    </td>
                    <td><?= esc($moneda) ?></td>
                    <td><?= isset($d['dividendo_n']) && $d['dividendo_n'] !== null && $d['dividendo_n'] !== '' ? (int) $d['dividendo_n'] : '—' ?></td>
                    <td><?= esc($fmtPar($cuotaUf, $cuotaClp)) ?></td>
                    <td><?= esc($fmtPar($amortUf, $amortClp)) ?></td>
                    <td><?= esc($fmtPar($intUf, $intClp)) ?></td>
                    <td><?= esc($fmtPar($saldoUf, $saldoClp)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
