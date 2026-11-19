<?php
/** @var array<string, mixed> $memb */
use App\Libraries\EquityAcl;
use App\Libraries\EquityCatalogo;
use App\Libraries\PerfilAcl;

$nav = static fn (string $key): bool => PerfilAcl::canMenu($memb, $key);
$title = $title ?? '';
$equityNav = $equityNav ?? '';
$canOrg = $nav('menu.organizacion');
// En host Equity la IA patrimonial es el producto: se muestra siempre.
// menu.equity / EquityAcl siguen gobernando el acceso a las rutas.
$canEquity = true;
$canPerfiles = $nav('menu.perfiles');
$canConfig = EquityAcl::canManage($memb);

$isActive = static function (string $clave) use ($title, $equityNav): bool {
    if ($clave === 'inicio') {
        return $title === 'Inicio';
    }
    if ($clave === 'personas') {
        return $title === 'Equity' && $equityNav === '';
    }
    if ($clave === 'socios') {
        return $equityNav === 'socio';
    }
    if ($clave === 'gerentes') {
        return $equityNav === 'gerente';
    }
    if ($clave === 'familiares') {
        return $equityNav === 'familiar';
    }

    return $equityNav === $clave || $title === match ($clave) {
        'resumen' => 'Resumen',
        'ingresos' => 'Ingresos',
        'respaldos' => 'Respaldos',
        'bienes-raices' => 'Bienes raíces',
        'vehiculos' => 'Vehículos',
        'sociedades' => 'Sociedades',
        'otros-activos' => 'Otros activos',
        'pasivos-inmuebles' => 'Hipotecarios',
        'pasivos-circulantes' => 'Pasivos circulantes',
        'otros-pasivos' => 'Otros pasivos',
        'pasivos-indirecta' => 'Deuda indirecta',
        'pasivos-lineas' => 'Líneas disponibles',
        'situacion-financiera' => 'Situación financiera',
        default => '',
    };
};
?>
<?php if ($canEquity): ?>
    <?php foreach (EquityCatalogo::navGroups() as $grupo => $items): ?>
        <p class="nav-section"><?= esc($grupo) ?></p>
        <?php foreach ($items as $item): ?>
            <?php
                $sub = in_array($item['clave'], ['socios', 'gerentes', 'familiares', 'bienes-raices', 'vehiculos', 'sociedades', 'otros-activos', 'pasivos-inmuebles', 'pasivos-circulantes', 'otros-pasivos', 'pasivos-indirecta', 'pasivos-lineas'], true);
            ?>
            <a href="<?= site_url(ltrim($item['path'], '/')) ?>"
               class="<?= $sub ? 'nav-sub ' : '' ?><?= $isActive($item['clave']) ? 'active' : '' ?>"
               data-nav="<?= esc($item['clave']) ?>">
                <span class="nav-label"><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php else: ?>
    <p class="nav-section">Principal</p>
    <a href="<?= site_url('dashboard') ?>" class="<?= $title === 'Inicio' ? 'active' : '' ?>" data-nav="inicio"><span class="nav-label">Inicio</span></a>
<?php endif; ?>

<?php if ($canOrg || $canPerfiles || $canConfig): ?>
    <p class="nav-section">Administración</p>
<?php endif; ?>
<?php if ($canOrg): ?>
    <a href="<?= site_url('organizacion') ?>" class="<?= $title === 'Organización' ? 'active' : '' ?>" data-nav="org"><span class="nav-label">Organización</span></a>
<?php endif; ?>
<?php if ($canConfig): ?>
    <a href="<?= site_url('equity/configuracion') ?>" class="<?= ($title === 'Configuración' || $equityNav === 'configuracion') ? 'active' : '' ?>" data-nav="configuracion"><span class="nav-label">Configuración</span></a>
<?php endif; ?>
<?php if ($canPerfiles): ?>
    <a href="<?= site_url('perfiles') ?>" class="<?= $title === 'Perfiles' ? 'active' : '' ?>" data-nav="perfiles"><span class="nav-label">Perfiles</span></a>
<?php endif; ?>
<a href="<?= site_url('logout') ?>" class="nav-exit" data-nav="salir"><span class="nav-label">Salir</span></a>
