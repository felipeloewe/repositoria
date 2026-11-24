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
// Perfiles (ACL Repositoria) no se muestra en Equity.
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
        'ingresos-renta' => 'Renta',
        'ingresos-honorarios' => 'Honorarios',
        'ingresos-inversiones' => 'Inversiones',
        'ingresos-otros' => 'Otros',
        'egresos' => 'Egresos',
        'egresos-hipotecarios' => 'Egresos · Hipotecarios',
        'egresos-circulantes' => 'Egresos · Circulantes',
        'egresos-otros' => 'Egresos · Otros',
        'egresos-indirecta' => 'Egresos · Deuda indirecta',
        'egresos-lineas' => 'Egresos · Líneas',
        default => '',
    };
};
?>
<?php if ($canEquity): ?>
    <?php foreach (EquityCatalogo::navGroups() as $grupo => $items): ?>
        <p class="nav-section"><?= esc($grupo) ?></p>
        <?php foreach ($items as $item): ?>
            <?php
                $sub = in_array($item['clave'], ['socios', 'gerentes', 'familiares', 'bienes-raices', 'vehiculos', 'sociedades', 'otros-activos', 'pasivos-inmuebles', 'pasivos-circulantes', 'otros-pasivos', 'pasivos-indirecta', 'pasivos-lineas', 'ingresos-renta', 'ingresos-honorarios', 'ingresos-inversiones', 'ingresos-otros', 'egresos-hipotecarios', 'egresos-circulantes', 'egresos-otros', 'egresos-indirecta', 'egresos-lineas'], true);
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

<?php
$isSuperadmin = ! empty($memb['es_admin_central']);
if ($canOrg || $canConfig || $isSuperadmin):
?>
    <p class="nav-section">Administración</p>
<?php endif; ?>
<?php if ($isSuperadmin): ?>
    <a href="<?= site_url('admin/empresas') ?>" class="<?= ($title === 'Admin central') ? 'active' : '' ?>" data-nav="admin-central"><span class="nav-label">Admin central · Empresas</span></a>
<?php endif; ?>
<?php if ($canOrg): ?>
    <a href="<?= site_url('organizacion') ?>" class="<?= $title === 'Organización' ? 'active' : '' ?>" data-nav="org"><span class="nav-label">Organización</span></a>
<?php endif; ?>
<?php if ($canConfig): ?>
    <a href="<?= site_url('equity/grupo') ?>" class="<?= ($title === 'Grupo Equity' || $equityNav === 'grupo') ? 'active' : '' ?>" data-nav="grupo"><span class="nav-label">Grupo / Usuarios</span></a>
    <a href="<?= site_url('equity/configuracion') ?>" class="<?= ($title === 'Configuración' || $equityNav === 'configuracion') ? 'active' : '' ?>" data-nav="configuracion"><span class="nav-label">Configuración</span></a>
<?php endif; ?>
<?php
$equityPersonas = [];
if (! empty($memb['empresa_id']) && ! empty($memb['usuario_id'])) {
    $equityPersonas = (new \App\Libraries\EquityOrg())->personasDeclarables((string) $memb['usuario_id'], (string) $memb['empresa_id']);
}
if (count($equityPersonas) > 1):
?>
    <p class="nav-section">Declarando</p>
    <a href="<?= site_url('login/persona') ?>" class="<?= $title === '¿A quién representas?' ? 'active' : '' ?>" data-nav="persona">
        <span class="nav-label">Cambiar persona</span>
    </a>
<?php endif; ?>
<a href="<?= site_url('logout') ?>" class="nav-exit" data-nav="salir"><span class="nav-label">Salir</span></a>
