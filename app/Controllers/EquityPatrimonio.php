<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentStorage;
use App\Libraries\EquityAcl;
use App\Libraries\EquityActivoBitacora;
use App\Libraries\EquityArriendoSync;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityEgresosProyeccion;
use App\Libraries\EquityUf;
use App\Libraries\MembershipAuth;
use App\Models\EquityBienRaizModel;
use App\Models\EquityFichaModel;
use App\Models\EquityIngresoContratoModel;
use App\Models\EquityOtroActivoModel;
use App\Models\EquityPasivoModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityRespaldoModel;
use App\Models\EquitySociedadModel;
use App\Models\EquityVehiculoModel;
use App\Models\DocumentoModel;

/**
 * Menús patrimoniales Equity: resumen, activos, pasivos, ingresos, egresos, respaldos.
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

    /** Persona de la sesión (propia o representada). */
    private function sessionPersonaId(): string
    {
        return (string) ((new MembershipAuth())->equityPersonaId() ?? '');
    }

    /**
     * Persona activa + mapa de personas visibles (solo la de sesión, o vacías para admin sin ficha).
     *
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function personaContexto(array $memb): array
    {
        $personaId = $this->sessionPersonaId();
        $auth      = new MembershipAuth();
        $uid       = (string) ($auth->usuarioId() ?? '');
        $personas  = $uid !== ''
            ? (new \App\Libraries\EquityOrg())->personasDeclarables($uid, $memb['empresa_id'])
            : [];
        if ($personaId === '' && count($personas) === 1) {
            $personaId = (string) $personas[0]['id'];
            $auth->setEquityPersonaId($personaId);
        }

        return [$personaId, $personas];
    }

    public function resumen()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);

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
            'canManage' => EquityAcl::canDeclare($memb),
        ]);
    }

    /**
     * Reporte tipo Balance: activos / pasivos / patrimonio + ingresos vs egresos mensuales.
     */
    public function reporteBalance()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $personaNombre = null;
        if ($personaId !== '' && isset($personaMap[$personaId])) {
            $p = $personaMap[$personaId];
            $personaNombre = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        }

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        $totalesUf = [
            'bienes_raices'     => $this->sumUfCol($empresaId, 'equity_bienes_raices', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'vehiculos'         => $this->sumUfCol($empresaId, 'equity_vehiculos', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'sociedades'        => $this->sumUfCol($empresaId, 'equity_sociedades', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'otros_activos'     => $this->sumUfCol($empresaId, 'equity_otros_activos', 'saldo_uf', 'saldo_clp', $personaId, $ufRate),
            'pasivos_inm'       => $this->sumPasivoCategoria($empresaId, 'inmueble', $personaId, $ufRate),
            'pasivos_circ'      => $this->sumPasivoCategoria($empresaId, 'circulante', $personaId, $ufRate),
            'pasivos_otro'      => $this->sumPasivoCategoria($empresaId, 'otro', $personaId, $ufRate),
            'pasivos_indirecta' => $this->sumDeudaIndirecta($empresaId, $personaId, $ufRate),
            'pasivos_lineas'    => $this->sumLineasDisponibles($empresaId, $personaId, $ufRate),
        ];
        $totalesUf['activos']    = $totalesUf['bienes_raices'] + $totalesUf['vehiculos'] + $totalesUf['sociedades'] + $totalesUf['otros_activos'];
        $totalesUf['pasivos']    = $totalesUf['pasivos_inm'] + $totalesUf['pasivos_circ'] + $totalesUf['pasivos_otro'] + $totalesUf['pasivos_indirecta'];
        $totalesUf['patrimonio'] = $totalesUf['activos'] - $totalesUf['pasivos'];

        $totales = [];
        foreach ($totalesUf as $k => $uf) {
            $totales[$k] = round($uf * $ufRate, 2);
        }

        // Ingresos mensuales: fichas (última por persona) + cánones de arriendo
        $fq = model(EquityFichaModel::class)
            ->orderBy('fecha_corte', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $fq->where('persona_id', $personaId);
        } else {
            $fq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $ingresosDetalle = [];
        $seenPersona     = [];
        $totalIngresosFichas = 0.0;
        foreach ($fq->findAll() as $ing) {
            $pid = (string) ($ing['persona_id'] ?? '');
            if ($pid !== '' && isset($seenPersona[$pid])) {
                continue; // solo última ficha por persona
            }
            $seenPersona[$pid] = true;
            $v = $ing['ingreso_mensual_total'] ?? $ing['renta_liquida'] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $ingresosDetalle[] = [
                'concepto' => 'Ficha de ingreso' . (! empty($ing['fecha_corte']) ? ' · corte ' . $ing['fecha_corte'] : ''),
                'persona'  => $personaMap[$pid] ?? null,
                'monto'    => (float) $v,
            ];
            $totalIngresosFichas += (float) $v;
        }

        $bq = model(EquityBienRaizModel::class)
            ->orderBy('direccion', 'ASC');
        $this->soloBienesActivos($bq);
        if ($personaId !== '') {
            $bq->where('persona_id', $personaId);
        } else {
            $bq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $totalArriendos = 0.0;
        $arriendosDetalle = [];
        foreach ($bq->findAll() as $br) {
            $canon = $br['canon_mensual_clp'] ?? null;
            if (($canon === null || $canon === '') && ! empty($br['canon_mensual_uf']) && $ufRate > 0) {
                $canon = round((float) $br['canon_mensual_uf'] * $ufRate, 2);
            }
            if ($canon === null || $canon === '' || (float) $canon <= 0) {
                continue;
            }
            $arriendosDetalle[] = [
                'concepto' => trim((string) ($br['direccion'] ?? $br['descripcion'] ?? 'Arriendo'))
                    . (! empty($br['arrendatario']) ? ' · ' . $br['arrendatario'] : ''),
                'monto'    => (float) $canon,
            ];
            $totalArriendos += (float) $canon;
        }

        // Si no hay fichas, sumar rentas laborales de la ficha personal
        $totalLaboral = 0.0;
        if ($totalIngresosFichas <= 0) {
            foreach ($personas as $p) {
                if ($personaId !== '' && $p['id'] !== $personaId) {
                    continue;
                }
                $s = (float) ($p['renta_liquida_persona'] ?? 0) + (float) ($p['renta_liquida_2'] ?? 0);
                if ($s <= 0) {
                    continue;
                }
                $ingresosDetalle[] = [
                    'concepto' => 'Antecedentes laborales'
                        . (! empty($p['nombre_empleador']) ? ' · ' . $p['nombre_empleador'] : ''),
                    'persona'  => $p,
                    'monto'    => $s,
                ];
                $totalLaboral += $s;
            }
        }

        $totalIngresos = $totalIngresosFichas + $totalLaboral + $totalArriendos;

        // Egresos: cuotas mensuales de créditos (excluye líneas)
        $pq = model(EquityPasivoModel::class)
            ->whereIn('categoria', ['inmueble', 'circulante', 'otro', 'indirecta'])
            ->orderBy('banco_descripcion', 'ASC');
        if ($personaId !== '') {
            $pq->where('persona_id', $personaId);
        } else {
            $pq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $egresosDetalle = [];
        $totalEgresos   = 0.0;
        foreach ($pq->findAll() as $row) {
            // Análisis siempre en $: UF → pesos con tasa vigente.
            $cuota = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
            if ($cuota === null || (float) $cuota <= 0) {
                continue;
            }
            $egresosDetalle[] = [
                'concepto' => trim(
                    (string) ($row['banco_descripcion'] ?? 'Crédito')
                    . (! empty($row['numero_credito']) ? ' · Nº ' . $row['numero_credito'] : '')
                    . ' · ' . $this->tipoCreditoSituacion($row)
                ),
                'monto'    => (float) $cuota,
            ];
            $totalEgresos += (float) $cuota;
        }

        return view('equity/reporte_balance', [
            'title'             => 'Reporte Balance',
            'memb'              => $memb,
            'equityNav'         => 'resumen',
            'personas'          => $personas,
            'personaId'         => $personaId,
            'personaNombre'     => $personaNombre,
            'totales'           => $totales,
            'totalesUf'         => $totalesUf,
            'ufVigente'         => $ufVigente,
            'ingresosDetalle'   => $ingresosDetalle,
            'arriendosDetalle'  => $arriendosDetalle,
            'egresosDetalle'    => $egresosDetalle,
            'totalIngresos'     => $totalIngresos,
            'totalArriendos'    => $totalArriendos,
            'totalEgresos'      => $totalEgresos,
            'resultadoMensual'  => $totalIngresos - $totalEgresos,
            'canManage'         => EquityAcl::canDeclare($memb),
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

    public function reporteActivo(string $seccion)
    {
        return $this->reporteSeccion($this->claveActivo($seccion));
    }

    public function reportePasivo(string $seccion)
    {
        return $this->reporteSeccion($this->clavePasivo($seccion));
    }

    public function reporteGrupoActivos()
    {
        return $this->reporteGrupo('activos', [
            'bienes-raices', 'vehiculos', 'sociedades', 'otros-activos',
        ]);
    }

    public function reporteGrupoPasivos()
    {
        return $this->reporteGrupo('pasivos', [
            'pasivos-inmuebles', 'pasivos-circulantes', 'otros-pasivos', 'pasivos-indirecta', 'pasivos-lineas',
        ]);
    }

    public function reporteIngresos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        // Reutiliza la vista de situación financiera como reportería de ingresos/carga.
        return redirect()->to('/equity/situacion-financiera');
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

    /**
     * Baja lógica de un activo (hoy: bienes raíces). Conserva IDs / bitácora.
     */
    public function eliminarActivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        // Misma barra que editar / agregar (declarante o admin del grupo).
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/activos/' . $seccion)->with('error', 'Sin permiso para dar de baja.');
        }

        $clave = $this->claveActivo($seccion);
        if ($clave !== 'bienes-raices') {
            return redirect()->to('/equity/activos/' . $seccion)
                ->with('error', 'La baja está habilitada por ahora solo en Bienes raíces.');
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $empresaId = (string) $memb['empresa_id'];
        $row       = $this->findRegistro($meta, $empresaId, $id);
        if ($row === null) {
            return redirect()->to('/equity/activos/bienes-raices')->with('error', 'Bien raíz no encontrado.');
        }

        $db = db_connect();
        if (! $db->fieldExists('activo', 'equity_bienes_raices')) {
            return redirect()->to('/equity/activos/bienes-raices')
                ->with('error', 'Falta la migración de baja lógica (activo) en bienes raíces.');
        }

        if (array_key_exists('activo', $row) && (int) ($row['activo'] ?? 1) === 0) {
            return redirect()->to('/equity/activos/bienes-raices')
                ->with('success', 'Este bien raíz ya estaba dado de baja.');
        }

        model(EquityBienRaizModel::class)->update($id, [
            'activo'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $cerrados = EquityArriendoSync::cerrarContratosDelBien($id);
        if ($cerrados > 0) {
            $pid = (string) ($row['persona_id'] ?? '');
            if ($pid !== '') {
                $this->sincronizarFichaDesdeContratos($empresaId, $pid, (string) ($memb['usuario_id'] ?? ''));
            }
        }
        $extra = $cerrados > 0
            ? ' Se marcaron ' . $cerrados . ' contrato(s) de arriendo como vencido(s).'
            : '';

        return redirect()->to('/equity/activos/bienes-raices')
            ->with('success', 'Bien raíz dado de baja. Queda fuera de listados y totales; el historial se conserva.' . $extra);
    }

    /**
     * Onboarding: vuelca arrendatario/cánon de bienes raíces activos a contratos de Inversiones.
     * Luego la gestión diaria es sobre el contrato (vigente → vencido → nuevo).
     */
    public function syncArriendosDesdeBienes(string $fuente)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if ($fuente !== 'inversiones') {
            return redirect()->to('/equity/ingresos/' . $fuente)
                ->with('error', 'La sincronización desde bienes raíces aplica solo a Inversiones.');
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/ingresos/inversiones')->with('error', 'Sin permiso para sincronizar.');
        }

        $personaId = $this->sessionPersonaId();
        if ($personaId === '') {
            return redirect()->to('/equity/ingresos/inversiones')
                ->with('error', 'No hay persona activa en la sesión.');
        }

        $empresaId = (string) $memb['empresa_id'];
        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $stats     = EquityArriendoSync::sincronizarDesdeBienes(
            $empresaId,
            $personaId,
            (string) ($memb['usuario_id'] ?? ''),
            $ufRate
        );
        $this->sincronizarFichaDesdeContratos($empresaId, $personaId, (string) ($memb['usuario_id'] ?? ''));

        $msg = sprintf(
            'Contratos actualizados desde bienes raíces: %d creados, %d actualizados, %d enlazados, %d omitidos.',
            $stats['created'],
            $stats['updated'],
            $stats['linked'],
            $stats['skipped']
        );

        return redirect()->to('/equity/ingresos/inversiones?persona_id=' . rawurlencode($personaId))
            ->with('success', $msg);
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
        [$personaId, $personas] = $this->personaContexto($memb);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        $q = model(EquityPasivoModel::class)
            ->whereIn('categoria', ['inmueble', 'circulante', 'otro', 'indirecta'])
            ->orderBy('banco_descripcion', 'ASC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
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
            $row['cuota_show'] = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
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

        $bq = model(EquityBienRaizModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('direccion', 'ASC');
        $this->soloBienesActivos($bq);
        if ($personaId !== '') {
            $bq->where('persona_id', $personaId);
        }
        $arriendos = [];
        $totalArriendos = 0.0;
        foreach ($bq->findAll() as $br) {
            $canon = $br['canon_mensual_clp'] ?? null;
            if (($canon === null || $canon === '') && ! empty($br['canon_mensual_uf']) && $ufRate > 0) {
                $canon = round((float) $br['canon_mensual_uf'] * $ufRate, 2);
            }
            if ($canon === null || $canon === '' || (float) $canon <= 0) {
                continue;
            }
            $br['persona'] = $personaMap[$br['persona_id']] ?? null;
            $br['canon_show'] = (float) $canon;
            $arriendos[] = $br;
            $totalArriendos += (float) $canon;
        }

        $ingresoMensual = null;
        $totalFichasIngresos = 0.0;
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
                $totalFichasIngresos += (float) $v;
            }
        }
        // Total reportado = fichas + cánones de bienes raíces (sin doble contar rentas laborales)
        $totalIngresosReportados = $totalFichasIngresos + $totalArriendos;
        if ($personaId !== '' && $totalArriendos > 0) {
            $ingresoMensual = ($ingresoMensual !== null ? (float) $ingresoMensual : 0.0) + $totalArriendos;
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
            'arriendos'               => $arriendos,
            'totalArriendos'          => $totalArriendos,
            'totalFichasIngresos'     => $totalFichasIngresos,
            'personas'                => $personas,
            'personaId'               => $personaId,
            'ufVigente'               => $ufVigente,
            'ingresoMensual'          => $ingresoMensual,
            'totalIngresosReportados' => $totalIngresosReportados,
            'totalCuotas'             => $totalCuotas,
            'canManage'               => EquityAcl::canDeclare($memb),
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
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/situacion-financiera')->with('error', 'Sin permiso para editar cuotas.');
        }

        $cuotas = $this->request->getPost('cuota');
        if (! is_array($cuotas)) {
            return redirect()->to('/equity/situacion-financiera')->with('error', 'Sin datos de cuotas.');
        }

        $personaId = $this->sessionPersonaId();
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
        [$personaId, $personas] = $this->personaContexto($memb);
        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        // Última ficha por persona (si filtra, solo esa; si no, una por cada persona)
        $fichasBase = [];
        if ($personaId !== '') {
            $f = $this->ultimaFicha($empresaId, $personaId);
            if (is_array($f)) {
                $fichasBase[] = $f;
            }
        } else {
            $seen = [];
            foreach (model(EquityFichaModel::class)
                ->where('empresa_id', $empresaId)
                ->orderBy('fecha_corte', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->findAll() as $f) {
                $pid = (string) ($f['persona_id'] ?? '');
                if ($pid === '' || isset($seen[$pid])) {
                    continue;
                }
                $seen[$pid] = true;
                $fichasBase[] = $f;
            }
        }
        $ficha = $fichasBase[0] ?? null;

        $fuentes = EquityCatalogo::fuentesIngreso();
        $totalesFuente = [];
        $totalGeneral = 0.0;
        // Preferir suma de contratos vigentes (equivalente mensual)
        $cq = model(EquityIngresoContratoModel::class)
            ->where('estado', 'vigente');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $contratosVigentes = $cq->findAll();
        $sumasContrato = [];
        foreach ($fuentes as $clave => $meta) {
            $sumasContrato[$clave] = 0.0;
        }
        $subtipoFuente = [];
        foreach ($fuentes as $clave => $meta) {
            foreach ($meta['campos'] as $c) {
                $subtipoFuente[$c['subtipo']] = $clave;
            }
        }
        foreach ($contratosVigentes as $ct) {
            $fClave = $subtipoFuente[$ct['subtipo'] ?? ''] ?? ($ct['fuente'] ?? null);
            if ($fClave === null || ! isset($sumasContrato[$fClave])) {
                continue;
            }
            $sumasContrato[$fClave] += EquityCatalogo::montoMensualEquivalente(
                isset($ct['monto_contrato_clp']) ? (float) $ct['monto_contrato_clp'] : null,
                $ct['periodicidad'] ?? 'mensual'
            );
        }

        foreach ($fuentes as $clave => $meta) {
            $sum = (float) ($sumasContrato[$clave] ?? 0);
            if ($sum <= 0) {
                foreach ($fichasBase as $fRow) {
                    foreach ($meta['campos'] as $campo) {
                        $v = $fRow[$campo['key']] ?? null;
                        if ($v !== null && $v !== '') {
                            $sum += (float) $v;
                        }
                    }
                }
            }
            if ($clave === 'inversiones' && $sum <= 0) {
                $sum += $this->sumCanonArriendos($empresaId, $personaId, $ufRate);
            }
            $totalesFuente[$clave] = $sum;
            $totalGeneral += $sum;
        }

        $q = model(EquityFichaModel::class)
            ->orderBy('fecha_corte', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $fichas = $q->findAll();
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($fichas as &$f) {
            $f['persona'] = $personaMap[$f['persona_id']] ?? null;
        }
        unset($f);

        return view('equity/ingresos', [
            'title'          => 'Ingresos',
            'memb'           => $memb,
            'equityNav'      => 'ingresos',
            'fuentes'        => $fuentes,
            'totalesFuente'  => $totalesFuente,
            'totalGeneral'   => $totalGeneral,
            'ficha'          => $ficha,
            'fichas'         => $fichas,
            'personas'       => $personas,
            'personaId'      => $personaId,
            'ufVigente'      => $ufVigente,
            'canManage'      => EquityAcl::canDeclare($memb),
            'success'        => session()->getFlashdata('success'),
            'error'          => session()->getFlashdata('error'),
        ]);
    }

    /**
     * Hub Egresos: totales mensuales por tipo de pasivo (pestañas como Ingresos).
     */
    public function egresos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $fuentes   = EquityCatalogo::fuentesEgreso();

        $totalesFuente = [];
        $conteosFuente = [];
        $totalGeneral  = 0.0;

        foreach ($fuentes as $clave => $meta) {
            $q = model(EquityPasivoModel::class)
                ->where('categoria', $meta['categoria'])
                ->orderBy('banco_descripcion', 'ASC');
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
            }
            $rows = $q->findAll();
            $conteosFuente[$clave] = count($rows);
            $sum = 0.0;
            if ($clave !== 'lineas') {
                foreach ($rows as $row) {
                    $cuota = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
                    if ($cuota !== null) {
                        $sum += $cuota;
                    }
                }
            }
            $totalesFuente[$clave] = $sum;
            $totalGeneral += $sum;
        }

        return view('equity/egresos', [
            'title'          => 'Egresos',
            'memb'           => $memb,
            'equityNav'      => 'egresos',
            'fuentes'        => $fuentes,
            'totalesFuente'  => $totalesFuente,
            'conteosFuente'  => $conteosFuente,
            'totalGeneral'   => $totalGeneral,
            'personas'       => $personas,
            'personaId'      => $personaId,
            'ufVigente'      => $ufVigente,
            'canManage'      => EquityAcl::canDeclare($memb),
            'success'        => session()->getFlashdata('success'),
            'error'          => session()->getFlashdata('error'),
        ]);
    }

    /**
     * Egresos por fuente/pasivo: listado + proyección de vencimientos.
     */
    public function egresosFuente(string $fuente)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $fuentes = EquityCatalogo::fuentesEgreso();
        if (! isset($fuentes[$fuente])) {
            return redirect()->to('/equity/egresos')->with('error', 'Tipo de egreso no válido.');
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $meta      = $fuentes[$fuente];
        $meses     = 12;

        $q = model(EquityPasivoModel::class)
            ->where('categoria', $meta['categoria'])
            ->orderBy('banco_descripcion', 'ASC')
            ->orderBy('numero_credito', 'ASC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $items = $q->findAll();

        $totalFuente = 0.0;
        $proyeccionMeses = [];
        $desarrollos = [];

        foreach ($items as &$row) {
            $cuota = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
            $row['_cuota_clp'] = $cuota;
            $row['_cuota_uf']  = EquityEgresosProyeccion::cuotaUf($row, $ufRate);
            $row['_moneda']    = EquityEgresosProyeccion::moneda($row);
            if ($fuente !== 'lineas' && $cuota !== null) {
                $totalFuente += $cuota;
            }

            $cuotas = EquityEgresosProyeccion::proyectar($row, $ufRate, $meses);
            if (in_array($fuente, ['hipotecarios', 'circulantes', 'otros'], true) && $cuotas !== []) {
                $desarrollos[] = [
                    'label'  => (string) ($row['banco_descripcion'] ?? 'Crédito'),
                    'numero' => $row['numero_credito'] ?? null,
                    'moneda' => EquityEgresosProyeccion::moneda($row),
                    'cuotas' => $cuotas,
                ];
            }
            foreach ($cuotas as $c) {
                $mesKey = substr((string) $c['fecha'], 0, 7);
                if (! isset($proyeccionMeses[$mesKey])) {
                    $proyeccionMeses[$mesKey] = ['total' => 0.0, 'items' => []];
                }
                $label = trim(
                    (string) ($row['banco_descripcion'] ?? 'Crédito')
                    . (! empty($row['numero_credito']) ? ' · ' . $row['numero_credito'] : '')
                );
                $proyeccionMeses[$mesKey]['items'][] = [
                    'label'       => $label,
                    'fecha'       => $c['fecha'],
                    'dividendo_n' => $c['dividendo_n'] ?? null,
                    'cuota_clp'   => $c['cuota_clp'] ?? null,
                ];
                $proyeccionMeses[$mesKey]['total'] += (float) ($c['cuota_clp'] ?? 0);
            }
        }
        unset($row);
        ksort($proyeccionMeses);

        return view('equity/egresos_fuente', [
            'title'            => 'Egresos · ' . $meta['label'],
            'memb'             => $memb,
            'equityNav'        => 'egresos-' . $fuente,
            'fuente'           => $fuente,
            'meta'             => $meta,
            'items'            => $items,
            'totalFuente'      => $totalFuente,
            'proyeccionMeses'  => $proyeccionMeses,
            'desarrollos'      => $desarrollos,
            'mesesProyeccion'  => $meses,
            'personas'         => $personas,
            'personaId'        => $personaId,
            'ufVigente'        => $ufVigente,
            'canManage'        => EquityAcl::canDeclare($memb),
            'success'          => session()->getFlashdata('success'),
            'error'            => session()->getFlashdata('error'),
        ]);
    }

    public function ingresosFuente(string $fuente)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $fuentes = EquityCatalogo::fuentesIngreso();
        if (! isset($fuentes[$fuente])) {
            return redirect()->to('/equity/ingresos')->with('error', 'Fuente de ingreso no válida.');
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $meta      = $fuentes[$fuente];
        $ficha     = $this->ultimaFicha($empresaId, $personaId);
        $editId    = trim((string) $this->request->getGet('edit'));

        $cq = model(EquityIngresoContratoModel::class)
            ->where('fuente', $fuente)
            ->orderBy('estado', 'ASC')
            ->orderBy('fecha_inicio_contrato', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $contratos = $cq->findAll();
        $editRow = null;
        foreach ($contratos as &$c) {
            $c['monto_mensual'] = EquityCatalogo::montoMensualEquivalente(
                isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                $c['periodicidad'] ?? 'mensual'
            );
            if ($editId !== '' && ($c['id'] ?? '') === $editId) {
                $editRow = $c;
            }
        }
        unset($c);

        $arriendos = [];
        $totalCanon = 0.0;
        $bienesOpciones = [];
        $contratosPorBien = [];
        if ($fuente === 'inversiones') {
            foreach ($contratos as $c) {
                $bid = (string) ($c['bien_raiz_id'] ?? '');
                if ($bid !== '') {
                    $contratosPorBien[$bid][] = $c;
                }
            }
            $bq = model(EquityBienRaizModel::class)
                ->orderBy('direccion', 'ASC');
            $this->soloBienesActivos($bq);
            if ($personaId !== '') {
                $bq->where('persona_id', $personaId);
            } else {
                $bq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
            }
            foreach ($bq->findAll() as $br) {
                $bienesOpciones[] = $br;
                $canon = EquityArriendoSync::canonClp($br, $ufRate);
                if ($canon <= 0) {
                    continue;
                }
                $br['canon_show'] = $canon;
                $bid = (string) ($br['id'] ?? '');
                $vinc = $contratosPorBien[$bid] ?? [];
                $br['contrato_vigente'] = null;
                foreach ($vinc as $vc) {
                    if (($vc['estado'] ?? '') === 'vigente') {
                        $br['contrato_vigente'] = $vc;
                        break;
                    }
                }
                if ($br['contrato_vigente'] === null && $vinc !== []) {
                    $br['contrato_vigente'] = $vinc[0];
                }
                $arriendos[] = $br;
                $totalCanon += $canon;
            }
            // Enriquecer contratos con dirección del bien vinculado
            $bienMap = [];
            foreach ($bienesOpciones as $b) {
                $bienMap[(string) $b['id']] = $b;
            }
            foreach ($contratos as &$c) {
                $bid = (string) ($c['bien_raiz_id'] ?? '');
                $c['bien'] = $bid !== '' ? ($bienMap[$bid] ?? null) : null;
            }
            unset($c);
        }

        return view('equity/ingresos_fuente', [
            'title'          => $meta['label'],
            'memb'           => $memb,
            'equityNav'      => 'ingresos-' . $fuente,
            'fuente'         => $fuente,
            'meta'           => $meta,
            'ficha'          => $ficha,
            'contratos'      => $contratos,
            'editRow'        => $editRow,
            'personas'       => $personas,
            'personaId'      => $personaId,
            'arriendos'      => $arriendos,
            'bienesOpciones' => $bienesOpciones,
            'totalCanon'     => $totalCanon,
            'ufVigente'      => $ufVigente,
            'canManage'      => EquityAcl::canDeclare($memb),
            'success'        => session()->getFlashdata('success'),
            'error'          => session()->getFlashdata('error'),
        ]);
    }

    public function guardarIngresosFuente(string $fuente)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Sin permiso para editar ingresos.');
        }

        $fuentes = EquityCatalogo::fuentesIngreso();
        if (! isset($fuentes[$fuente])) {
            return redirect()->to('/equity/ingresos')->with('error', 'Fuente de ingreso no válida.');
        }

        $personaId = $this->sessionPersonaId();
        if ($personaId === '') {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'No hay persona activa en la sesión. Contacta a la matriz o elige a quién representas.');
        }

        $empresaId = $memb['empresa_id'];
        $persona   = $this->personaActivaAutorizada($memb, $personaId);
        if (! is_array($persona)) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Persona no encontrada.');
        }

        $money = static function ($raw): ?string {
            $v = trim((string) $raw);
            if ($v === '') {
                return null;
            }
            $v = str_replace(['$', ' '], '', $v);
            if (str_contains($v, ',') && str_contains($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (str_contains($v, ',')) {
                $v = str_replace(',', '.', $v);
            }
            if (! is_numeric($v)) {
                return null;
            }

            return number_format((float) $v, 2, '.', '');
        };

        $meta = $fuentes[$fuente];
        $patch = [];
        foreach ($meta['campos'] as $campo) {
            $patch[$campo['key']] = $money($this->request->getPost($campo['key']));
        }

        // Sync arriendos desde bienes raíces si se pidió
        if ($fuente === 'inversiones' && $this->request->getPost('sync_arriendos') === '1') {
            $ufVigente = EquityUf::vigente($empresaId);
            $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
            $canon = $this->sumCanonArriendos($empresaId, $personaId, $ufRate);
            $patch['ingreso_arriendos'] = $canon > 0 ? number_format($canon, 2, '.', '') : null;
        }

        $ficha = $this->ultimaFicha($empresaId, $personaId);
        helper('id');

        if (is_array($ficha)) {
            // Recalcular total con merge de campos existentes
            $merged = array_merge($ficha, $patch);
            $patch['ingreso_mensual_total'] = $this->sumIngresosFicha($merged);
            $patch['updated_at'] = date('Y-m-d H:i:s');
            model(EquityFichaModel::class)->update($ficha['id'], $patch);
            $msg = 'Ingresos de ' . $meta['label'] . ' actualizados.';
        } else {
            $row = [
                'id'            => new_id(),
                'empresa_id'    => $empresaId,
                'persona_id'    => $personaId,
                'fecha_corte'   => date('Y-m-d'),
                'moneda'        => 'CLP',
                'creado_por_id' => $memb['usuario_id'],
            ];
            foreach ($patch as $k => $v) {
                $row[$k] = $v;
            }
            $row['ingreso_mensual_total'] = $this->sumIngresosFicha($row);
            model(EquityFichaModel::class)->insert($row);
            $msg = 'Ficha de ingresos creada con ' . $meta['label'] . '.';
        }

        return redirect()->to('/equity/ingresos/' . $fuente . '?persona_id=' . rawurlencode($personaId))
            ->with('success', $msg);
    }

    public function guardarContratoIngreso(string $fuente, ?string $id = null)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Sin permiso para editar contratos.');
        }

        $fuentes = EquityCatalogo::fuentesIngreso();
        if (! isset($fuentes[$fuente])) {
            return redirect()->to('/equity/ingresos')->with('error', 'Fuente de ingreso no válida.');
        }

        $personaId = $this->sessionPersonaId();
        $redirect  = '/equity/ingresos/' . $fuente . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : '');
        if ($personaId === '') {
            return redirect()->to($redirect)->with('error', 'Selecciona una persona.');
        }

        $empresaId = $memb['empresa_id'];
        $persona   = $this->personaActivaAutorizada($memb, $personaId);
        if (! is_array($persona)) {
            return redirect()->to($redirect)->with('error', 'Persona no encontrada.');
        }

        $subtipos = [];
        foreach ($fuentes[$fuente]['campos'] as $c) {
            $subtipos[$c['subtipo']] = $c;
        }
        $subtipo = trim((string) $this->request->getPost('subtipo'));
        if (! isset($subtipos[$subtipo])) {
            return redirect()->to($redirect)->with('error', 'Tipo de ingreso no válido.');
        }

        $nombre = trim((string) $this->request->getPost('contraparte_nombre'));
        if ($nombre === '') {
            return redirect()->to($redirect)->with('error', 'Nombre / razón social de la contraparte es obligatorio.');
        }

        $periodicidades = EquityCatalogo::opcionesPeriodicidadIngreso();
        $periodicidad   = trim((string) $this->request->getPost('periodicidad')) ?: 'mensual';
        if (! isset($periodicidades[$periodicidad])) {
            $periodicidad = 'mensual';
        }
        $estados = EquityCatalogo::opcionesEstadoContratoIngreso();
        $estado  = trim((string) $this->request->getPost('estado')) ?: 'vigente';
        if (! isset($estados[$estado])) {
            $estado = 'vigente';
        }

        $money = static function ($raw): ?string {
            $v = trim((string) $raw);
            if ($v === '') {
                return null;
            }
            $v = str_replace(['$', ' '], '', $v);
            if (str_contains($v, ',') && str_contains($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (str_contains($v, ',')) {
                $v = str_replace(',', '.', $v);
            }
            if (! is_numeric($v)) {
                return null;
            }

            return number_format((float) $v, 2, '.', '');
        };

        $fecha    = trim((string) $this->request->getPost('fecha_inicio_contrato'));
        $fechaVen = trim((string) $this->request->getPost('fecha_vencimiento'));
        $rut      = trim((string) $this->request->getPost('contraparte_rut'));
        $notas    = trim((string) $this->request->getPost('notas'));
        $monto    = $money($this->request->getPost('monto_contrato_clp'));
        $bienRaizId = trim((string) $this->request->getPost('bien_raiz_id'));

        if ($fechaVen !== '' && $fecha !== '' && $fechaVen < $fecha) {
            return redirect()->to($redirect)->with('error', 'La fecha de vencimiento no puede ser anterior al inicio del contrato.');
        }

        $db = db_connect();
        $hasBienLink = $db->fieldExists('bien_raiz_id', 'equity_ingresos_contratos');
        if ($hasBienLink && $bienRaizId !== '' && $subtipo === 'arriendos') {
            $bienOk = model(EquityBienRaizModel::class)
                ->where('id', $bienRaizId)
                ->where('persona_id', $personaId)
                ->first();
            if (! is_array($bienOk)) {
                return redirect()->to($redirect)->with('error', 'El bien raíz seleccionado no es válido para esta persona.');
            }
        } elseif ($subtipo !== 'arriendos') {
            $bienRaizId = '';
        }

        helper('id');
        $isEdit = $id !== null && $id !== '';
        $rowExisting = null;
        if ($isEdit) {
            $rowExisting = model(EquityIngresoContratoModel::class)
                ->where('id', $id)
                ->where('empresa_id', $empresaId)
                ->where('fuente', $fuente)
                ->first();
            if (! is_array($rowExisting)) {
                return redirect()->to($redirect)->with('error', 'Contrato no encontrado.');
            }
        }

        $data = [
            'persona_id'            => $personaId,
            'fuente'                => $fuente,
            'subtipo'               => $subtipo,
            'contraparte_rut'       => $rut !== '' ? $rut : null,
            'contraparte_nombre'    => $nombre,
            'fecha_inicio_contrato' => $fecha !== '' ? $fecha : null,
            'fecha_vencimiento'     => $fechaVen !== '' ? $fechaVen : null,
            'periodicidad'          => $periodicidad,
            'monto_contrato_clp'    => $monto,
            'estado'                => $estado,
            'notas'                 => $notas !== '' ? $notas : null,
        ];
        if ($hasBienLink) {
            $data['bien_raiz_id'] = $bienRaizId !== '' ? $bienRaizId : null;
        }

        $file = $this->request->getFile('contrato_archivo');
        if ($file !== null && $file->isValid() && $file->getError() === UPLOAD_ERR_OK) {
            if ($file->getSize() > 15 * 1024 * 1024) {
                return redirect()->to($redirect)->with('error', 'El contrato supera 15 MB.');
            }
            $binary = file_get_contents($file->getTempName());
            if ($binary === false) {
                return redirect()->to($redirect)->with('error', 'No se pudo leer el archivo del contrato.');
            }
            $docId = $isEdit ? (string) $id : new_id();
            try {
                $saved = DocumentStorage::save(
                    $empresaId,
                    $docId,
                    $file->getClientName(),
                    $binary,
                    'equity'
                );
                $data['contrato_archivo_nombre'] = $file->getClientName();
                $data['contrato_ruta_relativa']  = $saved['ruta_relativa'];
                $data['contrato_mime']           = $file->getMimeType() ?: 'application/octet-stream';
                $data['contrato_tamano_bytes']   = $saved['tamano_bytes'];
            } catch (\Throwable $e) {
                log_message('error', 'Equity contrato ingreso upload failed: {msg}', ['msg' => $e->getMessage()]);

                return redirect()->to($redirect)->with('error', 'No se pudo guardar el contrato adjunto.');
            }
        }

        if ($isEdit) {
            model(EquityIngresoContratoModel::class)->update($id, $data);
            $msg = 'Contrato / contraparte actualizado.';
        } else {
            $newId = new_id();
            // Si ya subimos archivo con new_id provisional distinto, re-asociar: usamos newId consistente
            if (! empty($data['contrato_ruta_relativa']) && isset($docId) && $docId !== $newId) {
                // archivo ya guardado bajo $docId; mantener esa ruta e id = docId
                $newId = $docId;
            }
            $data['id']            = $newId;
            $data['empresa_id']    = $empresaId;
            $data['creado_por_id'] = $memb['usuario_id'];
            model(EquityIngresoContratoModel::class)->insert($data);
            $msg = 'Contrato / contraparte agregado.';
        }

        $this->sincronizarFichaDesdeContratos($empresaId, $personaId, $memb['usuario_id']);

        return redirect()->to($redirect)->with('success', $msg);
    }

    public function eliminarContratoIngreso(string $fuente, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Sin permiso para eliminar.');
        }

        $empresaId = $memb['empresa_id'];
        $row = model(EquityIngresoContratoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->where('fuente', $fuente)
            ->first();
        $personaId = is_array($row) ? (string) ($row['persona_id'] ?? '') : '';
        $redirect  = '/equity/ingresos/' . $fuente . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : '');
        if (! is_array($row)) {
            return redirect()->to($redirect)->with('error', 'Contrato no encontrado.');
        }

        if (! empty($row['contrato_ruta_relativa'])) {
            try {
                DocumentStorage::delete((string) $row['contrato_ruta_relativa']);
            } catch (\Throwable $e) {
                // soft: continue delete
            }
        }
        model(EquityIngresoContratoModel::class)->delete($id);
        $this->sincronizarFichaDesdeContratos($empresaId, $personaId, $memb['usuario_id']);

        return redirect()->to($redirect)->with('success', 'Contrato eliminado.');
    }

    public function descargarContratoIngreso(string $fuente, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $row = model(EquityIngresoContratoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('fuente', $fuente)
            ->first();
        if (! is_array($row) || empty($row['contrato_ruta_relativa'])) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Archivo no disponible.');
        }

        try {
            $data = DocumentStorage::read((string) $row['contrato_ruta_relativa']);
        } catch (\Throwable $e) {
            return redirect()->to('/equity/ingresos/' . $fuente)->with('error', 'Archivo no encontrado.');
        }

        $nombre = str_replace('"', '', (string) ($row['contrato_archivo_nombre'] ?? 'contrato.pdf'));

        return $this->response
            ->setHeader('Content-Type', (string) ($row['contrato_mime'] ?: 'application/octet-stream'))
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nombre . '"')
            ->setBody($data);
    }

    /** Recalcula campos de ficha desde contratos vigentes (equivalente mensual). */
    private function sincronizarFichaDesdeContratos(string $empresaId, string $personaId, string $usuarioId): void
    {
        if ($personaId === '') {
            return;
        }
        helper('id');
        $fuentes = EquityCatalogo::fuentesIngreso();
        $sums = [];
        foreach ($fuentes as $meta) {
            foreach ($meta['campos'] as $c) {
                $sums[$c['key']] = 0.0;
            }
        }

        $rows = model(EquityIngresoContratoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->where('estado', 'vigente')
            ->findAll();
        $subtipoToKey = [];
        foreach ($fuentes as $meta) {
            foreach ($meta['campos'] as $c) {
                $subtipoToKey[$c['subtipo']] = $c['key'];
            }
        }
        foreach ($rows as $r) {
            $key = $subtipoToKey[$r['subtipo'] ?? ''] ?? null;
            if ($key === null) {
                continue;
            }
            $mensual = EquityCatalogo::montoMensualEquivalente(
                isset($r['monto_contrato_clp']) ? (float) $r['monto_contrato_clp'] : null,
                $r['periodicidad'] ?? 'mensual'
            );
            $sums[$key] = ($sums[$key] ?? 0) + $mensual;
        }

        $patch = [];
        foreach ($sums as $k => $v) {
            $patch[$k] = $v > 0 ? number_format($v, 2, '.', '') : null;
        }

        $ficha = $this->ultimaFicha($empresaId, $personaId);
        if (is_array($ficha)) {
            $merged = array_merge($ficha, $patch);
            $patch['ingreso_mensual_total'] = $this->sumIngresosFicha($merged);
            model(EquityFichaModel::class)->update($ficha['id'], $patch);
        } else {
            $any = false;
            foreach ($patch as $v) {
                if ($v !== null) {
                    $any = true;
                    break;
                }
            }
            if (! $any) {
                return;
            }
            $row = array_merge([
                'id'            => new_id(),
                'empresa_id'    => $empresaId,
                'persona_id'    => $personaId,
                'fecha_corte'   => date('Y-m-d'),
                'moneda'        => 'CLP',
                'creado_por_id' => $usuarioId,
            ], $patch);
            $row['ingreso_mensual_total'] = $this->sumIngresosFicha($row);
            model(EquityFichaModel::class)->insert($row);
        }
    }

    /** @return array<string, mixed>|null */
    private function ultimaFicha(string $empresaId, string $personaId): ?array
    {
        $q = model(EquityFichaModel::class)
            ->orderBy('fecha_corte', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $row = $q->first();

        return is_array($row) ? $row : null;
    }

    private function sumCanonArriendos(string $empresaId, string $personaId, float $ufRate): float
    {
        $bq = model(EquityBienRaizModel::class);
        $this->soloBienesActivos($bq);
        if ($personaId !== '') {
            $bq->where('persona_id', $personaId);
        } else {
            $bq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $sum = 0.0;
        foreach ($bq->findAll() as $br) {
            $canon = $br['canon_mensual_clp'] ?? null;
            if (($canon === null || $canon === '') && ! empty($br['canon_mensual_uf']) && $ufRate > 0) {
                $canon = round((float) $br['canon_mensual_uf'] * $ufRate, 2);
            }
            if ($canon !== null && $canon !== '' && (float) $canon > 0) {
                $sum += (float) $canon;
            }
        }

        return $sum;
    }

    /** @param array<string, mixed> $ficha */
    private function sumIngresosFicha(array $ficha): ?string
    {
        $keys = [
            'ingreso_fijo', 'ingreso_variable',
            'ingreso_honorarios', 'ingreso_comisiones', 'ingreso_bonos',
            'ingreso_arriendos', 'ingreso_mutuos', 'ingreso_dividendos',
            'ingreso_pensiones', 'ingreso_donaciones', 'ingreso_indemnizaciones',
            'ingreso_otros',
        ];
        $sum = 0.0;
        $any = false;
        foreach ($keys as $k) {
            if (isset($ficha[$k]) && $ficha[$k] !== null && $ficha[$k] !== '') {
                $sum += (float) $ficha[$k];
                $any = true;
            }
        }

        return $any ? number_format($sum, 2, '.', '') : null;
    }

    public function respaldos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $q = model(EquityRespaldoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $respaldos = $q->findAll();
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
            'canManage' => EquityAcl::canDeclare($memb),
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
        [$personaId, $personas] = $this->personaContexto($memb);
        $rows = $this->loadRows($meta, $empresaId, $personaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($rows as &$row) {
            $row['persona'] = $personaMap[$row['persona_id']] ?? null;
        }
        unset($row);

        $edit = null;
        $bitacora = [];
        if ($editId !== null && $editId !== '') {
            if (! EquityAcl::canDeclare($memb)) {
                return redirect()->to($this->pathSeccion($clave))->with('error', 'Sin permiso para editar.');
            }
            $edit = $this->findRegistro($meta, $empresaId, $editId);
            if ($edit === null) {
                return redirect()->to($this->pathSeccion($clave))->with('error', 'Registro no encontrado.');
            }
            if ($clave === 'bienes-raices'
                && array_key_exists('activo', $edit)
                && (int) ($edit['activo'] ?? 1) === 0
            ) {
                return redirect()->to($this->pathSeccion($clave))
                    ->with('error', 'Este bien raíz está dado de baja y no se puede editar.');
            }
            $personaId = (string) ($edit['persona_id'] ?? $personaId);
            $tipoBit = EquityActivoBitacora::tipoDesdeClave($clave);
            if ($tipoBit !== null) {
                $bitacora = EquityActivoBitacora::historial($tipoBit, (string) $editId);
            }
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
            'bitacora'  => $bitacora,
            'ufVigente' => EquityUf::vigente($empresaId),
            'canManage' => EquityAcl::canDeclare($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function bitacoraActivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $clave = $this->claveActivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        $tipo  = EquityActivoBitacora::tipoDesdeClave($clave);
        if ($meta === null || $tipo === null || $clave === '') {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $row = $this->findRegistro($meta, $memb['empresa_id'], $id);
        if ($row === null) {
            return redirect()->to($this->pathSeccion($clave))->with('error', 'Registro no encontrado.');
        }

        $historial = EquityActivoBitacora::historial($tipo, $id);
        $personaMap = [];
        [, $personas] = $this->personaContexto($memb);
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $persona = $personaMap[$row['persona_id'] ?? ''] ?? null;

        return view('equity/bitacora_activo', [
            'title'         => 'Bitácora · ' . $meta['label'],
            'memb'          => $memb,
            'equityNav'     => $clave,
            'meta'          => $meta,
            'row'           => $row,
            'persona'       => $persona,
            'historial'     => $historial,
            'campos'        => EquityActivoBitacora::camposParaClave($clave),
            'cierreActual'  => EquityActivoBitacora::fechaCierreMes(),
            'backUrl'       => $this->pathSeccion($clave),
            'editUrl'       => rtrim($this->pathSeccion($clave), '/') . '/' . $id . '/editar',
            'detalle'       => $this->detalleRegistro($clave, $row),
            'ufVigente'     => EquityUf::vigente($memb['empresa_id']),
        ]);
    }

    private function storeSeccion(string $clave, ?string $editId = null)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin permiso para editar.');
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $personaId = $this->sessionPersonaId();
        $persona   = $this->personaActivaAutorizada($memb, $personaId);
        if ($persona === null) {
            return redirect()->back()->with('error', 'No hay persona activa en la sesión para registrar datos.');
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
        $existing = null;
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
        $syncBienDespues = false;

        if ($clave === 'bienes-raices') {
            $direccion = trim((string) $this->request->getPost('direccion'));
            $tipoInm   = $intOrNull($this->request->getPost('tipo_inmueble'));
            if ($direccion === '' || $tipoInm === null) {
                return redirect()->to($redirectPath)->with('error', 'Tipo y dirección son obligatorios.');
            }
            $parCanon = EquityUf::resolverPar(
                (string) $this->request->getPost('canon_mensual_uf'),
                (string) $this->request->getPost('canon_mensual_clp'),
                $memb['empresa_id']
            );
            $model = model(EquityBienRaizModel::class);
            $data  = [
                'persona_id'        => $personaId,
                'tipo_inmueble'     => $tipoInm,
                'direccion'         => $direccion,
                'descripcion'       => $direccion,
                'rol'               => trim((string) $this->request->getPost('rol')) ?: null,
                'avaluo_fiscal'     => $money($this->request->getPost('avaluo_fiscal')),
                'garantia'          => $intOrNull($this->request->getPost('garantia')),
                'asegurado'         => $intOrNull($this->request->getPost('asegurado')),
                'valor_clp'         => $parValor['clp'],
                'valor_uf'          => $parValor['uf'],
                'arrendatario'      => trim((string) $this->request->getPost('arrendatario')) ?: null,
                'canon_mensual_clp' => $parCanon['clp'],
                'canon_mensual_uf'  => $parCanon['uf'],
                'mes_canon'         => $intOrNull($this->request->getPost('mes_canon')),
                'medio_pago'        => trim((string) $this->request->getPost('medio_pago')) ?: null,
                'notas'             => trim((string) $this->request->getPost('notas')) ?: null,
            ];
            if (! $isEdit) {
                $data['activo'] = 1;
            } elseif (array_key_exists('activo', $existing ?? []) && (int) (($existing['activo'] ?? 1)) === 0) {
                return redirect()->to($redirectPath)
                    ->with('error', 'Este bien raíz está dado de baja y no se puede editar.');
            }
            // Tras guardar el bien (onboarding), alinear el contrato de arriendo vinculado.
            $syncBienDespues = true;
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
            $monedaHip = strtoupper(trim((string) $this->request->getPost('moneda'))) === 'CLP' ? 'CLP' : 'UF';
            // Moneda del crédito define el canónico; la otra pata se calcula con UF vigente.
            $parSaldoHip = EquityUf::resolverPar(
                (string) $this->request->getPost('saldo_uf'),
                (string) $this->request->getPost('saldo_clp'),
                $memb['empresa_id'],
                null,
                $monedaHip
            );
            $parDividendo = EquityUf::resolverPar(
                (string) $this->request->getPost('dividendo_uf'),
                (string) $this->request->getPost('dividendo_clp'),
                $memb['empresa_id'],
                null,
                $monedaHip
            );
            $parTotalCred = EquityUf::resolverPar(
                (string) $this->request->getPost('total_credito_uf'),
                (string) $this->request->getPost('total_credito_clp'),
                $memb['empresa_id'],
                null,
                $monedaHip
            );
            $compHip = EquityUf::resolverParesComponentes(
                [
                    'amortizacion', 'interes', 'comision',
                    'seg_incendio', 'seg_desgravamen', 'seg_adicionales', 'seg_otros_complementarios',
                ],
                fn (string $k) => $this->request->getPost($k),
                $memb['empresa_id'],
                $monedaHip
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
                'moneda'                       => $monedaHip,
                'dividendo_n'                  => $intOrNull($this->request->getPost('dividendo_n')),
                'fecha_registro'               => trim((string) $this->request->getPost('fecha_registro')) ?: null,
                'plazo_pactado'                => $plazo,
                'saldo_clp'                    => $parSaldoHip['clp'],
                'saldo_uf'                     => $parSaldoHip['uf'],
                'cuotas_totales'               => $cuotasTot,
                'cuotas_pagadas'               => $intOrNull($this->request->getPost('cuotas_pagadas')),
                'cuota_mensual'                => $parDividendo['clp'],
                'dividendo_uf'                 => $parDividendo['uf'],
                'dividendo_clp'                => $parDividendo['clp'],
                'amortizacion_uf'              => $compHip['amortizacion_uf'],
                'amortizacion_clp'             => $compHip['amortizacion_clp'],
                'interes_uf'                   => $compHip['interes_uf'],
                'interes_clp'                  => $compHip['interes_clp'],
                'comision_uf'                  => $compHip['comision_uf'],
                'comision_clp'                 => $compHip['comision_clp'],
                'seg_incendio_uf'              => $compHip['seg_incendio_uf'],
                'seg_incendio_clp'             => $compHip['seg_incendio_clp'],
                'seg_desgravamen_uf'           => $compHip['seg_desgravamen_uf'],
                'seg_desgravamen_clp'          => $compHip['seg_desgravamen_clp'],
                'seg_adicionales_uf'           => $compHip['seg_adicionales_uf'],
                'seg_adicionales_clp'          => $compHip['seg_adicionales_clp'],
                'seg_otros_complementarios_uf' => $compHip['seg_otros_complementarios_uf'],
                'seg_otros_complementarios_clp'=> $compHip['seg_otros_complementarios_clp'],
                'fecha_otorgamiento'           => trim((string) $this->request->getPost('fecha_otorgamiento')) ?: null,
                'fecha_vencimiento'            => trim((string) $this->request->getPost('fecha_vencimiento')) ?: null,
                'total_credito_uf'             => $parTotalCred['uf'],
                'total_credito_clp'            => $parTotalCred['clp'],
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
            // Circulantes / Otros: misma estructura que hipotecarios; seguro = desgravamen.
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La institución / banco es obligatoria.');
            }
            $monedaOtro = strtoupper(trim((string) $this->request->getPost('moneda')));
            if ($monedaOtro !== 'UF' && $monedaOtro !== 'CLP') {
                $monedaOtro = 'CLP';
            }
            $parSaldoOtro = EquityUf::resolverPar(
                (string) $this->request->getPost('saldo_uf'),
                (string) $this->request->getPost('saldo_clp'),
                $memb['empresa_id'],
                null,
                $monedaOtro
            );
            // Dividendo / cuota: acepta dividendo_* (form nuevo) o cuota_* (legado).
            $divUfPost  = (string) $this->request->getPost('dividendo_uf');
            $divClpPost = (string) $this->request->getPost('dividendo_clp');
            if (trim($divUfPost) === '' && trim($divClpPost) === '') {
                $divUfPost  = (string) $this->request->getPost('cuota_uf');
                $divClpPost = (string) $this->request->getPost('cuota_mensual');
            }
            $parCuotaOtro = EquityUf::resolverPar(
                $divUfPost,
                $divClpPost,
                $memb['empresa_id'],
                null,
                $monedaOtro
            );
            $parTotalOtro = EquityUf::resolverPar(
                (string) $this->request->getPost('total_credito_uf'),
                (string) $this->request->getPost('total_credito_clp'),
                $memb['empresa_id'],
                null,
                $monedaOtro
            );
            $compOtro = EquityUf::resolverParesComponentes(
                ['amortizacion', 'interes', 'comision', 'seg_desgravamen'],
                fn (string $k) => $this->request->getPost($k),
                $memb['empresa_id'],
                $monedaOtro
            );
            $plazoOtro = $intOrNull($this->request->getPost('plazo_pactado'));
            $cuotasTotOtro = $intOrNull($this->request->getPost('cuotas_totales'));
            if ($cuotasTotOtro === null) {
                $cuotasTotOtro = $plazoOtro;
            }
            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'          => $personaId,
                'categoria'           => $meta['categoria'] ?? 'otro',
                'banco_descripcion'   => $desc,
                'numero_credito'      => trim((string) $this->request->getPost('numero_credito')) ?: null,
                'sucursal'            => trim((string) $this->request->getPost('sucursal')) ?: null,
                'moneda'              => $monedaOtro,
                'dividendo_n'         => $intOrNull($this->request->getPost('dividendo_n')),
                'fecha_registro'      => trim((string) $this->request->getPost('fecha_registro')) ?: null,
                'plazo_pactado'       => $plazoOtro,
                'saldo_clp'           => $parSaldoOtro['clp'],
                'saldo_uf'            => $parSaldoOtro['uf'],
                'cuotas_totales'      => $cuotasTotOtro,
                'cuotas_pagadas'      => $intOrNull($this->request->getPost('cuotas_pagadas')),
                'cuota_mensual'       => $parCuotaOtro['clp'],
                'dividendo_uf'        => $parCuotaOtro['uf'],
                'dividendo_clp'       => $parCuotaOtro['clp'],
                'amortizacion_uf'     => $compOtro['amortizacion_uf'],
                'amortizacion_clp'    => $compOtro['amortizacion_clp'],
                'interes_uf'          => $compOtro['interes_uf'],
                'interes_clp'         => $compOtro['interes_clp'],
                'comision_uf'         => $compOtro['comision_uf'],
                'comision_clp'        => $compOtro['comision_clp'],
                'seg_desgravamen_uf'  => $compOtro['seg_desgravamen_uf'],
                'seg_desgravamen_clp' => $compOtro['seg_desgravamen_clp'],
                'fecha_otorgamiento'  => trim((string) $this->request->getPost('fecha_otorgamiento')) ?: null,
                'fecha_vencimiento'   => trim((string) $this->request->getPost('fecha_vencimiento')) ?: null,
                'total_credito_uf'    => $parTotalOtro['uf'],
                'total_credito_clp'   => $parTotalOtro['clp'],
                'asociacion'          => trim((string) $this->request->getPost('asociacion')) ?: null,
                'tipo'                => trim((string) $this->request->getPost('tipo')) ?: null,
                'notas'               => trim((string) $this->request->getPost('notas')) ?: null,
            ];
        }

        if ($isEdit) {
            $before = $existing ?? null;
            $model->update($editId, $data);
            $activoId = (string) $editId;
            $msg = 'Registro actualizado.';
        } else {
            $before = null;
            $data['id']            = new_id();
            $data['empresa_id']    = $memb['empresa_id'];
            $data['creado_por_id'] = $memb['usuario_id'];
            $model->insert($data);
            $activoId = (string) $data['id'];
            $msg = 'Registro agregado.';
        }

        // Bitácora mensual de valor comercial / avalúo (solo activos).
        EquityActivoBitacora::sincronizarDesdeStore(
            $clave,
            (string) $memb['empresa_id'],
            (string) $personaId,
            $activoId,
            is_array($before ?? null) ? $before : null,
            $data,
            (string) ($memb['usuario_id'] ?? '')
        );

        if ($syncBienDespues && $clave === 'bienes-raices') {
            $ufVigente = EquityUf::vigente((string) $memb['empresa_id']);
            $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
            $stats = EquityArriendoSync::sincronizarDesdeBienes(
                (string) $memb['empresa_id'],
                (string) $personaId,
                (string) ($memb['usuario_id'] ?? ''),
                $ufRate,
                $activoId
            );
            if (($stats['created'] + $stats['updated'] + $stats['linked']) > 0) {
                $this->sincronizarFichaDesdeContratos(
                    (string) $memb['empresa_id'],
                    (string) $personaId,
                    (string) ($memb['usuario_id'] ?? '')
                );
                $msg .= ' Contrato de arriendo alineado desde el bien.';
            }
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

    private function pathReporteSeccion(string $clave): string
    {
        return match ($clave) {
            'bienes-raices'       => '/equity/activos/bienes-raices/reporte',
            'vehiculos'           => '/equity/activos/vehiculos/reporte',
            'sociedades'          => '/equity/activos/sociedades/reporte',
            'otros-activos'       => '/equity/activos/otros/reporte',
            'pasivos-inmuebles'   => '/equity/pasivos/inmuebles/reporte',
            'pasivos-circulantes' => '/equity/pasivos/circulantes/reporte',
            'otros-pasivos'       => '/equity/pasivos/otros/reporte',
            'pasivos-indirecta'   => '/equity/pasivos/indirecta/reporte',
            'pasivos-lineas'      => '/equity/pasivos/lineas/reporte',
            default               => '/equity/reporte/balance',
        };
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    private function reporteSeccion(string $clave)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null || $clave === '') {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $ufVigente = EquityUf::vigente($empresaId);
        $rows = $this->loadRows($meta, $empresaId, $personaId);
        foreach ($rows as &$row) {
            $row['persona'] = $personaMap[$row['persona_id'] ?? ''] ?? null;
            $row['detalle'] = $this->detalleRegistro($clave, $row);
            [$row['uf_show'], $row['clp_show']] = $this->montosRegistro($clave, $row, $ufVigente);
        }
        unset($row);

        $personaNombre = null;
        if ($personaId !== '' && isset($personaMap[$personaId])) {
            $p = $personaMap[$personaId];
            $personaNombre = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        }

        $totalUf   = 0.0;
        $totalClp  = 0.0;
        $totalAvaluo = 0.0;
        foreach ($rows as $r) {
            if ($r['uf_show'] !== null) {
                $totalUf += (float) $r['uf_show'];
            }
            if ($r['clp_show'] !== null) {
                $totalClp += (float) $r['clp_show'];
            }
            if ($clave === 'bienes-raices' && ! empty($r['avaluo_fiscal'])) {
                $totalAvaluo += (float) $r['avaluo_fiscal'];
            }
        }

        return view('equity/reporte_seccion', [
            'title'         => 'Reporte · ' . $meta['label'],
            'memb'          => $memb,
            'equityNav'     => $clave,
            'meta'          => $meta,
            'rows'          => $rows,
            'personaId'     => $personaId,
            'personaNombre' => $personaNombre,
            'ufVigente'     => $ufVigente,
            'totalUf'       => $totalUf,
            'totalClp'      => $totalClp,
            'totalAvaluo'   => $totalAvaluo,
            'backUrl'       => $this->pathSeccion($clave),
            'grupoReporte'  => str_starts_with($clave, 'pasivos') || $clave === 'otros-pasivos'
                ? '/equity/reporte/pasivos'
                : '/equity/reporte/activos',
        ]);
    }

    /**
     * @param list<string> $claves
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    private function reporteGrupo(string $grupo, array $claves)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $personaNombre = null;
        if ($personaId !== '' && isset($personaMap[$personaId])) {
            $p = $personaMap[$personaId];
            $personaNombre = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
        }

        $ufVigente = EquityUf::vigente($empresaId);
        $secciones = [];
        $granTotalUf  = 0.0;
        $granTotalClp = 0.0;
        foreach ($claves as $clave) {
            $meta = EquityCatalogo::seccion($clave);
            if ($meta === null) {
                continue;
            }
            $rows = $this->loadRows($meta, $empresaId, $personaId);
            $totalUf  = 0.0;
            $totalClp = 0.0;
            foreach ($rows as &$row) {
                $row['persona'] = $personaMap[$row['persona_id'] ?? ''] ?? null;
                $row['detalle'] = $this->detalleRegistro($clave, $row);
                [$row['uf_show'], $row['clp_show']] = $this->montosRegistro($clave, $row, $ufVigente);
                if ($row['uf_show'] !== null) {
                    $totalUf += (float) $row['uf_show'];
                }
                if ($row['clp_show'] !== null) {
                    $totalClp += (float) $row['clp_show'];
                }
            }
            unset($row);
            $granTotalUf  += $totalUf;
            $granTotalClp += $totalClp;
            $secciones[] = [
                'meta'     => $meta,
                'rows'     => $rows,
                'totalUf'  => $totalUf,
                'totalClp' => $totalClp,
                'path'     => $this->pathSeccion($clave),
                'reporte'  => $this->pathReporteSeccion($clave),
            ];
        }

        return view('equity/reporte_grupo', [
            'title'         => $grupo === 'pasivos' ? 'Reporte · Pasivos' : 'Reporte · Activos',
            'memb'          => $memb,
            'equityNav'     => $grupo === 'pasivos' ? 'pasivos-inmuebles' : 'bienes-raices',
            'grupo'         => $grupo,
            'secciones'     => $secciones,
            'personaId'     => $personaId,
            'personaNombre' => $personaNombre,
            'ufVigente'     => $ufVigente,
            'granTotalUf'   => $granTotalUf,
            'granTotalClp'  => $granTotalClp,
            'backUrl'       => $grupo === 'pasivos' ? '/equity/pasivos/inmuebles' : '/equity/activos/bienes-raices',
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function detalleRegistro(string $clave, array $row): string
    {
        return match ($clave) {
            'bienes-raices' => trim(
                EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoInmueble(), $row['tipo_inmueble'] ?? null)
                . ' · '
                . (($row['direccion'] ?? '') !== '' ? (string) $row['direccion'] : (string) ($row['descripcion'] ?? ''))
                . (! empty($row['rol']) ? ' · Rol ' . $row['rol'] : '')
                . (! empty($row['avaluo_fiscal']) ? ' · Avalúo $' . number_format((float) $row['avaluo_fiscal'], 0, ',', '.') : '')
                . (! empty($row['arrendatario']) ? ' · Arr. ' . $row['arrendatario'] : '')
                . (! empty($row['canon_mensual_clp']) ? ' · Cánon $' . number_format((float) $row['canon_mensual_clp'], 0, ',', '.') : ''),
                ' ·'
            ),
            'vehiculos' => trim(
                EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoVehiculo(), $row['tipo_vehiculo'] ?? null)
                . ' · '
                . trim(($row['marca'] ?? '') . ' ' . ($row['modelo'] ?? '') . ' ' . ($row['patente'] ?? '')),
                ' ·'
            ),
            'sociedades' => trim(
                (string) ($row['nombre_sociedad'] ?? '')
                . (! empty($row['rut_sociedad']) ? ' · ' . $row['rut_sociedad'] : '')
                . (! empty($row['porcentaje']) ? ' · ' . $row['porcentaje'] . '%' : ''),
                ' ·'
            ),
            'otros-activos' => trim(
                EquityCatalogo::labelOpcion(EquityCatalogo::opcionesTipoOtroActivo(), $row['tipo_activo'] ?? null)
                . ' · '
                . (string) ($row['institucion'] ?? ''),
                ' ·'
            ),
            'pasivos-inmuebles' => trim(
                (string) ($row['banco_descripcion'] ?? '')
                . (! empty($row['numero_credito']) ? ' · Crédito ' . $row['numero_credito'] : '')
                . (! empty($row['dividendo_n']) ? ' · Div. ' . $row['dividendo_n'] : '')
                . (! empty($row['fecha_registro']) ? ' · Reg. ' . $row['fecha_registro'] : '')
                . (! empty($row['dividendo_uf']) ? ' · ' . number_format((float) $row['dividendo_uf'], 4, ',', '.') . ' UF' : ''),
                ' ·'
            ),
            'pasivos-indirecta' => trim(
                (string) ($row['banco_descripcion'] ?? '')
                . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
                ' ·'
            ),
            'pasivos-lineas' => trim(
                (string) ($row['tipo'] ?? 'Línea')
                . ' · '
                . (string) ($row['banco_descripcion'] ?? ''),
                ' ·'
            ),
            default => (string) ($row['banco_descripcion'] ?? $row['descripcion'] ?? ''),
        };
    }

    /**
     * @param array<string, mixed>      $row
     * @param array<string, mixed>|null $ufVigente
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function montosRegistro(string $clave, array $row, ?array $ufVigente): array
    {
        $rate = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $ufRaw = match ($clave) {
            'bienes-raices', 'vehiculos', 'sociedades' => $row['valor_uf'] ?? null,
            default => $row['saldo_uf'] ?? null,
        };
        $clpRaw = match ($clave) {
            'bienes-raices', 'vehiculos', 'sociedades' => $row['valor_clp'] ?? null,
            default => $row['saldo_clp'] ?? null,
        };
        $uf  = ($ufRaw !== null && $ufRaw !== '') ? (float) $ufRaw : null;
        $clp = ($clpRaw !== null && $clpRaw !== '') ? (float) $clpRaw : null;
        if ($uf !== null) {
            return [$uf, round($uf * $rate, 2)];
        }
        if ($clp !== null && $rate > 0) {
            return [round($clp / $rate, 4), $clp];
        }

        return [null, null];
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
        $q = $model->where('id', $id);
        if (($meta['table'] ?? '') === 'equity_pasivos' && isset($meta['categoria'])) {
            $q->where('categoria', $meta['categoria']);
        }
        $row = $q->first();
        if (! is_array($row)) {
            return null;
        }

        $sessionPersona = $this->sessionPersonaId();
        if ($sessionPersona !== '' && (string) ($row['persona_id'] ?? '') === $sessionPersona) {
            return $row;
        }
        if (in_array((string) ($row['empresa_id'] ?? ''), $this->empresaScopeIds($empresaId), true)) {
            return $row;
        }

        return null;
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

        // Con persona de sesión: filtrar por persona (puede vivir en otro empresa_id del seed/grupo).
        // Sin persona: listar solo el alcance de la empresa de la membresía.
        $q = $model->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        if (($meta['table'] ?? '') === 'equity_pasivos' && isset($meta['categoria'])) {
            $q->where('categoria', $meta['categoria']);
        }
        if (($meta['table'] ?? '') === 'equity_bienes_raices') {
            $this->soloBienesActivos($q);
        }

        return $q->findAll();
    }

    /** Filtra bienes raíces dados de baja (activo=0), si la columna existe. */
    private function soloBienesActivos(object $builder): void
    {
        $db = db_connect();
        if ($db->tableExists('equity_bienes_raices') && $db->fieldExists('activo', 'equity_bienes_raices')) {
            $builder->where('activo', 1);
        }
    }

    /** @return list<string> */
    private function empresaScopeIds(string $empresaId): array
    {
        $ids = (new \App\Libraries\EquityOrg())->empresaIdsDelGrupo($empresaId);

        return $ids !== [] ? $ids : [$empresaId];
    }

    /**
     * Persona de sesión si es declarable para el usuario (propia o representada),
     * aunque su empresa_id no coincida exactamente con la membresía actual.
     *
     * @param array<string, mixed> $memb
     * @return array<string, mixed>|null
     */
    private function personaActivaAutorizada(array $memb, string $personaId): ?array
    {
        if ($personaId === '') {
            return null;
        }
        $uid = (string) ((new MembershipAuth())->usuarioId() ?? '');
        if ($uid === '') {
            return null;
        }
        foreach ((new \App\Libraries\EquityOrg())->personasDeclarables($uid, (string) $memb['empresa_id']) as $p) {
            if (($p['id'] ?? '') === $personaId) {
                return $p;
            }
        }

        return null;
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
        $builder = $db->table($table);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        if ($table === 'equity_bienes_raices' && $db->fieldExists('activo', $table)) {
            $builder->where('activo', 1);
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
            ->where('categoria', $categoria);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
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
            ->where('categoria', 'indirecta');
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
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
            ->where('categoria', 'linea');
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
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
