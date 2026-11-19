<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$vig = $vigente ?? null;
$editUf = $editUf ?? null;
$anioNow = (int) date('Y');
$mesNow  = (int) date('n');
$anioForm = $anioNow;
$mesForm  = $mesNow;
$valorForm = '';
$notasForm = '';
if (is_array($editUf)) {
    $fc = (string) ($editUf['fecha_cierre'] ?? '');
    if ($fc !== '') {
        $anioForm = (int) substr($fc, 0, 4);
        $mesForm  = (int) substr($fc, 5, 2);
    }
    if (isset($editUf['valor_clp']) && $editUf['valor_clp'] !== null && $editUf['valor_clp'] !== '') {
        $valorForm = number_format((float) $editUf['valor_clp'], 2, ',', '.');
    }
    $notasForm = (string) ($editUf['notas'] ?? '');
}
?>

<section class="banner">
    <h2>Configuración</h2>
    <p>Variables del estado de situación. La UF al cierre de cada mes fija el equivalente en $ de los montos patrimoniales.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if ($vig): ?>
<div class="card section-card">
    <h3>UF vigente</h3>
    <p style="margin:0;font-size:1.35rem">
        <strong><?= esc(EquityUf::fmtPesos((float) $vig['valor_clp'], 2)) ?></strong>
        <span class="muted"> · cierre <?= esc($vig['fecha_cierre']) ?></span>
    </p>
    <p class="muted" style="margin:.5rem 0 0">Los activos/pasivos se registran en UF (fijas). El valorizado en $ se recalcula con esta tasa.</p>
</div>
<?php endif; ?>

<div class="card section-card" id="form-uf">
    <h3><?= is_array($editUf) ? 'Editar UF de cierre' : 'Registrar / actualizar UF de cierre' ?></h3>
    <?php if (is_array($editUf)): ?>
        <p class="muted" style="margin-top:0"><a href="<?= site_url('equity/configuracion') ?>">Cancelar edición</a></p>
    <?php endif; ?>
    <form method="post" action="<?= site_url('equity/configuracion/uf') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
            <div>
                <label for="mes">Mes de cierre *</label>
                <select id="mes" name="mes" required>
                    <?php
                    $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                    foreach ($meses as $n => $lab):
                    ?>
                        <option value="<?= $n ?>" <?= $n === $mesForm ? 'selected' : '' ?>><?= $n ?>. <?= esc($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="anio">Año *</label>
                <input id="anio" name="anio" type="number" min="1990" max="2100" required value="<?= $anioForm ?>">
            </div>
            <div>
                <label for="valor_clp">Valor UF $ *</label>
                <input id="valor_clp" name="valor_clp" type="text" inputmode="decimal" required placeholder="41.247,63" value="<?= esc($valorForm) ?>">
            </div>
            <div style="grid-column:1/-1">
                <label for="notas">Notas</label>
                <input id="notas" name="notas" type="text" placeholder="Opcional" value="<?= esc($notasForm) ?>">
            </div>
        </div>
        <div style="margin-top:1rem">
            <button class="btn btn-primary" type="submit"><?= is_array($editUf) ? 'Actualizar UF' : 'Guardar UF' ?></button>
        </div>
    </form>
</div>

<div class="card section-card">
    <h3>Histórico UF (<?= count($valores) ?>)</h3>
    <?php if ($valores === []): ?>
        <p class="muted">Sin valores registrados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha cierre</th>
                    <th>Valor UF $</th>
                    <th>Notas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($valores as $row): ?>
                <tr>
                    <td><?= esc($row['fecha_cierre']) ?></td>
                    <td><?= esc(EquityUf::fmtPesos((float) $row['valor_clp'], 2)) ?></td>
                    <td><?= esc($row['notas'] ?? '—') ?></td>
                    <td style="white-space:nowrap">
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/configuracion') ?>?edit_uf=<?= esc($row['id']) ?>#form-uf">Editar</a>
                        <form method="post" action="<?= site_url('equity/configuracion/uf/' . $row['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este valor UF?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
