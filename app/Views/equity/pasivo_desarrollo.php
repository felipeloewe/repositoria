<?= $this->extend('layouts/shell') ?>

<?= $this->section('content') ?>

<?php
use App\Libraries\EquityUf;

$ufRate = (float) (($ufVigente['valor_clp'] ?? null) ?: EquityUf::DEFAULT_VALOR_CLP);
$moneda = strtoupper((string) ($moneda ?? 'UF')) === 'CLP' ? 'CLP' : 'UF';
$fmtIn = static function ($v, int $dec): string {
    if ($v === null || $v === '') {
        return '';
    }

    return number_format((float) $v, $dec, ',', '.');
};
$base = 'equity/pasivos/' . $seccion . '/' . ($pasivo['id'] ?? '');
?>

<section class="banner">
    <h2>Desarrollo · <?= esc($pasivo['banco_descripcion'] ?? 'Crédito') ?></h2>
    <p>
        <?php if (! empty($pasivo['numero_credito'])): ?>
            Crédito <?= esc($pasivo['numero_credito']) ?> ·
        <?php endif; ?>
        Moneda <?= esc($moneda === 'CLP' ? '$' : 'UF') ?>
        · UF vigente <?= esc(EquityUf::fmtPesos($ufRate, 2)) ?>
        · origen <?= esc($origen === 'guardado' ? 'cuotas guardadas' : 'proyección (aún no guardada)') ?>.
    </p>
    <p style="margin-top:.75rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn btn-ghost btn-sm" href="<?= site_url($base . '/editar') ?>">← Ficha del pasivo</a>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/pasivos/' . $seccion) ?>">Listado</a>
        <?php
            $egresoFuente = match ($seccion) {
                'inmuebles' => 'hipotecarios',
                'circulantes' => 'circulantes',
                'otros' => 'otros',
                default => null,
            };
        ?>
        <?php if ($egresoFuente): ?>
            <a class="btn btn-ghost btn-sm" href="<?= site_url('equity/egresos/' . $egresoFuente) ?>">Ver en Egresos</a>
        <?php endif; ?>
    </p>
</section>

<?php if (! empty($success)): ?>
    <div class="alert alert-ok"><?= esc($success) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert alert-error"><?= esc($error) ?></div>
<?php endif; ?>

<?php if (empty($tablasOk)): ?>
    <div class="alert alert-error">Falta la migración de cuotas editables (000039).</div>
<?php endif; ?>

<?php if (! empty($canManage) && ! empty($tablasOk)): ?>
<?php
    $ocr = $ocrEstado ?? [];
    $ocrListo = ! empty($ocr['listo']);
    $ocrMsg = (string) ($ocr['mensaje'] ?? '');
?>
<div class="card section-card" id="carga-desarrollo">
    <h3>Cargar desarrollo (Excel / CSV / PDF / imagen OCR)</h3>
    <p class="muted" style="margin-top:0">
        Sube la tabla del banco: Excel (.xlsx), CSV, PDF (texto o escaneado) o foto JPG/PNG del desarrollo completo.
        Se toman solo las columnas reconocidas (fecha, n° dividendo, cuota, amortización, interés, saldo en UF y/o $).
        El archivo queda siempre como respaldo; luego puedes corregir cuotas a mano.
    </p>
    <?php if ($ocrMsg !== ''): ?>
        <p class="<?= $ocrListo ? 'alert alert-ok' : 'alert alert-error' ?>" style="margin:.75rem 0">
            <?= esc($ocrMsg) ?>
            <?php if (! empty($ocr['tesseract'])): ?> · Tesseract OK<?php endif; ?>
            <?php if (! empty($ocr['pdftotext'])): ?> · pdftotext OK<?php endif; ?>
            <?php if (! empty($ocr['pdftoppm'])): ?> · pdftoppm OK<?php endif; ?>
        </p>
    <?php endif; ?>
    <form method="post" action="<?= site_url($base . '/desarrollo/importar') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-row" style="grid-template-columns:1fr auto;align-items:end;gap:1rem">
            <div>
                <label for="desarrollo_archivo">Archivo</label>
                <input id="desarrollo_archivo" name="desarrollo_archivo" type="file"
                       accept=".xlsx,.csv,.pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp,text/csv,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                       required>
            </div>
            <div>
                <button class="btn btn-primary" type="submit">Importar / OCR</button>
            </div>
        </div>
    </form>
    <?php if (! empty($pasivo['desarrollo_archivo_nombre'])): ?>
        <p style="margin-top:1rem" class="muted">
            Último archivo:
            <a href="<?= site_url($base . '/desarrollo/archivo') ?>"><?= esc($pasivo['desarrollo_archivo_nombre']) ?></a>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card section-card">
    <h3>Cuotas del desarrollo</h3>
    <p class="muted" style="margin-top:0">
        Edita montos mes a mes. En moneda <?= $moneda === 'CLP' ? '$' : 'UF' ?> el par equivalente se completa al guardar/mostrar con la UF vigente.
    </p>

    <?php if (! empty($canManage) && ! empty($tablasOk)): ?>
        <form method="post" action="<?= site_url($base . '/desarrollo') ?>" id="form-desarrollo">
            <?= csrf_field() ?>
            <div style="overflow-x:auto">
                <table class="table" id="tabla-desarrollo">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Div.</th>
                            <?php if ($moneda === 'UF'): ?>
                                <th>Cuota UF</th>
                                <th>Cuota $</th>
                                <th>Amort. UF</th>
                                <th>Amort. $</th>
                                <th>Interés UF</th>
                                <th>Interés $</th>
                                <th>Saldo UF</th>
                                <th>Saldo $</th>
                            <?php else: ?>
                                <th>Cuota $</th>
                                <th>Cuota UF</th>
                                <th>Amort. $</th>
                                <th>Amort. UF</th>
                                <th>Interés $</th>
                                <th>Interés UF</th>
                                <th>Saldo $</th>
                                <th>Saldo UF</th>
                            <?php endif; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rows = $cuotas !== [] ? $cuotas : [[
                        'fecha' => '', 'dividendo_n' => '', 'cuota_uf' => '', 'cuota_clp' => '',
                        'amortizacion_uf' => '', 'amortizacion_clp' => '', 'interes_uf' => '', 'interes_clp' => '',
                        'saldo_uf' => '', 'saldo_clp' => '',
                    ]];
                    foreach ($rows as $c):
                    ?>
                        <tr>
                            <td><input type="date" name="fecha[]" value="<?= esc($c['fecha'] ?? '') ?>"></td>
                            <td><input type="number" name="dividendo_n[]" min="1" style="width:4.5rem" value="<?= esc($c['dividendo_n'] ?? '') ?>"></td>
                            <?php if ($moneda === 'UF'): ?>
                                <td><input type="text" name="cuota_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['cuota_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="cuota_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['cuota_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="amortizacion_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['amortizacion_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="amortizacion_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['amortizacion_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="interes_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['interes_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="interes_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['interes_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="saldo_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['saldo_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="saldo_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['saldo_clp'] ?? null, 0)) ?>"></td>
                            <?php else: ?>
                                <td><input type="text" name="cuota_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['cuota_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="cuota_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['cuota_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="amortizacion_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['amortizacion_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="amortizacion_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['amortizacion_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="interes_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['interes_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="interes_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['interes_uf'] ?? null, 4)) ?>"></td>
                                <td><input type="text" name="saldo_clp[]" inputmode="decimal" value="<?= esc($fmtIn($c['saldo_clp'] ?? null, 0)) ?>"></td>
                                <td><input type="text" name="saldo_uf[]" inputmode="decimal" value="<?= esc($fmtIn($c['saldo_uf'] ?? null, 4)) ?>"></td>
                            <?php endif; ?>
                            <td><button type="button" class="btn btn-ghost btn-sm js-del-row">Quitar</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
                <button class="btn btn-primary" type="submit">Guardar desarrollo</button>
                <button class="btn btn-ghost" type="button" id="btn-add-row">Agregar fila</button>
            </div>
        </form>

        <form method="post" action="<?= site_url($base . '/desarrollo/regenerar') ?>" style="margin-top:1rem"
              onsubmit="return confirm('¿Regenerar desde la proyección automática? Se reemplazan las cuotas guardadas.');">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">Regenerar desde proyección</button>
        </form>

        <template id="tpl-desarrollo-row">
            <tr>
                <td><input type="date" name="fecha[]" value=""></td>
                <td><input type="number" name="dividendo_n[]" min="1" style="width:4.5rem" value=""></td>
                <?php if ($moneda === 'UF'): ?>
                    <td><input type="text" name="cuota_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="cuota_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="amortizacion_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="amortizacion_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="interes_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="interes_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="saldo_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="saldo_clp[]" inputmode="decimal" value=""></td>
                <?php else: ?>
                    <td><input type="text" name="cuota_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="cuota_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="amortizacion_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="amortizacion_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="interes_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="interes_uf[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="saldo_clp[]" inputmode="decimal" value=""></td>
                    <td><input type="text" name="saldo_uf[]" inputmode="decimal" value=""></td>
                <?php endif; ?>
                <td><button type="button" class="btn btn-ghost btn-sm js-del-row">Quitar</button></td>
            </tr>
        </template>
        <script>
        (function () {
            const tbody = document.querySelector('#tabla-desarrollo tbody');
            const tpl = document.getElementById('tpl-desarrollo-row');
            document.getElementById('btn-add-row')?.addEventListener('click', function () {
                if (!tpl || !tbody) return;
                tbody.appendChild(tpl.content.cloneNode(true));
            });
            tbody?.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-del-row');
                if (!btn) return;
                const tr = btn.closest('tr');
                if (tr && tbody.querySelectorAll('tr').length > 1) tr.remove();
            });
        })();
        </script>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th><th>Div.</th><th>Cuota</th><th>Amortización</th><th>Interés</th><th>Saldo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cuotas as $c): ?>
                <tr>
                    <td><?= esc($c['fecha'] ?? '—') ?></td>
                    <td><?= esc($c['dividendo_n'] ?? '—') ?></td>
                    <td><?= esc($moneda === 'UF' ? EquityUf::fmtUf($c['cuota_uf'] ?? null, 4) : EquityUf::fmtPesos($c['cuota_clp'] ?? null, 0)) ?></td>
                    <td><?= esc($moneda === 'UF' ? EquityUf::fmtUf($c['amortizacion_uf'] ?? null, 4) : EquityUf::fmtPesos($c['amortizacion_clp'] ?? null, 0)) ?></td>
                    <td><?= esc($moneda === 'UF' ? EquityUf::fmtUf($c['interes_uf'] ?? null, 4) : EquityUf::fmtPesos($c['interes_clp'] ?? null, 0)) ?></td>
                    <td><?= esc($moneda === 'UF' ? EquityUf::fmtUf($c['saldo_uf'] ?? null, 4) : EquityUf::fmtPesos($c['saldo_clp'] ?? null, 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
