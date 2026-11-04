<?php
/**
 * Renders fill inputs grouped by seccion; yellow highlight for duplicate labels/keys.
 *
 * @var list<array<string,mixed>> $campos
 */
use App\Libraries\FormEngine;

$campos = FormEngine::markDuplicateCampos($campos ?? []);
$groups = FormEngine::groupCamposBySeccion($campos);
$dupCount = count(array_filter($campos, static fn ($c) => ! empty($c['es_duplicado'])));
?>
<?php if ($dupCount > 0): ?>
    <p class="form-dup-legend hint">
        <span class="form-dup-swatch" aria-hidden="true"></span>
        Amarillo (<?= (int) $dupCount ?>): etiqueta/clave que se repite. El PDF a menudo duplica datos en la gráfica;
        no hace falta completar de más — revisa y rellena solo lo necesario.
    </p>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
    <?php
        $secTitle = trim((string) ($group['seccion'] ?? ''));
        $items    = $group['campos'] ?? [];
    ?>
    <?php if ($secTitle !== ''): ?>
        <h3 class="form-seccion-title"><?= esc($secTitle) ?></h3>
    <?php endif; ?>
    <?php foreach ($items as $c): ?>
        <?php
            $clave = (string) $c['clave'];
            $req   = (int) ($c['obligatorio'] ?? 0) === 1;
            $label = ($c['etiqueta'] ?? $clave) . ($req ? ' *' : '');
            $dup   = ! empty($c['es_duplicado']);
            $wrapClass = 'form-campo' . ($dup ? ' form-campo--dup' : '');
        ?>
        <div class="<?= esc($wrapClass) ?>">
            <label for="f_<?= esc($clave) ?>">
                <?= esc($label) ?>
                <?php if ($dup): ?><span class="tag tag-dup" title="Etiqueta o clave repetida">dup</span><?php endif; ?>
            </label>
            <?php if (($c['tipo'] ?? '') === 'date'): ?>
                <input id="f_<?= esc($clave) ?>" type="date" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?>>
            <?php elseif (($c['tipo'] ?? '') === 'file'): ?>
                <input id="f_<?= esc($clave) ?>" type="file" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?> style="margin-bottom:1rem">
            <?php else: ?>
                <input id="f_<?= esc($clave) ?>" type="text" name="<?= esc($clave) ?>" <?= $req ? 'required' : '' ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>
