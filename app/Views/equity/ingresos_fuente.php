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
$fmtInput = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }

    return number_format((float) $v, 0, ',', '.');
};
$val = static function (string $key) use ($ficha, $fmtInput): string {
    if (! is_array($ficha)) {
        return '';
    }

    return $fmtInput($ficha[$key] ?? null);
};
$qs = $personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : '';
?>

<section class="banner">
    <h2>Ingresos · <?= esc($meta['label']) ?></h2>
    <p><?= esc($meta['descripcion']) ?>. Montos mensuales en $.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/ingresos' . $qs) ?>">← Resumen ingresos</a>
    <?php foreach (\App\Libraries\EquityCatalogo::fuentesIngreso() as $k => $m): ?>
        <a href="<?= site_url('equity/ingresos/' . $k . $qs) ?>" class="<?= $k === $fuente ? 'active' : '' ?>"><?= esc($m['label']) ?></a>
    <?php endforeach; ?>
</div>

<div class="card section-card">
    <form method="get" action="<?= site_url('equity/ingresos/' . $fuente) ?>" class="form-row" style="grid-template-columns:1fr auto">
        <div>
            <label for="persona_id_filter">Persona</label>
            <select id="persona_id_filter" name="persona_id" onchange="this.form.submit()">
                <option value="">Selecciona persona…</option>
                <?php foreach ($personas as $p): ?>
                    <option value="<?= esc($p['id']) ?>" <?= $personaId === $p['id'] ? 'selected' : '' ?>>
                        <?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-ghost btn-sm" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<?php if ($personaId === ''): ?>
    <div class="card section-card">
        <p class="muted">Selecciona una persona para cargar o editar los montos de <strong><?= esc($meta['label']) ?></strong>.</p>
    </div>
<?php elseif (! empty($canManage)): ?>
    <div class="card section-card">
        <h3>Cargar <?= esc($meta['label']) ?></h3>
        <p class="muted" style="margin-top:0">
            Se guarda en la ficha de ingreso más reciente<?= is_array($ficha) && ! empty($ficha['fecha_corte']) ? ' (corte ' . esc($ficha['fecha_corte']) . ')' : ' (se crea una nueva si no existe)' ?>.
        </p>
        <form method="post" action="<?= site_url('equity/ingresos/' . $fuente) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="persona_id" value="<?= esc($personaId) ?>">
            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                <?php foreach ($meta['campos'] as $campo): ?>
                    <div>
                        <label for="<?= esc($campo['key']) ?>"><?= esc($campo['label']) ?> $</label>
                        <input id="<?= esc($campo['key']) ?>" name="<?= esc($campo['key']) ?>" type="text" inputmode="decimal"
                               placeholder="0" value="<?= esc($val($campo['key'])) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($fuente === 'inversiones' && ($arriendos ?? []) !== []): ?>
                <div style="margin-top:1rem;padding:1rem;border-radius:10px;background:rgba(15,76,129,.06)">
                    <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer">
                        <input type="checkbox" name="sync_arriendos" value="1" style="margin-top:.2rem">
                        <span>
                            Usar suma de cánones de bienes raíces como <strong>Arriendos</strong>
                            (<?= esc($fmt($totalCanon ?? 0)) ?> · <?= count($arriendos) ?> propiedad(es)).
                        </span>
                    </label>
                </div>
            <?php endif; ?>

            <div style="margin-top:1rem">
                <button class="btn btn-primary" type="submit">Guardar <?= esc($meta['label']) ?></button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="card section-card">
        <p class="muted">Sin permiso para editar. Puedes ver los montos actuales abajo.</p>
        <table class="table">
            <thead><tr><th>Concepto</th><th>Monto</th></tr></thead>
            <tbody>
            <?php foreach ($meta['campos'] as $campo): ?>
                <tr>
                    <td><?= esc($campo['label']) ?></td>
                    <td><?= esc($fmt(is_array($ficha) ? ($ficha[$campo['key']] ?? null) : null)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($fuente === 'inversiones' && ($arriendos ?? []) !== []): ?>
<div class="card section-card">
    <h3>Arriendos desde bienes raíces (<?= count($arriendos) ?>)</h3>
    <p class="muted" style="margin-top:0">Referencia desde Activos → Bienes raíces. Puedes sincronizarlos al campo Arriendos al guardar.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Dirección</th>
                <th>Arrendatario</th>
                <th>Pago</th>
                <th>Cánon</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($arriendos as $ar): ?>
            <tr>
                <td><?= esc($ar['direccion'] ?? $ar['descripcion'] ?? '—') ?></td>
                <td><?= esc($ar['arrendatario'] ?? '—') ?></td>
                <td><?= esc($ar['medio_pago'] ?? '—') ?></td>
                <td><strong><?= esc($fmt($ar['canon_show'] ?? null)) ?></strong></td>
                <td>
                    <?php if (! empty($canManage)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/activos/bienes-raices/' . $ar['id'] . '/editar') ?>">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right"><strong>Total cánones</strong></td>
                <td><strong><?= esc($fmt($totalCanon ?? 0)) ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?= $this->endSection() ?>