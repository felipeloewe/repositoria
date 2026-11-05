<?php
$tab = $tab ?? 'archivados';
$isMaster = \App\Libraries\Roles::isMaster($memb['rol'] ?? '');
$canArchivo = \App\Libraries\DocumentoSolicitudService::canValidateArchivo($memb ?? []);
$canBaja = \App\Libraries\DocumentoSolicitudService::canValidateBaja($memb ?? []);
$showColas = $canArchivo || $canBaja || ! $isMaster;
?>
<nav class="socios-tabs" aria-label="Ciclo archivo y baja">
    <?php if ($isMaster): ?>
        <a href="<?= site_url('documentos/archivados') ?>" class="<?= $tab === 'archivados' ? 'is-active' : '' ?>">Archivados (hechos)</a>
        <a href="<?= site_url('documentos/archivados/respaldo') ?>" class="<?= $tab === 'respaldo' ? 'is-active' : '' ?>">Respaldo ZIP</a>
    <?php endif; ?>
    <?php if ($showColas): ?>
        <a href="<?= site_url('documentos/archivado') ?>" class="<?= $tab === 'archivado' ? 'is-active' : '' ?>">Archivado</a>
        <a href="<?= site_url('documentos/en-baja') ?>" class="<?= $tab === 'baja' ? 'is-active' : '' ?>">En Baja</a>
    <?php endif; ?>
</nav>
