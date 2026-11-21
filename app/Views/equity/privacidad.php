<?= $this->extend('layouts/shell') ?>
<?= $this->section('content') ?>

<section class="banner">
    <h2>Privacidad · capa de identidad</h2>
    <p>
        Vault lógico: PII en usuarios / empresas / personas. El patrimonio opera por IDs.
        Al dar de baja se desactiva; al anonimizar se elimina el PII y no se puede rearmar desde la base operativa.
    </p>
    <p style="margin-top:.75rem">
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/grupo') ?>">← Grupo / Usuarios</a>
    </p>
</section>

<div class="card section-card">
    <h3>Inventario PII</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Capa</th>
                <th>Tabla / origen</th>
                <th>Campos</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($inventory ?? []) as $row): ?>
            <tr>
                <td><?= esc(($row['layer'] ?? '') === 'identity' ? 'Identidad' : 'Colateral') ?></td>
                <td><code><?= esc((string) ($row['table'] ?? '')) ?></code></td>
                <td class="muted" style="font-size:.85rem"><?= esc(implode(', ', $row['fields'] ?? [])) ?></td>
                <td><?= esc((string) ($row['note'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card section-card">
    <h3>Política de baja (fase actual)</h3>
    <ol>
        <li><strong>Baja</strong>: desactiva persona/usuario; bloquea login; conserva IDs y patrimonio.</li>
        <li><strong>Anonimizar (scrub)</strong>: borra nombre/RUT/correo/dirección personal de la capa de identidad.</li>
        <li><strong>Colateral</strong> (contrapartes, direcciones de inmuebles, PDFs, mails): aún no se limpia automáticamente; está inventariado para la siguiente fase.</li>
        <li>Backups/exports antiguos pueden seguir teniendo el mapeo ID↔persona: hay que tratarlos fuera de la app.</li>
    </ol>
</div>

<?= $this->endSection() ?>
