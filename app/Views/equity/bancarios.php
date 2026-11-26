<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityBancarios as Bancarios;

$nombre = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '') . ' ' . ($persona['apellido_materno'] ?? ''));
$pid = (string) ($persona['id'] ?? '');
$base = 'equity/' . $pid . '/bancarios';
$fmtMoney = static function ($v, string $suf = ''): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return number_format((float) $v, 0, ',', '.') . ($suf !== '' ? ' ' . $suf : '');
};
$appLink = static function (string $key, string $extraClass = ''): string {
    $url = Bancarios::appUrl($key);
    $lab = Bancarios::appLabel($key);
    if ($url === null) {
        return esc($lab);
    }
    $cls = trim('app-ref-link ' . $extraClass);

    return '<a class="' . esc($cls) . '" href="' . esc($url) . '" target="_blank" rel="noopener noreferrer">' . esc($lab) . '</a>';
};
$appsHtml = static function (array $row) use ($appLink): string {
    $bits = [];
    if (! empty($row['en_google_wallet'])) {
        $bits[] = $appLink('google_wallet');
    }
    if (! empty($row['en_gpay'])) {
        $bits[] = $appLink('gpay');
    }
    if (! empty($row['en_link'])) {
        $bits[] = $appLink('link');
    }

    return $bits === [] ? '—' : implode(' · ', $bits);
};
$checkboxApp = static function (string $name, string $key): string {
    $url = Bancarios::appUrl($key);
    $lab = Bancarios::appLabel($key);
    $link = $url !== null
        ? ' <a href="' . esc($url) . '" target="_blank" rel="noopener noreferrer" title="Abrir ' . esc($lab) . '">(abrir)</a>'
        : '';

    return '<label class="checkbox-inline"><input type="checkbox" name="' . esc($name) . '" value="1"> '
        . esc($lab) . $link . '</label>';
};
?>

<section class="banner">
    <h2>Antecedentes bancarios</h2>
    <p><?= esc($nombre !== '' ? $nombre : 'Persona') ?>
        <?php if (! empty($persona['rut'])): ?> · RUT <?= esc($persona['rut']) ?><?php endif; ?>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<div class="admin-subnav">
    <a href="<?= site_url('equity/' . $pid) ?>">← Ficha personal</a>
    <?php if (! empty($canManage)): ?>
        <a href="<?= site_url('equity/' . $pid . '/editar') ?>">Editar personales</a>
    <?php endif; ?>
    <a href="<?= site_url('equity/vault-pin?return=' . rawurlencode(site_url($base))) ?>">PIN de bóveda</a>
</div>

<?php if (! empty($secretosOk) && ! empty($canManage)): ?>
<div class="card section-card">
    <h3 style="margin-top:0">Bóveda de secretos</h3>
    <?php if (empty($vaultPinOk)): ?>
        <p class="muted">Para guardar claves de internet o PIN de cajero, configura un PIN de bóveda en tu perfil.</p>
        <a class="btn btn-primary btn-sm" href="<?= site_url('equity/vault-pin?return=' . rawurlencode(site_url($base))) ?>">Configurar PIN</a>
    <?php elseif (! empty($vaultOpen)): ?>
        <p class="muted" style="margin-top:0">Bóveda <strong>desbloqueada</strong> (se vuelve a bloquear a los 15 min de inactividad). Los secretos se muestran en claro.</p>
        <form method="post" action="<?= site_url($base . '/bloquear') ?>" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">Bloquear ahora</button>
        </form>
    <?php else: ?>
        <p class="muted" style="margin-top:0">Bóveda bloqueada. Ingresa tu PIN para ver o agregar claves / PINes de cajero.</p>
        <form method="post" action="<?= site_url($base . '/desbloquear') ?>" class="form-row" style="grid-template-columns:160px auto;align-items:end;gap:.75rem;max-width:360px">
            <?= csrf_field() ?>
            <div>
                <label for="vault_pin">PIN</label>
                <input id="vault_pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="12" required autocomplete="off">
            </div>
            <div>
                <button class="btn btn-primary btn-sm" type="submit">Desbloquear</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($tablasOk)): ?>
    <div class="alert alert-error">Falta la migración de antecedentes bancarios (000040).</div>
<?php endif; ?>

<?php if (! empty($canManage) && ! empty($tablasOk)): ?>
<div class="card section-card">
    <h3>Agregar banco</h3>
    <p class="muted" style="margin-top:0">
        Por cada banco podrás registrar cuentas corrientes y tarjetas de crédito.
        Si están referenciadas a una app:
        <a href="<?= esc(Bancarios::appUrl('google_wallet') ?? '#') ?>" target="_blank" rel="noopener noreferrer">Google Wallet</a>
        ·
        <a href="<?= esc(Bancarios::appUrl('gpay') ?? '#') ?>" target="_blank" rel="noopener noreferrer">G-Pay</a>
        ·
        <a href="<?= esc(Bancarios::appUrl('link') ?? '#') ?>" target="_blank" rel="noopener noreferrer">LINK</a> (pasarela).
    </p>
    <form method="post" action="<?= site_url($base . '/banco') ?>">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:2fr 2fr auto;align-items:end;gap:1rem">
            <div>
                <label for="banco_nombre">Banco *</label>
                <input id="banco_nombre" name="banco_nombre" type="text" required maxlength="160" placeholder="Ej. Banco de Chile">
            </div>
            <div>
                <label for="banco_notas">Notas</label>
                <input id="banco_notas" name="notas" type="text" maxlength="500">
            </div>
            <div>
                <button class="btn btn-primary" type="submit">Agregar</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (($arbol ?? []) === []): ?>
    <div class="card section-card">
        <p class="muted" style="margin:0">Aún no hay bancos registrados para esta persona.</p>
    </div>
<?php endif; ?>

<?php foreach (($arbol ?? []) as $nodo): ?>
    <?php
        $b = $nodo['banco'];
        $bid = (string) ($b['id'] ?? '');
    ?>
    <div class="card section-card" id="banco-<?= esc($bid) ?>">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start">
            <div>
                <h3 style="margin:0"><?= esc($b['banco_nombre'] ?? 'Banco') ?></h3>
                <?php if (! empty($b['notas'])): ?>
                    <p class="muted" style="margin:.35rem 0 0"><?= esc($b['notas']) ?></p>
                <?php endif; ?>
            </div>
            <?php if (! empty($canManage) && ! empty($tablasOk)): ?>
                <form method="post" action="<?= site_url($base . '/banco/' . $bid . '/eliminar') ?>"
                      onsubmit="return confirm('¿Eliminar este banco y todas sus cuentas/tarjetas?');">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit">Eliminar banco</button>
                </form>
            <?php endif; ?>
        </div>

        <h4 style="margin:1.25rem 0 .5rem">Cuentas corrientes</h4>
        <?php if (($nodo['cuentas'] ?? []) === []): ?>
            <p class="muted">Sin cuentas.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Moneda</th>
                            <th>N° cuenta</th>
                            <th>Apps</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nodo['cuentas'] as $c): ?>
                        <?php $cid = (string) ($c['id'] ?? ''); ?>
                        <tr id="cuenta-<?= esc($cid) ?>">
                            <td><?= esc(Bancarios::labelTipoCuenta($c['tipo'] ?? null)) ?></td>
                            <td><?= esc($c['moneda'] ?? '—') ?></td>
                            <td><?php
                                $nro = (string) ($c['numero_cuenta'] ?? $c['ultimos_digitos'] ?? '');
                                echo $nro !== '' ? esc($nro) : '—';
                            ?></td>
                            <td><?= $appsHtml($c) ?></td>
                            <td style="white-space:nowrap">
                                <?php if (! empty($canManage)): ?>
                                    <form method="post" action="<?= site_url($base . '/cuenta/' . $cid . '/eliminar') ?>" style="display:inline"
                                          onsubmit="return confirm('¿Eliminar esta cuenta?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (! empty($secretosOk)): ?>
                        <tr>
                            <td colspan="5" style="background:transparent;border-top:0;padding-top:0">
                                <div style="margin:.25rem 0 1rem;padding:.75rem 1rem;border-left:3px solid var(--border,#ccc)">
                                    <strong>Claves de internet</strong> <span class="muted">(opcional)</span>
                                    <?php $claves = $c['claves'] ?? []; ?>
                                    <?php if ($claves === []): ?>
                                        <p class="muted" style="margin:.35rem 0">Sin bitácora aún.</p>
                                    <?php else: ?>
                                        <table class="table" style="margin:.5rem 0">
                                            <thead>
                                                <tr>
                                                    <th>Inicio</th>
                                                    <th>Vigencia hasta</th>
                                                    <th>Estado</th>
                                                    <th>Clave</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($claves as $k): ?>
                                                <tr>
                                                    <td><?= esc($k['fecha_inicio'] ?? '—') ?></td>
                                                    <td><?= esc($k['vigencia_hasta'] ?? '—') ?></td>
                                                    <td><?= esc(($k['estado_efectivo'] ?? $k['estado'] ?? '') === 'caducada' ? 'Caducada' : 'Vigente') ?></td>
                                                    <td style="font-family:ui-monospace,monospace">
                                                        <?php if (! empty($vaultOpen) && array_key_exists('secreto_visible', $k) && $k['secreto_visible'] !== null): ?>
                                                            <?= esc((string) $k['secreto_visible']) ?>
                                                        <?php else: ?>
                                                            <span class="muted">••••••••</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (! empty($canManage)): ?>
                                                            <form method="post" action="<?= site_url($base . '/clave/' . $k['id'] . '/eliminar') ?>" style="display:inline"
                                                                  onsubmit="return confirm('¿Eliminar esta clave?');">
                                                                <?= csrf_field() ?>
                                                                <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                    <?php if (! empty($canManage) && ! empty($vaultOpen)): ?>
                                        <form method="post" action="<?= site_url($base . '/cuenta/' . $cid . '/clave') ?>" autocomplete="off">
                                            <?= csrf_field() ?>
                                            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));align-items:end;gap:.5rem">
                                                <div>
                                                    <label>Fecha cambio</label>
                                                    <input type="date" name="fecha_inicio" value="<?= esc(date('Y-m-d')) ?>" required>
                                                </div>
                                                <div>
                                                    <label>Vigencia hasta</label>
                                                    <input type="date" name="vigencia_hasta">
                                                </div>
                                                <div>
                                                    <label>Estado</label>
                                                    <select name="estado">
                                                        <option value="vigente" selected>Vigente</option>
                                                        <option value="caducada">Caducada</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label>Clave</label>
                                                    <input type="password" name="clave" required autocomplete="off">
                                                </div>
                                                <div>
                                                    <label class="checkbox-inline"><input type="checkbox" name="caducar_anteriores" value="1" checked> Caducar vigentes previas</label>
                                                    <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.35rem">Agregar clave</button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php elseif (! empty($canManage) && empty($vaultOpen)): ?>
                                        <p class="muted" style="margin:.35rem 0 0">Desbloquea la bóveda para agregar claves.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (! empty($canManage) && ! empty($tablasOk)): ?>
            <form method="post" action="<?= site_url($base . '/banco/' . $bid . '/cuenta') ?>" style="margin-top:1rem">
                <?= csrf_field() ?>
                <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end;gap:.75rem">
                    <div>
                        <label>Tipo</label>
                        <select name="tipo">
                            <?php foreach ($tiposCuenta as $k => $lab): ?>
                                <option value="<?= esc($k) ?>" <?= $k === 'corriente' ? 'selected' : '' ?>><?= esc($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Moneda</label>
                        <select name="moneda">
                            <?php foreach ($monedas as $m): ?>
                                <option value="<?= esc($m) ?>" <?= $m === 'CLP' ? 'selected' : '' ?>><?= esc($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="grid-column:span 2">
                        <label>Número de cuenta</label>
                        <input name="numero_cuenta" type="text" inputmode="numeric" maxlength="64" placeholder="Ej. 00-123-45678901" autocomplete="off">
                    </div>
                    <div>
                        <?= $checkboxApp('en_google_wallet', 'google_wallet') ?>
                        <div style="margin-top:.35rem"><?= $checkboxApp('en_gpay', 'gpay') ?></div>
                        <div style="margin-top:.35rem"><?= $checkboxApp('en_link', 'link') ?></div>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm" type="submit">Agregar cuenta</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <h4 style="margin:1.5rem 0 .5rem">Tarjetas de crédito</h4>
        <?php if (($nodo['tarjetas'] ?? []) === []): ?>
            <p class="muted">Sin tarjetas.</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Producto</th>
                            <th>Monto $</th>
                            <th>Monto US</th>
                            <th>Vencimiento</th>
                            <th>Últimos 4</th>
                            <th>Apps</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nodo['tarjetas'] as $t): ?>
                        <?php $tid = (string) ($t['id'] ?? ''); ?>
                        <tr id="tarjeta-<?= esc($tid) ?>">
                            <td><?= esc(Bancarios::labelMarca($t['marca'] ?? null)) ?></td>
                            <td><?= esc($t['tipo_producto'] ?? '—') ?></td>
                            <td><?= esc($fmtMoney($t['monto_aprobado_clp'] ?? null, '$')) ?></td>
                            <td><?= Bancarios::marcaSoportaUsd((string) ($t['marca'] ?? ''))
                                ? esc($fmtMoney($t['monto_aprobado_usd'] ?? null, 'US'))
                                : '—' ?></td>
                            <td><?= esc($t['fecha_vencimiento'] ?? '—') ?></td>
                            <td><?= esc($t['ultimos_4'] ? ('…' . $t['ultimos_4']) : '—') ?></td>
                            <td><?= $appsHtml($t) ?></td>
                            <td style="white-space:nowrap">
                                <?php if (! empty($canManage)): ?>
                                    <form method="post" action="<?= site_url($base . '/tarjeta/' . $tid . '/eliminar') ?>" style="display:inline"
                                          onsubmit="return confirm('¿Eliminar esta tarjeta?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (! empty($secretosOk)): ?>
                        <tr>
                            <td colspan="8" style="background:transparent;border-top:0;padding-top:0">
                                <div style="margin:.25rem 0 1rem;padding:.75rem 1rem;border-left:3px solid var(--border,#ccc)">
                                    <strong>PIN de cajero</strong> <span class="muted">(opcional, 4 dígitos)</span>
                                    <?php $pines = $t['pines'] ?? []; ?>
                                    <?php if ($pines === []): ?>
                                        <p class="muted" style="margin:.35rem 0">Sin bitácora aún.</p>
                                    <?php else: ?>
                                        <table class="table" style="margin:.5rem 0">
                                            <thead>
                                                <tr>
                                                    <th>Inicio</th>
                                                    <th>Vigencia hasta</th>
                                                    <th>Estado</th>
                                                    <th>PIN</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($pines as $p): ?>
                                                <tr>
                                                    <td><?= esc($p['fecha_inicio'] ?? '—') ?></td>
                                                    <td><?= esc($p['vigencia_hasta'] ?? '—') ?></td>
                                                    <td><?= esc(($p['estado_efectivo'] ?? $p['estado'] ?? '') === 'caducada' ? 'Caducada' : 'Vigente') ?></td>
                                                    <td style="font-family:ui-monospace,monospace">
                                                        <?php if (! empty($vaultOpen) && array_key_exists('secreto_visible', $p) && $p['secreto_visible'] !== null): ?>
                                                            <?= esc((string) $p['secreto_visible']) ?>
                                                        <?php else: ?>
                                                            <span class="muted">••••</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (! empty($canManage)): ?>
                                                            <form method="post" action="<?= site_url($base . '/pin/' . $p['id'] . '/eliminar') ?>" style="display:inline"
                                                                  onsubmit="return confirm('¿Eliminar este PIN?');">
                                                                <?= csrf_field() ?>
                                                                <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                    <?php if (! empty($canManage) && ! empty($vaultOpen)): ?>
                                        <form method="post" action="<?= site_url($base . '/tarjeta/' . $tid . '/pin') ?>" autocomplete="off">
                                            <?= csrf_field() ?>
                                            <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));align-items:end;gap:.5rem">
                                                <div>
                                                    <label>Fecha cambio</label>
                                                    <input type="date" name="fecha_inicio" value="<?= esc(date('Y-m-d')) ?>" required>
                                                </div>
                                                <div>
                                                    <label>Vigencia hasta</label>
                                                    <input type="date" name="vigencia_hasta">
                                                </div>
                                                <div>
                                                    <label>Estado</label>
                                                    <select name="estado">
                                                        <option value="vigente" selected>Vigente</option>
                                                        <option value="caducada">Caducada</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label>PIN (4 dígitos)</label>
                                                    <input type="password" name="pin_cajero" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autocomplete="off">
                                                </div>
                                                <div>
                                                    <label class="checkbox-inline"><input type="checkbox" name="caducar_anteriores" value="1" checked> Caducar vigentes previos</label>
                                                    <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.35rem">Agregar PIN</button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php elseif (! empty($canManage) && empty($vaultOpen)): ?>
                                        <p class="muted" style="margin:.35rem 0 0">Desbloquea la bóveda para agregar PINes.</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (! empty($canManage) && ! empty($tablasOk)): ?>
            <form method="post" action="<?= site_url($base . '/banco/' . $bid . '/tarjeta') ?>" class="form-tarjeta" style="margin-top:1rem" data-banco="<?= esc($bid) ?>">
                <?= csrf_field() ?>
                <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end;gap:.75rem">
                    <div>
                        <label>Tipo *</label>
                        <select name="marca" class="tarjeta-marca" required>
                            <option value="">—</option>
                            <?php foreach ($marcas as $k => $lab): ?>
                                <option value="<?= esc($k) ?>"><?= esc($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Producto</label>
                        <input name="tipo_producto" type="text" maxlength="64" placeholder="Classic, Gold…">
                    </div>
                    <div>
                        <label>Monto aprobado $</label>
                        <input name="monto_aprobado_clp" type="text" inputmode="decimal" placeholder="1.500.000">
                    </div>
                    <div class="campo-usd" style="display:none">
                        <label>Monto aprobado US</label>
                        <input name="monto_aprobado_usd" type="text" inputmode="decimal" placeholder="2.000">
                    </div>
                    <div>
                        <label>Vencimiento</label>
                        <input name="fecha_vencimiento" type="text" maxlength="7" placeholder="MM/AAAA">
                    </div>
                    <div>
                        <label>Últimos 4</label>
                        <input name="ultimos_4" type="text" inputmode="numeric" maxlength="4" placeholder="1234">
                    </div>
                    <div>
                        <?= $checkboxApp('en_google_wallet', 'google_wallet') ?>
                        <div style="margin-top:.35rem"><?= $checkboxApp('en_gpay', 'gpay') ?></div>
                        <div style="margin-top:.35rem"><?= $checkboxApp('en_link', 'link') ?></div>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm" type="submit">Agregar tarjeta</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
(function () {
  function syncUsd(form) {
    var sel = form.querySelector('.tarjeta-marca');
    var usd = form.querySelector('.campo-usd');
    if (!sel || !usd) return;
    var v = (sel.value || '').toLowerCase();
    usd.style.display = (v === 'visa' || v === 'mastercard') ? '' : 'none';
    if (usd.style.display === 'none') {
      var inp = usd.querySelector('input');
      if (inp) inp.value = '';
    }
  }
  document.querySelectorAll('form.form-tarjeta').forEach(function (form) {
    var sel = form.querySelector('.tarjeta-marca');
    if (sel) sel.addEventListener('change', function () { syncUsd(form); });
    syncUsd(form);
  });
})();
</script>

<?= $this->endSection() ?>
