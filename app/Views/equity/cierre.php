<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityCierre;
use App\Libraries\EquityUf;

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$cerrado = is_array($periodoActual) && ($periodoActual['estado'] ?? '') === 'cerrado';
$ufOk = is_array($ufRow) && ! empty($ufRow['valor_clp']);
$validacion = is_array($validacion ?? null) ? $validacion : ['ok' => true, 'bloqueantes' => 0, 'avisos' => 0, 'hallazgos' => []];
$hallazgos = $validacion['hallazgos'] ?? [];
$nBloq = (int) ($validacion['bloqueantes'] ?? 0);
$nAvisos = (int) ($validacion['avisos'] ?? 0);
$tieneHallazgos = $nBloq > 0 || $nAvisos > 0;
?>

<section class="banner">
    <h2>Cierre mensual</h2>
    <p>
        Valida con V.B. que la información del mes está completa y correcta.
        Al cerrar se congelan los reportes vigentes en Historial y el desarrollo de créditos (cuota, amortización e intereses) del periodo.
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (empty($tablasOk)): ?>
    <div class="alert alert-error">
        Falta la migración de cierre mensual (tablas <code>equity_periodos_cierre</code> / historial).
        Despliega la migración 000037 en producción.
    </div>
<?php endif; ?>

<div class="card section-card">
    <h3>Periodo a cerrar</h3>
    <form method="get" action="<?= site_url('equity/cierre') ?>" style="margin-bottom:1rem">
        <div class="form-row" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end">
            <div>
                <label for="mes">Mes</label>
                <select id="mes" name="mes">
                    <?php foreach ($meses as $n => $lab): ?>
                        <option value="<?= $n ?>" <?= (int) $mes === $n ? 'selected' : '' ?>><?= $n ?>. <?= esc($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="anio">Año</label>
                <input id="anio" name="anio" type="number" min="2000" max="2100" value="<?= (int) $anio ?>">
            </div>
            <div>
                <button class="btn btn-ghost" type="submit">Ver periodo</button>
            </div>
        </div>
    </form>

    <p style="margin:0 0 .5rem">
        <strong><?= esc($meses[(int) $mes] ?? $mes) ?> <?= (int) $anio ?></strong>
        <span class="muted"> · cierre contable <?= esc($fechaCierre) ?></span>
        <?php if ($ufOk): ?>
            <span class="muted"> · UF <?= esc(EquityUf::fmtPesos((float) $ufRow['valor_clp'], 2)) ?></span>
        <?php else: ?>
            <span class="muted"> · sin UF de cierre (cárgala en Configuración)</span>
        <?php endif; ?>
    </p>

    <?php if ($cerrado): ?>
        <p style="margin:.75rem 0">
            <span class="badge" style="background:#0f6b4c;color:#fff;padding:.2rem .55rem;border-radius:6px;font-size:.85rem">Cerrado · V.B.</span>
            <?php if (! empty($periodoActual['vb_at'])): ?>
                <span class="muted"> · <?= esc($periodoActual['vb_at']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (! empty($periodoActual['vb_nota'])): ?>
            <p class="muted" style="margin:.25rem 0 1rem"><?= esc($periodoActual['vb_nota']) ?></p>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= site_url('equity/cierre/periodo/' . $periodoActual['id']) ?>">Ver historial del periodo</a>
    <?php elseif (! empty($canDeclare) && ! empty($tablasOk)): ?>
        <form method="post" action="<?= site_url('equity/cierre/cerrar') ?>" id="form-cierre-vb">
            <?= csrf_field() ?>
            <input type="hidden" name="anio" value="<?= (int) $anio ?>">
            <input type="hidden" name="mes" value="<?= (int) $mes ?>">
            <input type="hidden" name="forzar" id="cierre-forzar" value="0">
            <div class="form-row" style="grid-template-columns:1fr;margin-top:1rem">
                <div>
                    <label for="vb_nota">Nota de V.B.</label>
                    <input id="vb_nota" name="vb_nota" type="text" maxlength="500"
                           value="V.B. de cierre: información completa, validada y valores correctos.">
                </div>
                <div>
                    <label for="notas">Notas internas (opcional)</label>
                    <input id="notas" name="notas" type="text" maxlength="500" placeholder="Observaciones del periodo">
                </div>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-primary" type="submit" id="btn-cerrar-vb" <?= $ufOk ? '' : 'disabled' ?>>Cerrar con V.B. y generar reportes</button>
                <button class="btn btn-ghost" type="button" id="btn-revalidar">Revalidar fechas</button>
                <a class="btn btn-ghost" href="<?= site_url('equity/reporte/balance') ?>">Revisar Balance</a>
                <a class="btn btn-ghost" href="<?= site_url('equity/configuracion') ?>">UF / Configuración</a>
            </div>
            <?php if (! $ufOk): ?>
                <p class="muted" style="margin-top:.75rem">Necesitas la UF de cierre de este mes antes de cerrar.</p>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p class="muted">Sin permiso para cerrar, o falta migración.</p>
    <?php endif; ?>
</div>

<?php if (! $cerrado): ?>
<div class="card section-card" id="panel-validacion-cierre">
    <h3>Validación de fechas / actualización</h3>
    <p class="muted" style="margin-top:0">
        Antes del V.B. se revisa que los ítems con fechas (dividendo, cartola AFP, pólizas, contratos, bitácora de valores)
        estén al día respecto a <strong><?= esc($fechaCierre) ?></strong>.
    </p>
    <?php if (! $tieneHallazgos): ?>
        <div class="alert alert-ok" id="validacion-ok">Todo al día para el periodo seleccionado.</div>
    <?php else: ?>
        <div class="<?= $nBloq > 0 ? 'alert alert-error' : 'alert alert-ok' ?>" id="validacion-resumen">
            <?= $nBloq > 0 ? $nBloq . ' problema(s) bloqueante(s)' : 'Sin bloqueantes' ?>
            <?= $nAvisos > 0 ? ' · ' . $nAvisos . ' aviso(s)' : '' ?>.
            Revisa el detalle abajo o al intentar cerrar.
        </div>
        <table class="table" id="tabla-hallazgos">
            <thead>
                <tr>
                    <th>Severidad</th>
                    <th>Sección</th>
                    <th>Problema</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hallazgos as $h): ?>
                <tr>
                    <td>
                        <?php if (($h['severidad'] ?? '') === 'error'): ?>
                            <span style="color:#b91c1c;font-weight:700">Error</span>
                        <?php else: ?>
                            <span style="color:#a16207;font-weight:700">Aviso</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($h['seccion'] ?? '') ?></td>
                    <td>
                        <strong><?= esc($h['titulo'] ?? '') ?></strong>
                        <div class="muted" style="font-size:.9rem"><?= esc($h['detalle'] ?? '') ?></div>
                    </td>
                    <td>
                        <?php if (! empty($h['href'])): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url(ltrim((string) $h['href'], '/')) ?>">Ir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Historial de periodos</h3>
    <?php if ($periodos === []): ?>
        <p class="muted">Aún no hay periodos cerrados.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Estado</th>
                    <th>UF cierre</th>
                    <th>V.B.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($periodos as $p): ?>
                <tr>
                    <td><?= esc(EquityCierre::labelMes((int) $p['mes'])) ?> <?= (int) $p['anio'] ?></td>
                    <td><?= esc($p['estado'] ?? '—') ?></td>
                    <td><?= ! empty($p['uf_valor_clp']) ? esc(EquityUf::fmtPesos((float) $p['uf_valor_clp'], 2)) : '—' ?></td>
                    <td><?= ! empty($p['vb_at']) ? esc($p['vb_at']) : '—' ?></td>
                    <td>
                        <?php if (($p['estado'] ?? '') === 'cerrado'): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/cierre/periodo/' . $p['id']) ?>">Abrir</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<dialog id="dlg-cierre-validacion" style="max-width:640px;width:calc(100% - 2rem);border:1px solid #d5e0f0;border-radius:12px;padding:0;box-shadow:0 18px 50px rgba(15,23,42,.18)">
    <form method="dialog" style="margin:0">
        <div style="padding:1.1rem 1.25rem;border-bottom:1px solid #e8eef8">
            <h3 style="margin:0;font-size:1.1rem" id="dlg-cierre-titulo">Revisa antes de cerrar</h3>
            <p class="muted" style="margin:.4rem 0 0" id="dlg-cierre-sub"></p>
        </div>
        <div style="padding:1rem 1.25rem;max-height:50vh;overflow:auto" id="dlg-cierre-lista"></div>
        <div style="padding:1rem 1.25rem;border-top:1px solid #e8eef8;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end">
            <button class="btn btn-ghost" value="cancel" id="dlg-cierre-cancel">Revisar datos</button>
            <button class="btn btn-primary" value="force" id="dlg-cierre-force" hidden>Cerrar de todas formas</button>
            <button class="btn btn-primary" value="ok" id="dlg-cierre-ok" hidden>Continuar con V.B.</button>
        </div>
    </form>
</dialog>

<script>
(function () {
  var form = document.getElementById('form-cierre-vb');
  if (!form) return;
  var dlg = document.getElementById('dlg-cierre-validacion');
  var forzarInput = document.getElementById('cierre-forzar');
  var initial = <?= json_encode([
      'bloqueantes' => $nBloq,
      'avisos' => $nAvisos,
      'hallazgos' => $hallazgos,
      'ok' => ! $tieneHallazgos || $nBloq === 0,
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>;
  var validateUrl = <?= json_encode(site_url('equity/cierre/validar') . '?anio=' . (int) $anio . '&mes=' . (int) $mes) ?>;

  function renderLista(hallazgos) {
    var box = document.getElementById('dlg-cierre-lista');
    if (!hallazgos || !hallazgos.length) {
      box.innerHTML = '<p class="muted" style="margin:0">Sin hallazgos.</p>';
      return;
    }
    var base = <?= json_encode(rtrim(site_url('/'), '/')) ?>;
    var html = '<ul style="margin:0;padding-left:1.1rem">';
    hallazgos.forEach(function (h) {
      var sev = (h.severidad === 'error') ? '<strong style="color:#b91c1c">Error</strong>' : '<strong style="color:#a16207">Aviso</strong>';
      var link = '';
      if (h.href) {
        var path = h.href.charAt(0) === '/' ? h.href : '/' + h.href;
        link = ' <a href="' + base + path + '">Ir →</a>';
      }
      html += '<li style="margin:.45rem 0">' + sev + ' · <strong>' + (h.seccion || '') + '</strong><br>'
        + '<span>' + (h.titulo || '') + '</span><br>'
        + '<span class="muted" style="font-size:.9rem">' + (h.detalle || '') + '</span>' + link + '</li>';
    });
    html += '</ul>';
    box.innerHTML = html;
  }

  function openDlg(data, mode) {
    // mode: 'block' | 'warn' | 'ok'
    var titulo = document.getElementById('dlg-cierre-titulo');
    var sub = document.getElementById('dlg-cierre-sub');
    var btnForce = document.getElementById('dlg-cierre-force');
    var btnOk = document.getElementById('dlg-cierre-ok');
    var btnCancel = document.getElementById('dlg-cierre-cancel');
    renderLista(data.hallazgos || []);
    btnForce.hidden = true;
    btnOk.hidden = true;
    if (mode === 'block') {
      titulo.textContent = 'No se puede cerrar aún';
      sub.textContent = (data.bloqueantes || 0) + ' problema(s) de fechas/actualización. Corrige o fuerza el cierre solo si corresponde.';
      btnForce.hidden = false;
      btnCancel.textContent = 'Revisar datos';
    } else if (mode === 'warn') {
      titulo.textContent = 'Avisos antes del V.B.';
      sub.textContent = (data.avisos || 0) + ' aviso(s). Puedes continuar o revisar.';
      btnOk.hidden = false;
      btnCancel.textContent = 'Revisar datos';
    } else {
      titulo.textContent = 'Listo para cerrar';
      sub.textContent = 'Validación OK. ¿Confirmas el V.B.?';
      btnOk.hidden = false;
      btnCancel.textContent = 'Cancelar';
      renderLista([]);
      document.getElementById('dlg-cierre-lista').innerHTML = '<p class="muted" style="margin:0">Fechas y actualizaciones al día para el periodo.</p>';
    }
    if (typeof dlg.showModal === 'function') {
      dlg.showModal();
    } else {
      // Fallback sin <dialog>
      var msg = (data.hallazgos || []).map(function (h) {
        return (h.severidad === 'error' ? '[Error] ' : '[Aviso] ') + (h.seccion || '') + ': ' + (h.titulo || '');
      }).join('\n');
      if (mode === 'block') {
        if (window.confirm(msg + '\n\n¿Cerrar de todas formas?')) {
          forzarInput.value = '1';
          form.submit();
        }
      } else if (window.confirm((mode === 'ok' ? 'Validación OK. ' : msg + '\n\n') + '¿Continuar con V.B.?')) {
        forzarInput.value = mode === 'block' ? '1' : '0';
        form.submit();
      }
    }
  }

  dlg.addEventListener('close', function () {
    var v = dlg.returnValue;
    if (v === 'force') {
      forzarInput.value = '1';
      form.submit();
    } else if (v === 'ok') {
      forzarInput.value = '0';
      // segunda confirmación breve
      if (window.confirm('¿Confirmas el V.B. y congelar los reportes del periodo?')) {
        form.submit();
      }
    }
  });

  form.addEventListener('submit', function (ev) {
    if (forzarInput.value === '1') {
      return; // ya forzado
    }
    ev.preventDefault();
    var data = initial;
    var mode = (data.bloqueantes > 0) ? 'block' : ((data.avisos > 0) ? 'warn' : 'ok');
    openDlg(data, mode);
  });

  var btnRe = document.getElementById('btn-revalidar');
  if (btnRe) {
    btnRe.addEventListener('click', function () {
      btnRe.disabled = true;
      btnRe.textContent = 'Validando…';
      window.location.href = <?= json_encode(site_url('equity/cierre') . '?anio=' . (int) $anio . '&mes=' . (int) $mes) ?>;
    });
  }

  // Si llegamos con errores flash y hay bloqueantes, abrir popup automáticamente
  <?php if (! empty($error) && $nBloq > 0): ?>
  openDlg(initial, 'block');
  <?php endif; ?>
})();
</script>

<?= $this->endSection() ?>
