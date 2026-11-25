<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCierre;
use App\Libraries\EquityUf;

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$cerrado = is_array($periodoActual) && ($periodoActual['estado'] ?? '') === 'cerrado';
$ufOk = is_array($ufRow) && ! empty($ufRow['valor_clp']);
?>

<section class="banner">
    <h2>Cierre mensual</h2>
    <p>
        Valida con V.B. que la información del mes está completa y correcta.
        Al cerrar se congelan los reportes vigentes en Historial y el desarrollo de créditos (cuota, amortización e intereses) del periodo.
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (empty($tablasOk)): ?>
    <div class="alert alert-error">
        Falta la migración de cierre mensual (tablas <code>equity_periodos_cierre</code> / historial).
        Despliega la migración 000037 en producción.
    </div>
<?php endif; ?>

<div class="card section-card">
    <h3>Periodo a cerrar</h3>
    <form method="get" action="<?= site_url('equity/cierre') ?>" style="margin-bottom:1rem">
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end">
            <div>
                <label for="mes">Mes</label>
                <select id="mes" name="mes">
                    <?php foreach ($meses as $n => $lab): ?>
                        <option value="<?= $n ?>" <?= (int) $mes === $n ? 'selected' : '' ?>><?= $n ?>. <?= esc($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="anio">Año</label>
                <input id="anio" name="anio" type="number" min="2000" max="2100" value="<?= (int) $anio ?>">
            </div>
            <div>
                <button class="btn btn-ghost" type="submit">Ver periodo</button>
            </div>
        </div>
    </form>

    <p style="margin:0 0 .5rem">
        <strong><?= esc($meses[(int) $mes] ?? $mes) ?> <?= (int) $anio ?></strong>
        <span class="muted"> · cierre contable <?= esc($fechaCierre) ?></span>
        <?php if ($ufOk): ?>
            <span class="muted"> · UF <?= esc(EquityUf::fmtPesos((float) $ufRow['valor_clp'], 2)) ?></span>
        <?php else: ?>
            <span class="muted"> · sin UF de cierre (cárgala en Configuración)</span>
        <?php endif; ?>
    </p>

    <?php if ($cerrado): ?>
        <p style="margin:.75rem 0">
            <span class="badge" style="background:#0f6b4c;color:#fff;padding:.2rem .55rem;border-radius:6px;font-size:.85rem">Cerrado · V.B.</span>
            <?php if (! empty($periodoActual['vb_at'])): ?>
                <span class="muted"> · <?= esc($periodoActual['vb_at']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (! empty($periodoActual['vb_nota'])): ?>
            <p class="muted" style="margin:.25rem 0 1rem"><?= esc($periodoActual['vb_nota']) ?></p>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= site_url('equity/cierre/periodo/' . $periodoActual['id']) ?>">Ver historial del periodo</a>
    <?php elseif (! empty($canDeclare) && ! empty($tablasOk)): ?>
        <form method="post" action="<?= site_url('equity/cierre/cerrar') ?>"
              onsubmit="return confirm('¿Confirmas el V.B. de <?= esc($meses[(int) $mes] ?? '') ?> <?= (int) $anio ?>? Se congelarán los reportes vigentes en Historial.');">
            <?= csrf_field() ?>
            <input type="hidden" name="anio" value="<?= (int) $anio ?>">
            <input type="hidden" name="mes" value="<?= (int) $mes ?>">
            <div class="form-row" style="grid-template-columns:1fr;margin-top:1rem">
                <div>
                    <label for="vb_nota">Nota de V.B.</label>
                    <input id="vb_nota" name="vb_nota" type="text" maxlength="500"
                           value="V.B. de cierre: información completa, validada y valores correctos.">
                </div>
                <div>
                    <label for="notas">Notas internas (opcional)</label>
                    <input id="notas" name="notas" type="text" maxlength="500" placeholder="Observaciones del periodo">
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-primary" type="submit" <?= $ufOk ? '' : 'disabled' ?>>Cerrar con V.B. y generar reportes</button>
                <a class="btn btn-ghost" href="<?= site_url('equity/reporte/balance') ?>">Revisar Balance</a>
                <a class="btn btn-ghost" href="<?= site_url('equity/configuracion') ?>">UF / Configuración</a>
            </div>
            <?php if (! $ufOk): ?>
                <p class="muted" style="margin-top:.75rem">Necesitas la UF de cierre de este mes antes de cerrar.</p>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p class="muted">Sin permiso para cerrar, o falta migración.</p>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Historial de periodos</h3>
    <?php if ($periodos === []): ?>
        <p class="muted">Aún no hay periodos cerrados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Estado</th>
                    <th>UF cierre</th>
                    <th>V.B.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periodos as $p): ?>
                <tr>
                    <td><?= esc(EquityCierre::labelMes((int) $p['mes'])) ?> <?= (int) $p['anio'] ?></td>
                    <td><?= esc($p['estado'] ?? '—') ?></td>
                    <td><?= ! empty($p['uf_valor_clp']) ? esc(EquityUf::fmtPesos((float) $p['uf_valor_clp'], 2)) : '—' ?></td>
                    <td><?= ! empty($p['vb_at']) ? esc($p['vb_at']) : '—' ?></td>
                    <td>
                        <?php if (($p['estado'] ?? '') === 'cerrado'): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/cierre/periodo/' . $p['id']) ?>">Abrir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
