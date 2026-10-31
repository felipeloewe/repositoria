<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Repositorio documental</h2>
    <p>Documentos ligados a <strong>Departamentos</strong>; alcance opcional por sucursal. ACL por rol de membresía.</p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Filtros</h3>
    <form method="get" action="<?= site_url('documentos') ?>" class="form-row" style="grid-template-columns:1fr 1fr 1fr auto">
        <div>
            <label for="f_depto">Departamento</label>
            <select id="f_depto" name="departamento_id">
                <option value="">Todos</option>
                <?php foreach ($deptos as $d): ?>
                    <option value="<?= esc($d['id']) ?>" <?= ($filters['departamento_id'] ?? '') === $d['id'] ? 'selected' : '' ?>>
                        <?= esc($d['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f_ambito">Ámbito</label>
            <select id="f_ambito" name="ambito">
                <option value="">Todos</option>
                <option value="depto" <?= ($filters['ambito'] ?? '') === 'depto' ? 'selected' : '' ?>>Nivel departamento</option>
                <option value="sucursal" <?= ($filters['ambito'] ?? '') === 'sucursal' ? 'selected' : '' ?>>Por sucursal</option>
            </select>
        </div>
        <div>
            <label for="f_sec">Sección</label>
            <select id="f_sec" name="seccion_id">
                <option value="">Todas</option>
                <?php foreach ($secciones as $s): ?>
                    <option value="<?= esc($s['id']) ?>" <?= ($filters['seccion_id'] ?? '') === $s['id'] ? 'selected' : '' ?>>
                        <?= esc(($deptoMap[$s['departamento_id']] ?? '') . ' · ' . $s['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        </div>
    </form>
</div>

<?php if (! empty($canUpload)): ?>
<div class="card section-card">
    <h3>Subir documento</h3>
    <form method="post" action="<?= site_url('documentos/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="up_titulo">Título</label>
        <input id="up_titulo" type="text" name="titulo" required>
        <label for="up_sec">Sección</label>
        <select id="up_sec" name="seccion_id" required>
            <?php foreach ($uploadableSections as $s): ?>
                <option value="<?= esc($s['id']) ?>"><?= esc($s['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="up_file">Archivo (máx. 15 MB)</label>
        <input id="up_file" type="file" name="archivo" required style="margin-bottom:1rem">
        <button class="btn btn-primary" type="submit">Subir</button>
    </form>
</div>
<?php endif; ?>

<?php if (! empty($canManageSections)): ?>
<div class="card section-card">
    <h3>Nueva sección</h3>
    <form method="post" action="<?= site_url('documentos/seccion') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr auto;align-items:end">
            <div>
                <label>Departamento</label>
                <select name="departamento_id" required>
                    <?php foreach ($deptos as $d): ?>
                        <option value="<?= esc($d['id']) ?>"><?= esc($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Sucursal (vacío = nivel depto)</label>
                <select name="sucursal_id">
                    <option value="">— Nivel departamento —</option>
                    <?php foreach ($sucursales as $s): ?>
                        <option value="<?= esc($s['id']) ?>"><?= esc(($deptoMap[$s['departamento_id']] ?? '') . ' · ' . $s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Nombre</label>
                <input type="text" name="nombre" required>
            </div>
            <div>
                <label>Código</label>
                <input type="text" name="codigo" required placeholder="GENERAL">
            </div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit">Crear</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Documentos visibles (<?= count($docs) ?>)</h3>
    <?php if ($docs === []): ?>
        <p class="muted">No hay documentos visibles para tu membresía con estos filtros.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Departamento</th>
                <th>Ámbito</th>
                <th>Sección</th>
                <th>Tamaño</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($docs as $doc): ?>
            <tr>
                <td>
                    <strong><?= esc($doc['titulo']) ?></strong><br>
                    <span class="muted" style="font-size:.8rem"><?= esc($doc['nombre_archivo'] ?? '') ?></span>
                </td>
                <td><?= esc($doc['depto_nombre']) ?></td>
                <td>
                    <?php if ($doc['sucursal_nombre']): ?>
                        <span class="tag"><?= esc($doc['sucursal_nombre']) ?></span>
                    <?php else: ?>
                        <span class="tag">Nivel depto</span>
                    <?php endif; ?>
                </td>
                <td><?= esc($doc['seccion_nombre']) ?></td>
                <td class="muted"><?= number_format((int) ($doc['tamano_bytes'] ?? 0) / 1024, 1) ?> KB</td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
                    <?php if (! empty($doc['can_delete'])): ?>
                        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar documento?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm" type="submit" style="background:#fee2e2;color:#991b1b">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (! empty($canAuthorize)): ?>
            <tr>
                <td colspan="6" style="padding-top:0;border-bottom:1px solid #e8eef8">
                    <form method="post" action="<?= site_url('documentos/authorize') ?>" class="form-row" style="grid-template-columns:auto 1fr auto;margin:0 0 .5rem;align-items:center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="documento_id" value="<?= esc($doc['id']) ?>">
                        <span class="muted" style="font-size:.8rem">Autorizar externo:</span>
                        <select name="usuario_id" required>
                            <?php foreach ($externals as $ex): ?>
                                <option value="<?= esc($ex['id']) ?>">
                                    <?= esc($ex['nombre']) ?> (<?= esc(\App\Libraries\Roles::label($ex['rol'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-ghost btn-sm" type="submit">Autorizar</button>
                    </form>
                    <?php if (! empty($doc['autorizado_usuario_ids'])): ?>
                        <span class="muted" style="font-size:.75rem">Autorizados: <?= count($doc['autorizado_usuario_ids']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card section-card">
    <h3>ACL (resumen)</h3>
    <ul class="muted">
        <li><strong>Master</strong> — ve y sube todo en la empresa.</li>
        <li><strong>Jefe de Sucursal</strong> — ve su sucursal + docs a nivel de su departamento; sube solo en secciones de su sucursal.</li>
        <li><strong>Externos</strong> — solo documentos con autorización explícita.</li>
    </ul>
</div>

<?= $this->endSection() ?>
