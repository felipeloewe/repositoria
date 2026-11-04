<?php
/**
 * Tabla de respuestas de instancia, agrupada por sección.
 *
 * @var list<array<string,mixed>> $campos
 * @var array<string,mixed> $respuestas
 */
use App\Libraries\FormEngine;

$campos = FormEngine::markDuplicateCampos($campos ?? []);
$groups = FormEngine::groupCamposBySeccion($campos);
$respuestas = $respuestas ?? [];
?>
<table class="table">
    <thead>
        <tr>
            <th>Campo</th>
            <th>Valor</th>
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
                <td colspan="2"><strong><?= esc($secTitle) ?></strong></td>
            </tr>
        <?php endif; ?>
        <?php foreach ($items as $c): ?>
            <?php
                $val = $respuestas[$c['clave']] ?? null;
                if (is_array($val)) {
                    $shown = ($val['nombre'] ?? 'archivo') . (! empty($val['documento_id']) ? ' → doc' : '');
                } else {
                    $shown = $val === null || $val === '' ? '—' : (string) $val;
                }
                $dup = ! empty($c['es_duplicado']);
            ?>
            <tr class="<?= $dup ? 'form-campo--dup' : '' ?>">
                <td>
                    <?= esc($c['etiqueta'] ?? '') ?>
                    <?php if ($dup): ?><span class="tag tag-dup">dup</span><?php endif; ?>
                </td>
                <td>
                    <?= esc($shown) ?>
                    <?php if (is_array($val) && ! empty($val['documento_id'])): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $val['documento_id']) ?>">Ver adjunto</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
