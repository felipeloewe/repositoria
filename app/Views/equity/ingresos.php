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
?>

<section class="banner">
    <h2>Ingresos</h2>
    <p>Fichas de ingreso mensual. Montos en $ con equivalente UF (tasa vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>).</p>
</section>

<div class="card section-card">
    <form method="get" action="<?= site_url('equity/ingresos') ?>" class="form-row" style="grid-template-columns:1fr auto auto">
        <div>
            <label for="persona_id">Persona</label>
            <select id="persona_id" name="persona_id">
                <option value="">Todas</option>
                <?php foreach ($personas as $p): ?>
                    <option value="<?= esc($p['id']) ?>" <?= $personaId === $p['id'] ? 'selected' : '' ?>>
                        <?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </div>
        <?php if (! empty($canManage) && $personaId !== ''): ?>
            <div>
                <label>&nbsp;</label>
                <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $personaId . '/fichas/nueva') ?>">Nueva ficha</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="card section-card">
    <h3>Fichas (<?= count($fichas) ?>)</h3>
    <?php if ($fichas === []): ?>
        <p class="muted">Sin fichas. Ábrelas desde la ficha de cada persona.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Corte</th>
                    <th>Ingreso mensual</th>
                    <th>Renta líquida</th>
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
                    <td><?= esc($fmt($f['renta_liquida'] ?? null)) ?></td>
                    <td>
                        <?php if ($p && ! empty($canManage)): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $p['id'] . '/fichas/' . $f['id'] . '/editar') ?>">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
