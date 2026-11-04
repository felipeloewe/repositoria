<?php
$tab = $tab ?? 'departamentos';
?>
<nav class="socios-tabs deptos-tabs" aria-label="Secciones Departamentos">
    <a href="<?= site_url('departamentos') ?>" class="<?= $tab === 'departamentos' ? 'is-active' : '' ?>">Departamentos</a>
    <a href="<?= site_url('departamentos/actividades') ?>" class="<?= $tab === 'actividades' ? 'is-active' : '' ?>">Actividades</a>
    <a href="<?= site_url('departamentos/temas') ?>" class="<?= $tab === 'temas' ? 'is-active' : '' ?>">Temas de Interés</a>
</nav>
