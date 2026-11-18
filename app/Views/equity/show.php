<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityAcl;

$nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));
$fmt = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 0, ',', '.');
};
?>

<section class="banner">
    <h2><?= esc($nombre) ?></h2>
    <p>
        <?= esc(EquityAcl::labelTipo((string) ($persona['tipo'] ?? 'socio'))) ?>
        <?php if (! empty($persona['cargo'])): ?>
            · <?= esc($persona['cargo']) ?>
        <?php endif; ?>
        <?php if (! empty($persona['rut'])): ?>
            · RUT <?= esc($persona['rut']) ?>
        <?php endif; ?>
        <?php if ((int) ($persona['activo'] ?? 1) !== 1): ?>
            · <strong>Inactivo</strong>
        <?php endif; ?>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity') ?>">← Volver a Equity</a>
    <?php if (! empty($canManage)): ?>
        <a href="<?= site_url('equity/' . $persona['id'] . '/editar') ?>">Editar persona</a>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>Datos</h3>
    <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div>
            <label>Email</label>
            <div><?= esc($persona['email'] ?? '—') ?></div>
        </div>
        <div>
            <label>Cargo</label>
            <div><?= esc($persona['cargo'] ?? '—') ?></div>
        </div>
        <div>
            <label>Notas</label>
            <div class="muted"><?= esc($persona['notas'] ?? '—') ?></div>
        </div>
    </div>
</div>

<div class="card section-card">
    <h3>Fichas financieras (<?= count($fichas) ?>)</h3>
    <?php if (! empty($canManage)): ?>
        <p style="margin-top:0">
            <a class="btn btn-primary btn-sm" href="<?= site_url('equity/' . $persona['id'] . '/fichas/nueva') ?>">Nueva ficha</a>
        </p>
    <?php endif; ?>
    <?php if ($fichas === []): ?>
        <p class="muted">Sin fichas registradas.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Corte</th>
                <th>Ingreso mensual</th>
                <th>Activos</th>
                <th>Pasivos</th>
                <th>Patrimonio</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($fichas as $f): ?>
            <tr>
                <td><?= esc($f['fecha_corte'] ?? '—') ?><br><span class="muted" style="font-size:.8rem"><?= esc($f['moneda'] ?? 'CLP') ?></span></td>
                <td><?= esc($fmt($f['ingreso_mensual_total'] ?? $f['renta_liquida'] ?? null)) ?></td>
                <td><?= esc($fmt($f['activos_total'] ?? null)) ?></td>
                <td><?= esc($fmt($f['pasivos_total'] ?? null)) ?></td>
                <td><strong><?= esc($fmt($f['patrimonio_neto'] ?? null)) ?></strong></td>
                <td style="white-space:nowrap">
                    <?php if (! empty($canManage)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/' . $persona['id'] . '/fichas/' . $f['id'] . '/editar') ?>">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (! empty($canUpload)): ?>
<div class="card section-card">
    <h3>Subir respaldo</h3>
    <form method="post" action="<?= site_url('equity/' . $persona['id'] . '/respaldos') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="up_titulo">Título</label>
        <input id="up_titulo" type="text" name="titulo" required>
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="up_tipo_doc">Tipo de documento</label>
                <select id="up_tipo_doc" name="tipo_id" required>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= esc($t['id']) ?>"><?= esc($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_tipo_res">Tipo de respaldo</label>
                <select id="up_tipo_res" name="tipo_respaldo">
                    <option value="">—</option>
                    <?php foreach ($tiposRespaldo as $tr): ?>
                        <option value="<?= esc($tr) ?>"><?= esc($tr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_ficha">Vincular a ficha (opcional)</label>
                <select id="up_ficha" name="ficha_id">
                    <option value="">Ninguna</option>
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= esc($f['id']) ?>">
                            <?= esc($f['fecha_corte'] ?? $f['created_at'] ?? $f['id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="up_desc">Descripción</label>
                <input id="up_desc" type="text" name="descripcion" placeholder="Opcional">
            </div>
        </div>
        <label for="up_file">Archivo (máx. 15 MB)</label>
        <input id="up_file" type="file" name="archivo" required style="margin-bottom:1rem">
        <button class="btn btn-primary" type="submit">Subir respaldo</button>
    </form>
</div>
<?php elseif (empty($tipos)): ?>
<div class="alert alert-info">Necesitas al menos un <a href="<?= site_url('documentos/tipos') ?>">tipo de documento</a> activo para subir respaldos.</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Respaldos (<?= count($respaldos) ?>)</h3>
    <?php if ($respaldos === []): ?>
        <p class="muted">Sin respaldos cargados.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Documento</th>
                <th>Tipo respaldo</th>
                <th>Fecha</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($respaldos as $r): ?>
            <?php $doc = $r['documento'] ?? null; ?>
            <tr>
                <td>
                    <?php if ($doc): ?>
                        <a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a><br>
                        <span class="muted" style="font-size:.8rem"><?= esc($doc['nombre_archivo'] ?? '') ?></span>
                    <?php else: ?>
                        <span class="muted">Documento no disponible</span>
                    <?php endif; ?>
                    <?php if (! empty($r['descripcion'])): ?>
                        <br><span class="muted"><?= esc($r['descripcion']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= esc($r['tipo_respaldo'] ?? '—') ?></td>
                <td class="muted"><?= esc($r['created_at'] ?? '') ?></td>
                <td style="white-space:nowrap">
                    <?php if ($doc): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id']) ?>">Abrir</a>
                        <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
                    <?php endif; ?>
                    <?php if (! empty($r['can_delete']) && $doc): ?>
                        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este respaldo?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
