<?php
$tab = $tab ?? 'empresa';
?>
<nav class="socios-tabs" aria-label="Organización">
    <a href="<?= site_url('organizacion') ?>" class="<?= $tab === 'empresa' ? 'is-active' : '' ?>">Mi Empresa</a>
    <a href="<?= site_url('organizacion/log') ?>" class="<?= $tab === 'log' ? 'is-active' : '' ?>">Log</a>
</nav>
