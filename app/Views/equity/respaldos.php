<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<section class="banner">
    <h2>Respaldos</h2>
    <p>Documentos de evidencia del estado de situación (liquidaciones, DAI, cartolas, tasaciones…).</p>
</section>

<div class="card section-card">
    <form method="get" action="<?= site_url('equity/respaldos') ?>" class="form-row" style="grid-template-columns:1fr auto">
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
    </form>
</div>

<div class="card section-card">
    <h3>Documentos (<?= count($respaldos) ?>)</h3>
    <?php if ($respaldos === []): ?>
        <p class="muted">Sin respaldos. Súbelos desde la ficha de cada persona.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Documento</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($respaldos as $r): ?>
                <?php
                    $p = $r['persona'] ?? null;
                    $doc = $r['documento'] ?? null;
                ?>
                <tr>
                    <td>
                        <?php if ($p): ?>
                            <a href="<?= site_url('equity/' . $p['id']) ?>"><?= esc(trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''))) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                            <a href="<?= site_url('documentos/' . $doc['id']) ?>"><strong><?= esc($doc['titulo']) ?></strong></a>
                        <?php else: ?>
                            <span class="muted">No disponible</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($r['tipo_respaldo'] ?? '—') ?></td>
                    <td class="muted"><?= esc($r['created_at'] ?? '') ?></td>
                    <td>
                        <?php if ($doc): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('documentos/' . $doc['id'] . '/download') ?>">Descargar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
