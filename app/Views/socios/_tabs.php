<?php
$tab = $tab ?? 'documentos';
?>
<nav class="socios-tabs" aria-label="Secciones Socios">
    <a href="<?= site_url('socios') ?>" class="<?= $tab === 'documentos' ? 'is-active' : '' ?>">Documentos</a>
    <a href="<?= site_url('socios/formularios') ?>" class="<?= $tab === 'formularios' ? 'is-active' : '' ?>">Formularios</a>
</nav>
