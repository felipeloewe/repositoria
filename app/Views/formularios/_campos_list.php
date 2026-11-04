<?php
/**
 * Tabla de campos de plantilla: agrupada por sección + filas amarillas si dup.
 *
 * @var list<array<string,mixed>> $campos
 * @var bool $esGenerica
 * @var string $deleteBaseUrl  e.g. socios/formularios/{id}/campos or formularios/{id}/campos
 */
use App\Libraries\FormEngine;

$campos   = FormEngine::markDuplicateCampos($campos ?? []);
$groups   = FormEngine::groupCamposBySeccion($campos);
$dupCount = count(array_filter($campos, static fn ($c) => ! empty($c['es_duplicado'])));
$esGenerica = ! empty($esGenerica);
$deleteBaseUrl = rtrim((string) ($deleteBaseUrl ?? ''), '/');
$colspan = $esGenerica ? 6 : 7;
?>
<?php if ($dupCount > 0): ?>
    <p class="form-dup-legend hint">
        <span class="form-dup-swatch" aria-hidden="true"></span>
        Amarillo (<?= (int) $dupCount ?>): etiqueta/clave repetida. La representación gráfica del PDF sí puede duplicar
        datos; el highlight es para no completar de más — ir corrigiendo de a poco.
    </p>
<?php endif; ?>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Sección</th>
            <th>Etiqueta</th>
            <th>Clave</th>
            <th>Tipo</th>
            <th>Oblig.</th>
            <?php if (! $esGenerica): ?><th></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($groups as $group): ?>
        <?php
            $secTitle = trim((string) ($group['seccion'] ?? ''));
            $items    = $group['campos'] ?? [];
        ?>
        <?php if ($secTitle !== ''): ?>
            <tr class="form-seccion-row">
                <td colspan="<?= (int) $colspan ?>"><strong><?= esc($secTitle) ?></strong></td>
            </tr>
        <?php endif; ?>
        <?php foreach ($items as $c): ?>
            <?php $dup = ! empty($c['es_duplicado']); ?>
            <tr class="<?= $dup ? 'form-campo--dup' : '' ?>">
                <td class="muted"><?= (int) ($c['orden'] ?? 0) ?></td>
                <td class="muted"><?= esc($secTitle !== '' ? $secTitle : '—') ?></td>
                <td>
                    <?= esc($c['etiqueta'] ?? '') ?>
                    <?php if ($dup): ?><span class="tag tag-dup">dup</span><?php endif; ?>
                </td>
                <td><code><?= esc($c['clave'] ?? '') ?></code></td>
                <td><?= esc($c['tipo'] ?? '') ?></td>
                <td><?= (int) ($c['obligatorio'] ?? 0) === 1 ? 'Sí' : 'No' ?></td>
                <?php if (! $esGenerica): ?>
                <td>
                    <form method="post" action="<?= site_url($deleteBaseUrl . '/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('¿Eliminar campo?')">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Eliminar</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
