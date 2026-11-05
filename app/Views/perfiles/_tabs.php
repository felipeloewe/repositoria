<?php
$tab = $tab ?? 'perfiles';
?>
<nav class="socios-tabs" aria-label="Secciones Perfiles">
    <a href="<?= site_url('perfiles') ?>" class="<?= $tab === 'perfiles' ? 'is-active' : '' ?>">Perfiles</a>
    <a href="<?= site_url('perfiles/asignacion') ?>" class="<?= $tab === 'asignacion' ? 'is-active' : '' ?>">Asignación</a>
</nav>
