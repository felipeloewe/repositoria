<?php
$tab = $tab ?? 'servicios';
?>
<nav class="socios-tabs recursos-tabs" aria-label="Secciones Recursos">
    <a href="<?= site_url('recursos') ?>" class="<?= $tab === 'servicios' ? 'is-active' : '' ?>">Servicios</a>
    <a href="<?= site_url('recursos/contratos') ?>" class="<?= $tab === 'contratos' ? 'is-active' : '' ?>">Contratos</a>
</nav>
