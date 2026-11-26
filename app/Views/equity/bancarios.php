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
</div>

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
        <a href="<?= esc(Bancarios::appUrl('gpay') ?? '#') ?>" target="_blank" rel="noopener noreferrer">G-Pay</a>.
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
                            <th>Últimos dígitos</th>
                            <th>Apps</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($nodo['cuentas'] as $c): ?>
                        <tr>
                            <td><?= esc(Bancarios::labelTipoCuenta($c['tipo'] ?? null)) ?></td>
                            <td><?= esc($c['moneda'] ?? '—') ?></td>
                            <td><?= esc($c['ultimos_digitos'] ? ('…' . $c['ultimos_digitos']) : '—') ?></td>
                            <td><?= $appsHtml($c) ?></td>
                            <td style="white-space:nowrap">
                                <?php if (! empty($canManage)): ?>
                                    <form method="post" action="<?= site_url($base . '/cuenta/' . $c['id'] . '/eliminar') ?>" style="display:inline"
                                          onsubmit="return confirm('¿Eliminar esta cuenta?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
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
                    <div>
                        <label>Últimos dígitos</label>
                        <input name="ultimos_digitos" type="text" inputmode="numeric" maxlength="8" placeholder="1234">
                    </div>
                    <div>
                        <?= $checkboxApp('en_google_wallet', 'google_wallet') ?>
                        <div style="margin-top:.35rem"><?= $checkboxApp('en_gpay', 'gpay') ?></div>
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
                        <tr>
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
                                    <form method="post" action="<?= site_url($base . '/tarjeta/' . $t['id'] . '/eliminar') ?>" style="display:inline"
                                          onsubmit="return confirm('¿Eliminar esta tarjeta?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-ghost btn-sm" type="submit">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
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
