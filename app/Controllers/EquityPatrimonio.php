<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;
use App\Libraries\MembershipAuth;
use App\Models\EquityBienRaizModel;
use App\Models\EquityFichaModel;
use App\Models\EquityOtroActivoModel;
use App\Models\EquityPasivoModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityRespaldoModel;
use App\Models\EquitySociedadModel;
use App\Models\EquityVehiculoModel;
use App\Models\DocumentoModel;

/**
 * Menús patrimoniales Equity: resumen, activos, pasivos, ingresos, respaldos.
 */
class EquityPatrimonio extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireAccess()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EquityAcl::canAccess($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin acceso a Equity.');
        }

        return $memb;
    }

    public function resumen()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $personas  = $this->personas($empresaId);

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        $totalesUf = [
            'bienes_raices' => $this->sumUfCol($empresaId, 'equity_bienes_raices', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'vehiculos'     => $this->sumUfCol($empresaId, 'equity_vehiculos', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'sociedades'    => $this->sumUfCol($empresaId, 'equity_sociedades', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'otros_activos' => $this->sumUfCol($empresaId, 'equity_otros_activos', 'saldo_uf', 'saldo_clp', $personaId, $ufRate),
            'pasivos_inm'       => $this->sumPasivoCategoria($empresaId, 'inmueble', $personaId, $ufRate),
            'pasivos_circ'      => $this->sumPasivoCategoria($empresaId, 'circulante', $personaId, $ufRate),
            'pasivos_otro'      => $this->sumPasivoCategoria($empresaId, 'otro', $personaId, $ufRate),
            'pasivos_indirecta' => $this->sumDeudaIndirecta($empresaId, $personaId, $ufRate),
            'pasivos_lineas'    => $this->sumLineasDisponibles($empresaId, $personaId, $ufRate),
        ];
        $totalesUf['activos'] = $totalesUf['bienes_raices'] + $totalesUf['vehiculos'] + $totalesUf['sociedades'] + $totalesUf['otros_activos'];
        // Líneas disponibles = cupos no utilizados (informativo). Deuda indirecta sí cuenta como pasivo contingente.
        $totalesUf['pasivos'] = $totalesUf['pasivos_inm'] + $totalesUf['pasivos_circ'] + $totalesUf['pasivos_otro'] + $totalesUf['pasivos_indirecta'];
        $totalesUf['patrimonio'] = $totalesUf['activos'] - $totalesUf['pasivos'];

        $totales = [];
        foreach ($totalesUf as $k => $uf) {
            $totales[$k] = round($uf * $ufRate, 2);
        }

        return view('equity/resumen', [
            'title'     => 'Resumen',
            'memb'      => $memb,
            'equityNav' => 'resumen',
            'personas'  => $personas,
            'personaId' => $personaId,
            'totales'   => $totales,
            'totalesUf' => $totalesUf,
            'ufVigente' => $ufVigente,
            'menus'     => EquityCatalogo::menusInicio(),
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    public function activos(string $seccion)
    {
        return $this->seccionLista($this->claveActivo($seccion));
    }

    public function pasivos(string $seccion)
    {
        return $this->seccionLista($this->clavePasivo($seccion));
    }

    public function editActivo(string $seccion, string $id)
    {
        return $this->seccionLista($this->claveActivo($seccion), $id);
    }

    public function editPasivo(string $seccion, string $id)
    {
        return $this->seccionLista($this->clavePasivo($seccion), $id);
    }

    public function storeActivo(string $seccion)
    {
        return $this->storeSeccion($this->claveActivo($seccion));
    }

    public function storePasivo(string $seccion)
    {
        return $this->storeSeccion($this->clavePasivo($seccion));
    }

    public function updateActivo(string $seccion, string $id)
    {
        return $this->storeSeccion($this->claveActivo($seccion), $id);
    }

    public function updatePasivo(string $seccion, string $id)
    {
        return $this->storeSeccion($this->clavePasivo($seccion), $id);
    }

    private function claveActivo(string $seccion): string
    {
        return match ($seccion) {
            'bienes-raices' => 'bienes-raices',
            'vehiculos'     => 'vehiculos',
            'sociedades'    => 'sociedades',
            'otros'         => 'otros-activos',
            default         => '',
        };
    }

    private function clavePasivo(string $seccion): string
    {
        return match ($seccion) {
            'inmuebles'   => 'pasivos-inmuebles',
            'circulantes' => 'pasivos-circulantes',
            'otros'       => 'otros-pasivos',
            'indirecta'   => 'pasivos-indirecta',
            'lineas'      => 'pasivos-lineas',
            default       => '',
        };
    }

    public function situacionFinanciera()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $personas  = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        $q = model(EquityPasivoModel::class)
            ->where('empresa_id', $empresaId)
            ->whereIn('categoria', ['inmueble', 'circulante', 'otro', 'indirecta'])
            ->orderBy('banco_descripcion', 'ASC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $rows = $q->findAll();
        foreach ($rows as &$row) {
            $row['persona'] = $personaMap[$row['persona_id']] ?? null;
            $row['tipo_credito_label'] = $this->tipoCreditoSituacion($row);
            $row['total_clp'] = $this->montoClpPreferido(
                $row['total_credito_clp'] ?? null,
                $row['total_credito_uf'] ?? null,
                $row['saldo_clp'] ?? null,
                $row['saldo_uf'] ?? null,
                $ufRate
            );
            $row['vigente_show'] = $this->montoClpPreferido(
                $row['vigente_clp'] ?? null,
                $row['vigente_uf'] ?? null,
                $row['saldo_clp'] ?? null,
                $row['saldo_uf'] ?? null,
                $ufRate
            );
            $row['cuota_show'] = $this->montoClpPreferido(
                $row['cuota_mensual'] ?? null,
                null,
                $row['dividendo_clp'] ?? null,
                $row['dividendo_uf'] ?? null,
                $ufRate
            );
            $row['mora_30_show'] = $this->montoClpPreferido($row['mora_30_59_clp'] ?? null, $row['mora_30_59_uf'] ?? null, null, null, $ufRate);
            $row['mora_60_show'] = $this->montoClpPreferido($row['mora_60_89_clp'] ?? null, $row['mora_60_89_uf'] ?? null, null, null, $ufRate);
            $row['mora_90_show'] = $this->montoClpPreferido($row['mora_90_mas_clp'] ?? null, $row['mora_90_mas_uf'] ?? null, null, null, $ufRate);
        }
        unset($row);

        // Reporte de ingresos informados (fichas + rentas laborales de la ficha personal).
        $fq = model(EquityFichaModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha_corte', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $fq->where('persona_id', $personaId);
        }
        $ingresos = $fq->findAll();
        foreach ($ingresos as &$ing) {
            $ing['persona'] = $personaMap[$ing['persona_id']] ?? null;
        }
        unset($ing);

        $ingresosLaborales = [];
        foreach ($personas as $p) {
            if ($personaId !== '' && $p['id'] !== $personaId) {
                continue;
            }
            $renta1 = $p['renta_liquida_persona'] ?? null;
            $renta2 = $p['renta_liquida_2'] ?? null;
            if (($renta1 === null || $renta1 === '') && ($renta2 === null || $renta2 === '')) {
                continue;
            }
            $ingresosLaborales[] = [
                'persona'              => $p,
                'fuente'               => 'Antecedentes laborales',
                'renta_liquida'        => $renta1,
                'renta_liquida_2'      => $renta2,
                'empleador'            => $p['nombre_empleador'] ?? null,
                'cargo'                => $p['cargo_actividad'] ?? null,
            ];
        }

        $ingresoMensual = null;
        $totalIngresosReportados = 0.0;
        if ($personaId !== '') {
            foreach ($ingresos as $ing) {
                $v = $ing['ingreso_mensual_total'] ?? $ing['renta_liquida'] ?? null;
                if ($v !== null && $v !== '') {
                    $ingresoMensual = (float) $v;
                    break;
                }
            }
            if ($ingresoMensual === null) {
                foreach ($ingresosLaborales as $lab) {
                    $s = (float) ($lab['renta_liquida'] ?? 0) + (float) ($lab['renta_liquida_2'] ?? 0);
                    if ($s > 0) {
                        $ingresoMensual = $s;
                        break;
                    }
                }
            }
        }
        foreach ($ingresos as $ing) {
            $v = $ing['ingreso_mensual_total'] ?? $ing['renta_liquida'] ?? null;
            if ($v !== null && $v !== '') {
                $totalIngresosReportados += (float) $v;
            }
        }

        $totalCuotas = 0.0;
        foreach ($rows as $r) {
            if ($r['cuota_show'] !== null) {
                $totalCuotas += (float) $r['cuota_show'];
            }
        }

        return view('equity/situacion_financiera', [
            'title'                   => 'Situación financiera',
            'memb'                    => $memb,
            'equityNav'               => 'situacion-financiera',
            'rows'                    => $rows,
            'ingresos'                => $ingresos,
            'ingresosLaborales'       => $ingresosLaborales,
            'personas'                => $personas,
            'personaId'               => $personaId,
            'ufVigente'               => $ufVigente,
            'ingresoMensual'          => $ingresoMensual,
            'totalIngresosReportados' => $totalIngresosReportados,
            'totalCuotas'             => $totalCuotas,
            'canManage'               => EquityAcl::canManage($memb),
            'success'                 => session()->getFlashdata('success'),
            'error'                   => session()->getFlashdata('error'),
        ]);
    }

    public function guardarCuotasSituacion()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/situacion-financiera')->with('error', 'Sin permiso para editar cuotas.');
        }

        $cuotas = $this->request->getPost('cuota');
        if (! is_array($cuotas)) {
            return redirect()->to('/equity/situacion-financiera')->with('error', 'Sin datos de cuotas.');
        }

        $personaId = trim((string) $this->request->getPost('persona_id'));
        $updated = 0;
        foreach ($cuotas as $id => $raw) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $row = model(EquityPasivoModel::class)
                ->where('id', $id)
                ->where('empresa_id', $memb['empresa_id'])
                ->first();
            if ($row === null) {
                continue;
            }
            $parsed = EquityUf::parseDecimal((string) $raw, 2);
            $data = [
                'cuota_mensual' => $parsed !== null ? number_format($parsed, 2, '.', '') : null,
            ];
            // Hipotecarios: mantener dividendo_clp alineado con la cuota en $.
            if (($row['categoria'] ?? '') === 'inmueble') {
                $data['dividendo_clp'] = $data['cuota_mensual'];
                if ($parsed !== null) {
                    $par = EquityUf::resolverPar('', (string) $parsed, $memb['empresa_id']);
                    if ($par['uf'] !== null) {
                        $data['dividendo_uf'] = $par['uf'];
                    }
                }
            }
            model(EquityPasivoModel::class)->update($id, $data);
            $updated++;
        }

        $qs = $personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : '';

        return redirect()->to('/equity/situacion-financiera' . $qs)
            ->with('success', $updated > 0 ? "Se actualizaron {$updated} cuota(s) mensual(es)." : 'Sin cambios.');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function tipoCreditoSituacion(array $row): string
    {
        $tipo = trim((string) ($row['tipo'] ?? ''));
        if ($tipo !== '') {
            return $tipo;
        }

        return match ($row['categoria'] ?? '') {
            'inmueble'   => 'Vivienda',
            'circulante' => 'Comercial',
            'indirecta'  => 'Comercial',
            'otro'       => 'Consumo',
            default      => '—',
        };
    }

    private function montoClpPreferido(
        mixed $clpPrimary,
        mixed $ufPrimary,
        mixed $clpFallback,
        mixed $ufFallback,
        float $ufRate
    ): ?float {
        if ($clpPrimary !== null && $clpPrimary !== '') {
            return (float) $clpPrimary;
        }
        if ($ufPrimary !== null && $ufPrimary !== '' && $ufRate > 0) {
            return round((float) $ufPrimary * $ufRate, 2);
        }
        if ($clpFallback !== null && $clpFallback !== '') {
            return (float) $clpFallback;
        }
        if ($ufFallback !== null && $ufFallback !== '' && $ufRate > 0) {
            return round((float) $ufFallback * $ufRate, 2);
        }

        return null;
    }

    public function ingresos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $q = model(EquityFichaModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha_corte', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $fichas = $q->findAll();
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($fichas as &$f) {
            $f['persona'] = $personaMap[$f['persona_id']] ?? null;
        }
        unset($f);

        return view('equity/ingresos', [
            'title'     => 'Ingresos',
            'memb'      => $memb,
            'equityNav' => 'ingresos',
            'fichas'    => $fichas,
            'personas'  => $personas,
            'personaId' => $personaId,
            'ufVigente' => EquityUf::vigente($empresaId),
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    public function respaldos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $q = model(EquityRespaldoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $respaldos = $q->findAll();
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $docIds = array_values(array_filter(array_column($respaldos, 'documento_id')));
        $docsById = [];
        if ($docIds !== []) {
            foreach (model(DocumentoModel::class)->whereIn('id', $docIds)->findAll() as $d) {
                $docsById[$d['id']] = $d;
            }
        }
        foreach ($respaldos as &$r) {
            $r['persona']   = $personaMap[$r['persona_id']] ?? null;
            $r['documento'] = $docsById[$r['documento_id']] ?? null;
        }
        unset($r);

        return view('equity/respaldos', [
            'title'     => 'Respaldos',
            'memb'      => $memb,
            'equityNav' => 'respaldos',
            'respaldos' => $respaldos,
            'personas'  => $personas,
            'personaId' => $personaId,
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    private function seccionLista(string $clave, ?string $editId = null)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $rows = $this->loadRows($meta, $empresaId, $personaId);
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($rows as &$row) {
            $row['persona'] = $personaMap[$row['persona_id']] ?? null;
        }
        unset($row);

        $edit = null;
        if ($editId !== null && $editId !== '') {
            if (! EquityAcl::canManage($memb)) {
                return redirect()->to($this->pathSeccion($clave))->with('error', 'Sin permiso para editar.');
            }
            $edit = $this->findRegistro($meta, $empresaId, $editId);
            if ($edit === null) {
                return redirect()->to($this->pathSeccion($clave))->with('error', 'Registro no encontrado.');
            }
            $personaId = (string) ($edit['persona_id'] ?? $personaId);
        }

        return view('equity/seccion', [
            'title'     => $meta['label'],
            'memb'      => $memb,
            'equityNav' => $clave,
            'meta'      => $meta,
            'rows'      => $rows,
            'personas'  => $personas,
            'personaId' => $personaId,
            'edit'      => $edit,
            'ufVigente' => EquityUf::vigente($empresaId),
            'canManage' => EquityAcl::canManage($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    private function storeSeccion(string $clave, ?string $editId = null)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin permiso para editar.');
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $personaId = trim((string) $this->request->getPost('persona_id'));
        $persona = model(EquityPersonaModel::class)
            ->where('id', $personaId)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($persona === null) {
            return redirect()->back()->with('error', 'Selecciona una persona válida.');
        }

        helper('id');
        $money = static function ($raw): ?string {
            $v = trim((string) $raw);
            if ($v === '') {
                return null;
            }
            if (str_contains($v, ',') && str_contains($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (str_contains($v, ',')) {
                $v = str_replace(',', '.', $v);
            }

            return is_numeric($v) ? number_format((float) $v, 2, '.', '') : null;
        };

        $parValor = EquityUf::resolverPar(
            (string) $this->request->getPost('valor_uf'),
            (string) $this->request->getPost('valor_clp'),
            $memb['empresa_id']
        );
        $parSaldo = EquityUf::resolverPar(
            (string) $this->request->getPost('saldo_uf'),
            (string) $this->request->getPost('saldo_clp'),
            $memb['empresa_id']
        );

        $redirectPath = $this->pathSeccion($clave);
        $isEdit = $editId !== null && $editId !== '';
        if ($isEdit) {
            $existing = $this->findRegistro($meta, $memb['empresa_id'], $editId);
            if ($existing === null) {
                return redirect()->to($redirectPath)->with('error', 'Registro no encontrado.');
            }
        }

        $intOrNull = static function ($raw): ?int {
            $v = trim((string) $raw);
            if ($v === '' || ! is_numeric($v)) {
                return null;
            }

            return (int) $v;
        };
        $ufDec = static function ($raw): ?string {
            $v = EquityUf::parseDecimal((string) $raw, 4);

            return $v !== null ? number_format($v, 4, '.', '') : null;
        };

        $model = null;
        $data  = [];

        if ($clave === 'bienes-raices') {
            $direccion = trim((string) $this->request->getPost('direccion'));
            $tipoInm   = $intOrNull($this->request->getPost('tipo_inmueble'));
            if ($direccion === '' || $tipoInm === null) {
                return redirect()->to($redirectPath)->with('error', 'Tipo y dirección son obligatorios.');
            }
            $model = model(EquityBienRaizModel::class);
            $data  = [
                'persona_id'    => $personaId,
                'tipo_inmueble' => $tipoInm,
                'direccion'     => $direccion,
                'descripcion'   => $direccion,
                'rol'           => trim((string) $this->request->getPost('rol')) ?: null,
                'garantia'      => $intOrNull($this->request->getPost('garantia')),
                'asegurado'     => $intOrNull($this->request->getPost('asegurado')),
                'valor_clp'     => $parValor['clp'],
                'valor_uf'      => $parValor['uf'],
                'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'vehiculos') {
            $tipoVeh = $intOrNull($this->request->getPost('tipo_vehiculo'));
            if ($tipoVeh === null) {
                return redirect()->to($redirectPath)->with('error', 'El tipo de vehículo es obligatorio.');
            }
            $tipoLabel = EquityCatalogo::opcionesTipoVehiculo()[$tipoVeh] ?? (string) $tipoVeh;
            $model = model(EquityVehiculoModel::class);
            $data  = [
                'persona_id'    => $personaId,
                'tipo_vehiculo' => $tipoVeh,
                'tipo'          => $tipoLabel,
                'marca'         => trim((string) $this->request->getPost('marca')) ?: null,
                'modelo'        => trim((string) $this->request->getPost('modelo')) ?: null,
                'anio'          => $intOrNull($this->request->getPost('anio')),
                'patente'       => trim((string) $this->request->getPost('patente')) ?: null,
                'garantia'      => $intOrNull($this->request->getPost('garantia')),
                'asegurado'     => $intOrNull($this->request->getPost('asegurado')),
                'valor_clp'     => $parValor['clp'],
                'valor_uf'      => $parValor['uf'],
                'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'sociedades') {
            $nombre = trim((string) $this->request->getPost('nombre_sociedad'));
            if ($nombre === '') {
                return redirect()->to($redirectPath)->with('error', 'La razón social es obligatoria.');
            }
            $model = model(EquitySociedadModel::class);
            $data  = [
                'persona_id'         => $personaId,
                'nombre_sociedad'    => $nombre,
                'rut_sociedad'       => trim((string) $this->request->getPost('rut_sociedad')) ?: null,
                'giro_rubro'         => trim((string) $this->request->getPost('giro_rubro')) ?: null,
                'porcentaje'         => $money($this->request->getPost('porcentaje')),
                'valor_clp'          => $parValor['clp'],
                'valor_uf'           => $parValor['uf'],
                'emp_relacionada'    => $intOrNull($this->request->getPost('emp_relacionada')),
                'cumple_articulo_84' => (int) $this->request->getPost('cumple_articulo_84') === 1 ? 1 : 0,
                'tipo'               => 'Directo',
                'notas'              => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'otros-activos') {
            $tipoAct = $intOrNull($this->request->getPost('tipo_activo'));
            if ($tipoAct === null) {
                return redirect()->to($redirectPath)->with('error', 'El tipo de activo es obligatorio.');
            }
            $tipoLabel = EquityCatalogo::opcionesTipoOtroActivo()[$tipoAct] ?? (string) $tipoAct;
            $model = model(EquityOtroActivoModel::class);
            $data  = [
                'persona_id'  => $personaId,
                'tipo_activo' => $tipoAct,
                'tipo'        => $tipoLabel,
                'institucion' => trim((string) $this->request->getPost('institucion')) ?: null,
                'saldo_clp'   => $parSaldo['clp'],
                'saldo_uf'    => $parSaldo['uf'],
                'notas'       => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'pasivos-inmuebles') {
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La institución / banco es obligatoria.');
            }
            $parDividendo = EquityUf::resolverPar(
                (string) $this->request->getPost('dividendo_uf'),
                (string) $this->request->getPost('dividendo_clp'),
                $memb['empresa_id']
            );
            $plazo = $intOrNull($this->request->getPost('plazo_pactado'));
            $cuotasTot = $intOrNull($this->request->getPost('cuotas_totales'));
            if ($cuotasTot === null) {
                $cuotasTot = $plazo;
            }
            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'                   => $personaId,
                'categoria'                    => 'inmueble',
                'banco_descripcion'            => $desc,
                'numero_credito'               => trim((string) $this->request->getPost('numero_credito')) ?: null,
                'sucursal'                     => trim((string) $this->request->getPost('sucursal')) ?: null,
                'moneda'                       => trim((string) $this->request->getPost('moneda')) ?: 'UF',
                'dividendo_n'                  => $intOrNull($this->request->getPost('dividendo_n')),
                'plazo_pactado'                => $plazo,
                'saldo_clp'                    => $parSaldo['clp'],
                'saldo_uf'                     => $parSaldo['uf'],
                'cuotas_totales'               => $cuotasTot,
                'cuotas_pagadas'               => $intOrNull($this->request->getPost('cuotas_pagadas')),
                'cuota_mensual'                => $parDividendo['clp'],
                'dividendo_uf'                 => $parDividendo['uf'],
                'dividendo_clp'                => $parDividendo['clp'],
                'amortizacion_uf'              => $ufDec($this->request->getPost('amortizacion_uf')),
                'interes_uf'                   => $ufDec($this->request->getPost('interes_uf')),
                'comision_uf'                  => $ufDec($this->request->getPost('comision_uf')),
                'seg_incendio_uf'              => $ufDec($this->request->getPost('seg_incendio_uf')),
                'seg_desgravamen_uf'           => $ufDec($this->request->getPost('seg_desgravamen_uf')),
                'seg_adicionales_uf'           => $ufDec($this->request->getPost('seg_adicionales_uf')),
                'seg_otros_complementarios_uf' => $ufDec($this->request->getPost('seg_otros_complementarios_uf')),
                'fecha_vencimiento'            => trim((string) $this->request->getPost('fecha_vencimiento')) ?: null,
                'direccion_garantia'           => trim((string) $this->request->getPost('direccion_garantia')) ?: null,
                'asociacion'                   => trim((string) $this->request->getPost('asociacion')) ?: null,
                'tipo'                         => 'Hipotecario',
                'seguros_info'                 => trim((string) $this->request->getPost('seguros_info')) ?: null,
                'cuenta_pac'                   => trim((string) $this->request->getPost('cuenta_pac')) ?: null,
                'dia_cargo_pac'                => $intOrNull($this->request->getPost('dia_cargo_pac')),
                'notas'                        => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'pasivos-indirecta') {
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La institución financiera es obligatoria.');
            }
            $tipoCredito = trim((string) $this->request->getPost('tipo_credito'));
            if ($tipoCredito === '') {
                return redirect()->to($redirectPath)->with('error', 'El tipo de crédito es obligatorio.');
            }
            $parTotal = EquityUf::resolverPar(
                (string) $this->request->getPost('total_credito_uf'),
                (string) $this->request->getPost('total_credito_clp'),
                $memb['empresa_id']
            );
            $parVig = EquityUf::resolverPar(
                (string) $this->request->getPost('vigente_uf'),
                (string) $this->request->getPost('vigente_clp'),
                $memb['empresa_id']
            );
            $par3059 = EquityUf::resolverPar(
                (string) $this->request->getPost('mora_30_59_uf'),
                (string) $this->request->getPost('mora_30_59_clp'),
                $memb['empresa_id']
            );
            $par6089 = EquityUf::resolverPar(
                (string) $this->request->getPost('mora_60_89_uf'),
                (string) $this->request->getPost('mora_60_89_clp'),
                $memb['empresa_id']
            );
            $par90 = EquityUf::resolverPar(
                (string) $this->request->getPost('mora_90_mas_uf'),
                (string) $this->request->getPost('mora_90_mas_clp'),
                $memb['empresa_id']
            );
            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'          => $personaId,
                'categoria'           => 'indirecta',
                'banco_descripcion'   => $desc,
                'tipo'                => $tipoCredito,
                'fecha_otorgamiento'  => trim((string) $this->request->getPost('fecha_otorgamiento')) ?: null,
                'total_credito_uf'    => $parTotal['uf'],
                'total_credito_clp'   => $parTotal['clp'],
                'vigente_uf'          => $parVig['uf'],
                'vigente_clp'         => $parVig['clp'],
                'mora_30_59_uf'       => $par3059['uf'],
                'mora_30_59_clp'      => $par3059['clp'],
                'mora_60_89_uf'       => $par6089['uf'],
                'mora_60_89_clp'      => $par6089['clp'],
                'mora_90_mas_uf'      => $par90['uf'],
                'mora_90_mas_clp'     => $par90['clp'],
                'saldo_uf'            => $parVig['uf'] ?? $parTotal['uf'],
                'saldo_clp'           => $parVig['clp'] ?? $parTotal['clp'],
                'notas'               => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } elseif ($clave === 'pasivos-lineas') {
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La institución financiera es obligatoria.');
            }
            $tipoLinea = $intOrNull($this->request->getPost('tipo_linea'));
            if ($tipoLinea === null) {
                return redirect()->to($redirectPath)->with('error', 'Selecciona el tipo (líneas de crédito / otros créditos).');
            }
            $tipoLabel = EquityCatalogo::opcionesTipoLineaDisponible()[$tipoLinea] ?? (string) $tipoLinea;
            $parDir = EquityUf::resolverPar(
                (string) $this->request->getPost('monto_directo_uf'),
                (string) $this->request->getPost('monto_directo_clp'),
                $memb['empresa_id']
            );
            $parInd = EquityUf::resolverPar(
                (string) $this->request->getPost('monto_indirecto_uf'),
                (string) $this->request->getPost('monto_indirecto_clp'),
                $memb['empresa_id']
            );
            $totalUf  = (float) ($parDir['uf'] ?? 0) + (float) ($parInd['uf'] ?? 0);
            $totalClp = (float) ($parDir['clp'] ?? 0) + (float) ($parInd['clp'] ?? 0);
            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'          => $personaId,
                'categoria'           => 'linea',
                'banco_descripcion'   => $desc,
                'tipo'                => $tipoLabel,
                'monto_directo_uf'    => $parDir['uf'],
                'monto_directo_clp'   => $parDir['clp'],
                'monto_indirecto_uf'  => $parInd['uf'],
                'monto_indirecto_clp' => $parInd['clp'],
                'saldo_uf'            => $totalUf > 0 ? number_format($totalUf, 4, '.', '') : null,
                'saldo_clp'           => $totalClp > 0 ? number_format($totalClp, 2, '.', '') : null,
                'notas'               => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        } else {
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La descripción / banco es obligatoria.');
            }
            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'        => $personaId,
                'categoria'         => $meta['categoria'] ?? 'otro',
                'banco_descripcion' => $desc,
                'saldo_clp'         => $parSaldo['clp'],
                'saldo_uf'          => $parSaldo['uf'],
                'cuotas_totales'    => $intOrNull($this->request->getPost('cuotas_totales')),
                'cuotas_pagadas'    => $intOrNull($this->request->getPost('cuotas_pagadas')),
                'cuota_mensual'     => $money($this->request->getPost('cuota_mensual')),
                'asociacion'        => trim((string) $this->request->getPost('asociacion')) ?: null,
                'tipo'              => trim((string) $this->request->getPost('tipo')) ?: null,
                'notas'             => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        }

        if ($isEdit) {
            $model->update($editId, $data);
            $msg = 'Registro actualizado.';
        } else {
            $data['id']            = new_id();
            $data['empresa_id']    = $memb['empresa_id'];
            $data['creado_por_id'] = $memb['usuario_id'];
            $model->insert($data);
            $msg = 'Registro agregado.';
        }

        return redirect()->to($redirectPath . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : ''))
            ->with('success', $msg);
    }

    private function pathSeccion(string $clave): string
    {
        return match ($clave) {
            'bienes-raices'       => '/equity/activos/bienes-raices',
            'vehiculos'           => '/equity/activos/vehiculos',
            'sociedades'          => '/equity/activos/sociedades',
            'otros-activos'       => '/equity/activos/otros',
            'pasivos-inmuebles'   => '/equity/pasivos/inmuebles',
            'pasivos-circulantes' => '/equity/pasivos/circulantes',
            'otros-pasivos'       => '/equity/pasivos/otros',
            'pasivos-indirecta'   => '/equity/pasivos/indirecta',
            'pasivos-lineas'      => '/equity/pasivos/lineas',
            default               => '/dashboard',
        };
    }

    /**
     * @param array{table: string, categoria?: string} $meta
     * @return array<string, mixed>|null
     */
    private function findRegistro(array $meta, string $empresaId, string $id): ?array
    {
        $model = match ($meta['table']) {
            'equity_bienes_raices' => model(EquityBienRaizModel::class),
            'equity_vehiculos'     => model(EquityVehiculoModel::class),
            'equity_sociedades'    => model(EquitySociedadModel::class),
            'equity_otros_activos' => model(EquityOtroActivoModel::class),
            'equity_pasivos'       => model(EquityPasivoModel::class),
            default                => null,
        };
        if ($model === null) {
            return null;
        }
        $q = $model->where('id', $id)->where('empresa_id', $empresaId);
        if (($meta['table'] ?? '') === 'equity_pasivos' && isset($meta['categoria'])) {
            $q->where('categoria', $meta['categoria']);
        }
        $row = $q->first();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    private function personas(string $empresaId): array
    {
        return model(EquityPersonaModel::class)
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * @param array{table: string, categoria?: string} $meta
     * @return list<array<string, mixed>>
     */
    private function loadRows(array $meta, string $empresaId, string $personaId): array
    {
        $model = match ($meta['table']) {
            'equity_bienes_raices'  => model(EquityBienRaizModel::class),
            'equity_vehiculos'      => model(EquityVehiculoModel::class),
            'equity_sociedades'     => model(EquitySociedadModel::class),
            'equity_otros_activos'  => model(EquityOtroActivoModel::class),
            'equity_pasivos'        => model(EquityPasivoModel::class),
            default                 => null,
        };
        if ($model === null) {
            return [];
        }

        $q = $model->where('empresa_id', $empresaId)->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        if (($meta['table'] ?? '') === 'equity_pasivos' && isset($meta['categoria'])) {
            $q->where('categoria', $meta['categoria']);
        }

        return $q->findAll();
    }

    /**
     * Suma UF: usa columna UF si existe; si no, convierte CLP legacy con $ufRate.
     */
    private function sumUfCol(
        string $empresaId,
        string $table,
        string $colUf,
        string $colClp,
        string $personaId,
        float $ufRate
    ): float {
        if (! db_connect()->tableExists($table)) {
            return 0.0;
        }
        $db = db_connect();
        $hasUf = $db->fieldExists($colUf, $table);
        $builder = $db->table($table)->where('empresa_id', $empresaId);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($hasUf && $r[$colUf] !== null && $r[$colUf] !== '') {
                $sum += (float) $r[$colUf];
            } elseif ($r[$colClp] !== null && $r[$colClp] !== '' && $ufRate > 0) {
                $sum += (float) $r[$colClp] / $ufRate;
            }
        }

        return $sum;
    }

    private function sumPasivoCategoria(string $empresaId, string $categoria, string $personaId, float $ufRate): float
    {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return 0.0;
        }
        $builder = db_connect()->table('equity_pasivos')
            ->where('empresa_id', $empresaId)
            ->where('categoria', $categoria);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
            } elseif ($r['saldo_clp'] !== null && $r['saldo_clp'] !== '' && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
            }
        }

        return $sum;
    }

    /** Suma deuda indirecta (vigente o total crédito) en UF. */
    private function sumDeudaIndirecta(string $empresaId, string $personaId, float $ufRate): float
    {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return 0.0;
        }
        $db = db_connect();
        $builder = $db->table('equity_pasivos')
            ->where('empresa_id', $empresaId)
            ->where('categoria', 'indirecta');
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $hasVig = $db->fieldExists('vigente_uf', 'equity_pasivos');
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($hasVig && $r['vigente_uf'] !== null && $r['vigente_uf'] !== '') {
                $sum += (float) $r['vigente_uf'];
            } elseif ($hasVig && ! empty($r['vigente_clp']) && $ufRate > 0) {
                $sum += (float) $r['vigente_clp'] / $ufRate;
            } elseif ($hasVig && $r['total_credito_uf'] !== null && $r['total_credito_uf'] !== '') {
                $sum += (float) $r['total_credito_uf'];
            } elseif ($hasVig && ! empty($r['total_credito_clp']) && $ufRate > 0) {
                $sum += (float) $r['total_credito_clp'] / $ufRate;
            } elseif ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
            } elseif (! empty($r['saldo_clp']) && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
            }
        }

        return $sum;
    }

    /** Suma cupos disponibles (directo + indirecto) en UF. */
    private function sumLineasDisponibles(string $empresaId, string $personaId, float $ufRate): float
    {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return 0.0;
        }
        $db = db_connect();
        $builder = $db->table('equity_pasivos')
            ->where('empresa_id', $empresaId)
            ->where('categoria', 'linea');
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $hasDirUf = $db->fieldExists('monto_directo_uf', 'equity_pasivos');
        $sum = 0.0;
        foreach ($rows as $r) {
            $dir = null;
            $ind = null;
            if ($hasDirUf) {
                if ($r['monto_directo_uf'] !== null && $r['monto_directo_uf'] !== '') {
                    $dir = (float) $r['monto_directo_uf'];
                } elseif (! empty($r['monto_directo_clp']) && $ufRate > 0) {
                    $dir = (float) $r['monto_directo_clp'] / $ufRate;
                }
                if ($r['monto_indirecto_uf'] !== null && $r['monto_indirecto_uf'] !== '') {
                    $ind = (float) $r['monto_indirecto_uf'];
                } elseif (! empty($r['monto_indirecto_clp']) && $ufRate > 0) {
                    $ind = (float) $r['monto_indirecto_clp'] / $ufRate;
                }
            }
            if ($dir !== null || $ind !== null) {
                $sum += (float) ($dir ?? 0) + (float) ($ind ?? 0);
            } elseif ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
            } elseif (! empty($r['saldo_clp']) && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
            }
        }

        return $sum;
    }
}
