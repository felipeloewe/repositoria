<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php use App\Libraries\DocumentLifecycle; ?>

<section class="banner">
    <h2>Documentos de socios</h2>
    <p>Repositorio exclusivo para socios / representantes legales de esta empresa. Visible solo entre membresías con atributo <strong>Socio</strong>.</p>
</section>

<?= $this->include('socios/_tabs', ['tab' => 'documentos']) ?>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="card section-card">
    <h3>Filtros</h3>
    <form method="get" action="<?= site_url('socios') ?>" class="form-row" style="grid-template-columns:1fr 1fr auto">
        <div>
            <label for="f_tipo">Tipo</label>
            <select id="f_tipo" name="tipo_id">
                <option value="">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= esc($t['id']) ?>" <?= ($filters['tipo_id'] ?? '') === $t['id'] ? 'selected' : '' ?>>
                        <?= esc($t['nombre']) ?> (<?= esc(DocumentLifecycle::labelModalidad($t['modalidad'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f_estado">Estado ciclo</label>
            <select id="f_estado" name="estado">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= esc($e) ?>" <?= ($filters['estado'] ?? '') === $e ? 'selected' : '' ?>>
                        <?= esc(DocumentLifecycle::labelEstado($e)) ?>
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
    <h3>Subir documento de socios</h3>
    <form method="post" action="<?= site_url('socios/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="up_titulo">Título</label>
        <input id="up_titulo" type="text" name="titulo" required>
        <label for="up_tipo">Tipo</label>
        <select id="up_tipo" name="tipo_id" required>
            <?php foreach ($tiposActivos as $t): ?>
                <option value="<?= esc($t['id']) ?>">
                    <?= esc($t['nombre']) ?> — <?= esc(DocumentLifecycle::labelModalidad($t['modalidad'])) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="up_asunto">Asunto (cabecera)</label>
        <input id="up_asunto" type="text" name="cabecera_asunto" placeholder="Opcional">
        <div class="form-row" style="grid-template-columns:1fr 1fr">
            <div>
                <label for="up_partes">Partes</label>
                <input id="up_partes" type="text" name="cabecera_partes">
            </div>
            <div>
                <label for="up_auth">Autorizantes</label>
                <input id="up_auth" type="text" name="cabecera_autorizantes">
            </div>
            <div>
                <label for="up_inicio">Inicio</label>
                <input id="up_inicio" type="date" name="cabecera_inicio">
            </div>
            <div>
                <label for="up_termino">Término</label>
                <input id="up_termino" type="date" name="cabecera_termino">
            </div>
        </div>
        <label for="up_file">Archivo (máx. 15 MB)</label>
        <input id="up_file" type="file" name="archivo" required style="margin-bottom:1rem">
        <button class="btn btn-primary" type="submit">Subir</button>
    </form>
</div>
<?php elseif (empty($tiposActivos)): ?>
<div class="alert alert-info">Necesitas al menos un <a href="<?= site_url('documentos/tipos') ?>">tipo de documento</a> activo (gestión Master) para subir archivos de socios.</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Documentos de socios (<?= count($docs) ?>)</h3>
    <?php if ($docs === []): ?>
        <p class="muted">Aún no hay documentos en el pool de socios.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Estado ciclo</th>
                <th>Versión / Término</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($docs as $doc): ?>
            <?php
                $tipo = $doc['tipo'] ?? null;
                $estado = DocumentLifecycle::normalizarEstado($doc['estado'] ?? null);
                $esOficial = DocumentLifecycle::isOficial($tipo);
            ?>
            <tr>
                <td>
                    <a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a><br>
                    <span class="muted" style="font-size:.8rem"><?= esc($doc['nombre_archivo'] ?? '') ?></span>
                    <?php if (! empty($doc['vencido'])): ?>
                        <span class="tag tag-vencido">Vencido</span>
                    <?php elseif (! empty($doc['por_vencer'])): ?>
                        <span class="tag tag-alerta">Por vencer</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($tipo): ?>
                        <?= esc($tipo['nombre']) ?><br>
                        <span class="tag <?= $esOficial ? 'tag-oficial' : 'tag-borrador-tipo' ?>">
                            <?= esc(DocumentLifecycle::labelModalidad($tipo['modalidad'])) ?>
                        </span>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($esOficial): ?>
                        <span class="tag <?= esc(DocumentLifecycle::cssEstado($estado)) ?>">
                            <?= esc(DocumentLifecycle::labelEstado($estado)) ?>
                        </span>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="muted">
                    v<?= (int) ($doc['version_actual'] ?? 1) ?>
                    <?php
                        $term = $doc['cabecera_termino'] ?? $doc['fecha_termino'] ?? ($doc['vence_at'] ?? null);
                    ?>
                    <?= $term ? '<br>' . esc($term) : '' ?>
                </td>
                <td style="white-space:nowrap">
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id']) ?>">Abrir</a>
                    <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
                    <?php if (! empty($doc['can_delete'])): ?>
                        <form method="post" action="<?= site_url('documentos/' . $doc['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar documento de socios?')">
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
