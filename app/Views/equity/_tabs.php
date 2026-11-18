<?php
$tab = $tab ?? 'personas';
?>
<nav class="socios-tabs" aria-label="Secciones Equity">
    <a href="<?= site_url('equity') ?>" class="<?= $tab === 'personas' ? 'is-active' : '' ?>">Grupo Familiar</a>
</nav>
