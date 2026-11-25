<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentStorage;
use App\Libraries\EquityAcl;
use App\Libraries\EquityActivoBitacora;
use App\Libraries\EquityArriendoSync;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityDesarrolloCuotas;
use App\Libraries\EquityDesarrolloImport;
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
        // Solo deuda directa de la persona. Indirecta y Líneas son referencias (no suman al total ni al patrimonio neto).
        $totalesUf['pasivos'] = $totalesUf['pasivos_inm'] + $totalesUf['pasivos_circ'] + $totalesUf['pasivos_otro'];
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
        // Solo deuda directa. Indirecta y Líneas quedan como referencia (no restan del patrimonio).
        $totalesUf['pasivos']    = $totalesUf['pasivos_inm'] + $totalesUf['pasivos_circ'] + $totalesUf['pasivos_otro'];
        $totalesUf['patrimonio'] = $totalesUf['activos'] - $totalesUf['pasivos'];

        $totales = [];
        foreach ($totalesUf as $k => $uf) {
            $totales[$k] = round($uf * $ufRate, 2);
        }

        // Ingresos / Egresos del Balance: totales por pestaña (misma lógica que los hubs).
        $fuentesIngreso = EquityCatalogo::fuentesIngreso();
        $ingTotales     = $this->totalesIngresosMensuales($empresaId, $personaId, $ufRate);
        $ingresosDetalle = [];
        foreach ($fuentesIngreso as $clave => $meta) {
            $ingresosDetalle[] = [
                'concepto' => $meta['label'],
                'monto'    => (float) ($ingTotales['porFuente'][$clave] ?? 0),
            ];
        }
        $totalIngresos = (float) $ingTotales['total'];

        $fuentesEgreso   = EquityCatalogo::fuentesEgreso();
        $egresosDetalle  = [];
        $totalEgresos    = 0.0;
        foreach ($fuentesEgreso as $clave => $meta) {
            $q = model(EquityPasivoModel::class)->where('categoria', $meta['categoria']);
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
            }
            $sum = 0.0;
            foreach ($q->findAll() as $row) {
                $cuota = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
                if ($cuota !== null) {
                    $sum += $cuota;
                }
            }
            $egresosDetalle[] = [
                'concepto' => $meta['label'],
                'monto'    => $sum,
            ];
            $totalEgresos += $sum;
        }

        return view('equity/reporte_balance', [
            'title'            => 'Reporte Balance',
            'memb'             => $memb,
            'equityNav'        => 'resumen',
            'personas'         => $personas,
            'personaId'        => $personaId,
            'personaNombre'    => $personaNombre,
            'totales'          => $totales,
            'totalesUf'        => $totalesUf,
            'ufVigente'        => $ufVigente,
            'ingresosDetalle'  => $ingresosDetalle,
            'arriendosDetalle' => [],
            'egresosDetalle'   => $egresosDetalle,
            'totalIngresos'    => $totalIngresos,
            'totalArriendos'   => 0.0,
            'totalEgresos'     => $totalEgresos,
            'resultadoMensual' => $totalIngresos - $totalEgresos,
            'canManage'        => EquityAcl::canDeclare($memb),
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

    /** Líneas disponibles (Pasivos): listado, edición y eliminación. */
    public function lineas()
    {
        return $this->seccionLista('pasivos-lineas');
    }

    public function editLinea(string $id)
    {
        return $this->seccionLista('pasivos-lineas', $id);
    }

    public function storeLinea()
    {
        return $this->storeSeccion('pasivos-lineas');
    }

    public function updateLinea(string $id)
    {
        return $this->storeSeccion('pasivos-lineas', $id);
    }

    public function reporteLineas()
    {
        return $this->reporteSeccion('pasivos-lineas');
    }

    /**
     * Elimina una línea disponible (hard delete del registro de cupo).
     */
    public function eliminarLinea(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        $redirectLineas = '/equity/pasivos/lineas';
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to($redirectLineas)->with('error', 'Sin permiso para eliminar.');
        }

        [$personaId] = $this->personaContexto($memb);
        $q = model(EquityPasivoModel::class)
            ->where('id', $id)
            ->where('categoria', 'linea');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->whereIn('empresa_id', $this->empresaScopeIds($memb['empresa_id']));
        }
        $row = $q->first();
        if ($row === null) {
            return redirect()->to($redirectLineas)->with('error', 'Línea no encontrada.');
        }

        model(EquityPasivoModel::class)->delete($id);

        return redirect()->to($redirectLineas . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : ''))
            ->with('success', 'Línea eliminada.');
    }

    public function reporteIngresos()
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
        $fuentes   = EquityCatalogo::fuentesIngreso();
        $ingTotales = $this->totalesIngresosMensuales($empresaId, $personaId, $ufRate);
        $periodos   = EquityCatalogo::opcionesPeriodicidadIngreso();
        $estados    = EquityCatalogo::opcionesEstadoContratoIngreso();

        $subtipoFuente = [];
        $subtipoLabel  = [];
        foreach ($fuentes as $clave => $meta) {
            foreach ($meta['campos'] as $c) {
                $subtipoFuente[$c['subtipo']] = $clave;
                $subtipoLabel[$c['subtipo']]  = $c['label'];
            }
        }

        $cq = model(EquityIngresoContratoModel::class)
            ->orderBy('estado', 'ASC')
            ->orderBy('fecha_inicio_contrato', 'DESC')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $allContratos = $cq->findAll();

        $bienIds = [];
        foreach ($allContratos as $c) {
            $bid = (string) ($c['bien_raiz_id'] ?? '');
            if ($bid !== '') {
                $bienIds[$bid] = true;
            }
        }
        $bienMap = [];
        if ($bienIds !== []) {
            foreach (model(EquityBienRaizModel::class)->whereIn('id', array_keys($bienIds))->findAll() as $br) {
                $bienMap[(string) $br['id']] = $br;
            }
        }

        $detallePorFuente = [];
        foreach ($fuentes as $clave => $_meta) {
            $detallePorFuente[$clave] = [
                'vigentes' => [],
                'vencidos' => [],
                'subtotales' => [],
            ];
            foreach ($_meta['campos'] as $c) {
                $detallePorFuente[$clave]['subtotales'][$c['subtipo']] = [
                    'label' => $c['label'],
                    'monto' => 0.0,
                ];
            }
        }

        $seenBienArriendo = [];
        foreach ($allContratos as $c) {
            $subtipo = (string) ($c['subtipo'] ?? '');
            $fClave  = $subtipoFuente[$subtipo] ?? (string) ($c['fuente'] ?? '');
            if ($fClave === '' || ! isset($detallePorFuente[$fClave])) {
                continue;
            }
            $estado = (string) ($c['estado'] ?? 'vigente');
            $bid    = (string) ($c['bien_raiz_id'] ?? '');
            if ($estado === 'vigente' && $subtipo === 'arriendos' && $bid !== '') {
                if (isset($seenBienArriendo[$bid])) {
                    continue;
                }
                $seenBienArriendo[$bid] = true;
            }
            $mensual = EquityCatalogo::montoMensualEquivalente(
                isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                $c['periodicidad'] ?? 'mensual'
            );
            $br = $bid !== '' ? ($bienMap[$bid] ?? null) : null;
            $row = [
                'tipo'            => $subtipoLabel[$subtipo] ?? $subtipo,
                'contraparte'     => $c['contraparte_nombre'] ?? null,
                'rut'             => $c['contraparte_rut'] ?? null,
                'bien'            => is_array($br)
                    ? trim((string) (($br['direccion'] ?? '') !== '' ? $br['direccion'] : ($br['descripcion'] ?? '')))
                    : null,
                'inicio'          => $c['fecha_inicio_contrato'] ?? null,
                'vencimiento'     => $c['fecha_vencimiento'] ?? null,
                'periodicidad'    => $periodos[$c['periodicidad'] ?? ''] ?? ($c['periodicidad'] ?? null),
                'monto_contrato'  => isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                'equiv_mensual'   => $mensual,
                'estado'          => $estados[$estado] ?? $estado,
                'estado_clave'    => $estado,
                'notas'           => $c['notas'] ?? null,
            ];
            if ($estado === 'vigente') {
                $detallePorFuente[$fClave]['vigentes'][] = $row;
                if (isset($detallePorFuente[$fClave]['subtotales'][$subtipo])) {
                    $detallePorFuente[$fClave]['subtotales'][$subtipo]['monto'] += $mensual;
                }
            } else {
                $detallePorFuente[$fClave]['vencidos'][] = $row;
            }
        }

        // Si una fuente no tiene contratos vigentes, desglose desde ficha / total hub.
        foreach ($fuentes as $clave => $meta) {
            $tieneVigentes = ($detallePorFuente[$clave]['vigentes'] ?? []) !== [];
            if ($tieneVigentes) {
                continue;
            }
            $sumSub = 0.0;
            foreach ($meta['campos'] as $c) {
                $monto = 0.0;
                foreach ($ingTotales['fichasBase'] as $fRow) {
                    $v = $fRow[$c['key']] ?? null;
                    if ($v !== null && $v !== '') {
                        $monto += (float) $v;
                    }
                }
                $detallePorFuente[$clave]['subtotales'][$c['subtipo']]['monto'] = $monto;
                $sumSub += $monto;
            }
            if ($clave === 'inversiones' && $sumSub <= 0) {
                $canon = (float) ($ingTotales['porFuente']['inversiones'] ?? 0);
                if ($canon > 0 && isset($detallePorFuente[$clave]['subtotales']['arriendos'])) {
                    $detallePorFuente[$clave]['subtotales']['arriendos']['monto'] = $canon;
                }
            }
        }

        $secciones = [];
        foreach ($fuentes as $clave => $meta) {
            $secciones[] = [
                'clave'      => $clave,
                'meta'       => $meta,
                'total'      => (float) ($ingTotales['porFuente'][$clave] ?? 0),
                'subtotales' => array_values($detallePorFuente[$clave]['subtotales']),
                'vigentes'   => $detallePorFuente[$clave]['vigentes'],
                'vencidos'   => $detallePorFuente[$clave]['vencidos'],
            ];
        }

        return view('equity/reporte_ingresos', [
            'title'         => 'Reporte Ingresos',
            'memb'          => $memb,
            'equityNav'     => 'ingresos',
            'personas'      => $personas,
            'personaId'     => $personaId,
            'personaNombre' => $personaNombre,
            'ufVigente'     => $ufVigente,
            'fuentes'       => $fuentes,
            'totalesFuente' => $ingTotales['porFuente'],
            'totalGeneral'  => (float) $ingTotales['total'],
            'secciones'     => $secciones,
            'canManage'     => EquityAcl::canDeclare($memb),
        ]);
    }

    public function reporteEgresos()
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
        $fuentes   = EquityCatalogo::fuentesEgreso();

        $totalesFuente = [];
        $secciones     = [];
        $totalGeneral  = 0.0;

        foreach ($fuentes as $clave => $meta) {
            $q = model(EquityPasivoModel::class)
                ->where('categoria', $meta['categoria'])
                ->orderBy('banco_descripcion', 'ASC')
                ->orderBy('numero_credito', 'ASC');
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
            }
            $rows = $q->findAll();
            $detalle = [];
            $sum = 0.0;
            foreach ($rows as $row) {
                $cuotaClp = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
                $cuotaUf  = EquityEgresosProyeccion::cuotaUf($row, $ufRate);
                $moneda   = EquityEgresosProyeccion::moneda($row);
                if ($cuotaClp !== null) {
                    $sum += $cuotaClp;
                }
                $saldoClp = null;
                if (isset($row['saldo_clp']) && $row['saldo_clp'] !== null && $row['saldo_clp'] !== '') {
                    $saldoClp = (float) $row['saldo_clp'];
                } elseif (isset($row['saldo_uf']) && $row['saldo_uf'] !== null && $row['saldo_uf'] !== '' && $ufRate > 0) {
                    $saldoClp = round((float) $row['saldo_uf'] * $ufRate, 2);
                }
                $saldoUf = null;
                if (isset($row['saldo_uf']) && $row['saldo_uf'] !== null && $row['saldo_uf'] !== '') {
                    $saldoUf = (float) $row['saldo_uf'];
                } elseif ($saldoClp !== null && $ufRate > 0) {
                    $saldoUf = round($saldoClp / $ufRate, 4);
                }
                $personaLab = '';
                $pid = (string) ($row['persona_id'] ?? '');
                if ($personaId === '' && $pid !== '' && isset($personaMap[$pid])) {
                    $pp = $personaMap[$pid];
                    $personaLab = trim(($pp['nombre'] ?? '') . ' ' . ($pp['apellido'] ?? ''));
                }
                $detalle[] = [
                    'institucion'     => $row['banco_descripcion'] ?? null,
                    'numero'          => $row['numero_credito'] ?? null,
                    'tipo'            => $row['tipo'] ?? null,
                    'moneda'          => $moneda,
                    'fecha_registro'  => $row['fecha_registro'] ?? $row['fecha_otorgamiento'] ?? null,
                    'fecha_vencimiento' => $row['fecha_vencimiento'] ?? null,
                    'dividendo_n'     => $row['dividendo_n'] ?? null,
                    'saldo_uf'        => $saldoUf,
                    'saldo_clp'       => $saldoClp,
                    'cuota_clp'       => $cuotaClp,
                    'cuota_uf'        => $cuotaUf,
                    'persona'         => $personaLab !== '' ? $personaLab : null,
                ];
            }
            $totalesFuente[$clave] = $sum;
            $totalGeneral += $sum;
            $secciones[] = [
                'clave'   => $clave,
                'meta'    => $meta,
                'total'   => $sum,
                'conteo'  => count($detalle),
                'detalle' => $detalle,
            ];
        }

        // Stock de pasivos (misma base que Resumen / Balance), no cuotas mensuales.
        $pasivosDirectosUf = $this->sumPasivoCategoria($empresaId, 'inmueble', $personaId, $ufRate)
            + $this->sumPasivoCategoria($empresaId, 'circulante', $personaId, $ufRate)
            + $this->sumPasivoCategoria($empresaId, 'otro', $personaId, $ufRate);
        $pasivosIndirectaUf = $this->sumDeudaIndirecta($empresaId, $personaId, $ufRate);
        $totalPasivosDirectos = round($pasivosDirectosUf * $ufRate, 2);
        $totalPasivosIndirecta = round($pasivosIndirectaUf * $ufRate, 2);
        $totalPasivosConIndirectos = round(($pasivosDirectosUf + $pasivosIndirectaUf) * $ufRate, 2);

        // Detalle de referencia: deuda indirecta (no entra a egresos mensuales).
        $qInd = model(EquityPasivoModel::class)
            ->where('categoria', 'indirecta')
            ->orderBy('banco_descripcion', 'ASC');
        if ($personaId !== '') {
            $qInd->where('persona_id', $personaId);
        } else {
            $qInd->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $indirectaDetalle = [];
        foreach ($qInd->findAll() as $row) {
            $vigenteClp = $this->montoClpPreferido(
                $row['vigente_clp'] ?? null,
                $row['vigente_uf'] ?? null,
                $row['total_credito_clp'] ?? null,
                $row['total_credito_uf'] ?? null,
                $ufRate
            );
            if ($vigenteClp === null) {
                $vigenteClp = $this->montoClpPreferido(
                    $row['saldo_clp'] ?? null,
                    $row['saldo_uf'] ?? null,
                    null,
                    null,
                    $ufRate
                );
            }
            $personaLab = '';
            $pid = (string) ($row['persona_id'] ?? '');
            if ($personaId === '' && $pid !== '' && isset($personaMap[$pid])) {
                $pp = $personaMap[$pid];
                $personaLab = trim(($pp['nombre'] ?? '') . ' ' . ($pp['apellido'] ?? ''));
            }
            $indirectaDetalle[] = [
                'institucion' => $row['banco_descripcion'] ?? null,
                'tipo'        => $row['tipo'] ?? null,
                'tomador'     => $row['tomador_nombre'] ?? null,
                'tomador_rut' => $row['tomador_rut'] ?? null,
                'vigente_clp' => $vigenteClp,
                'persona'     => $personaLab !== '' ? $personaLab : null,
            ];
        }

        // Referencia: líneas / cupos disponibles (no suman a egresos ni a pasivos).
        $lineasUf = $this->sumLineasDisponibles($empresaId, $personaId, $ufRate);
        $totalLineasDisponibles = round($lineasUf * $ufRate, 2);
        $qLin = model(EquityPasivoModel::class)
            ->where('categoria', 'linea')
            ->orderBy('banco_descripcion', 'ASC');
        if ($personaId !== '') {
            $qLin->where('persona_id', $personaId);
        } else {
            $qLin->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $lineasDetalle = [];
        foreach ($qLin->findAll() as $row) {
            $cupoClp = $this->montoClpPreferido(
                $row['total_credito_clp'] ?? null,
                $row['total_credito_uf'] ?? null,
                $row['saldo_clp'] ?? null,
                $row['saldo_uf'] ?? null,
                $ufRate
            );
            if ($cupoClp === null) {
                $dir = $this->montoClpPreferido(
                    $row['monto_directo_clp'] ?? null,
                    $row['monto_directo_uf'] ?? null,
                    null,
                    null,
                    $ufRate
                );
                $ind = $this->montoClpPreferido(
                    $row['monto_indirecto_clp'] ?? null,
                    $row['monto_indirecto_uf'] ?? null,
                    null,
                    null,
                    $ufRate
                );
                if ($dir !== null || $ind !== null) {
                    $cupoClp = (float) ($dir ?? 0) + (float) ($ind ?? 0);
                }
            }
            $personaLab = '';
            $pid = (string) ($row['persona_id'] ?? '');
            if ($personaId === '' && $pid !== '' && isset($personaMap[$pid])) {
                $pp = $personaMap[$pid];
                $personaLab = trim(($pp['nombre'] ?? '') . ' ' . ($pp['apellido'] ?? ''));
            }
            $lineasDetalle[] = [
                'institucion' => $row['banco_descripcion'] ?? null,
                'tipo'        => $row['tipo'] ?? null,
                'cupo_clp'    => $cupoClp,
                'persona'     => $personaLab !== '' ? $personaLab : null,
            ];
        }

        return view('equity/reporte_egresos', [
            'title'                      => 'Reporte Egresos',
            'memb'                       => $memb,
            'equityNav'                  => 'egresos',
            'personas'                   => $personas,
            'personaId'                  => $personaId,
            'personaNombre'              => $personaNombre,
            'ufVigente'                  => $ufVigente,
            'fuentes'                    => $fuentes,
            'totalesFuente'              => $totalesFuente,
            'totalGeneral'               => $totalGeneral,
            'secciones'                  => $secciones,
            'indirectaDetalle'           => $indirectaDetalle,
            'lineasDetalle'              => $lineasDetalle,
            'totalPasivosDirectos'       => $totalPasivosDirectos,
            'totalPasivosIndirecta'      => $totalPasivosIndirecta,
            'totalPasivosConIndirectos'  => $totalPasivosConIndirectos,
            'totalLineasDisponibles'     => $totalLineasDisponibles,
            'canManage'                  => EquityAcl::canDeclare($memb),
        ]);
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
     * Desarrollo editable de un crédito (cuotas mes a mes).
     */
    public function desarrolloPasivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $clave = $this->clavePasivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        if ($meta === null || ($meta['table'] ?? '') !== 'equity_pasivos') {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Sección no válida.');
        }
        if (! in_array((string) ($meta['categoria'] ?? ''), ['inmueble', 'circulante', 'otro'], true)) {
            return redirect()->to('/equity/pasivos/' . $seccion)
                ->with('error', 'El desarrollo aplica a Hipotecarios, Circulantes y Otros.');
        }

        $empresaId = $memb['empresa_id'];
        $row = $this->findRegistro($meta, $empresaId, $id);
        if ($row === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Pasivo no encontrado.');
        }

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);
        $moneda    = EquityEgresosProyeccion::moneda($row);
        $saved     = EquityDesarrolloCuotas::listar($id);
        $origen    = 'guardado';
        if ($saved === []) {
            $origen = 'proyeccion';
            $saved  = EquityEgresosProyeccion::proyectar($row, $ufRate, 24);
        }
        $cuotas = [];
        foreach ($saved as $c) {
            $cuotas[] = EquityDesarrolloCuotas::normalizarFila($c, $moneda, $ufRate);
        }

        return view('equity/pasivo_desarrollo', [
            'title'     => 'Desarrollo · ' . ($row['banco_descripcion'] ?? 'Crédito'),
            'memb'      => $memb,
            'equityNav' => $clave,
            'seccion'   => $seccion,
            'meta'      => $meta,
            'pasivo'    => $row,
            'cuotas'    => $cuotas,
            'origen'    => $origen,
            'moneda'    => $moneda,
            'ufVigente' => $ufVigente,
            'tablasOk'  => EquityDesarrolloCuotas::tablasListas(),
            'canManage' => EquityAcl::canDeclare($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function guardarDesarrolloPasivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Sin permiso para editar el desarrollo.');
        }
        if (! EquityDesarrolloCuotas::tablasListas()) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Falta la migración de cuotas (000039).');
        }

        $clave = $this->clavePasivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Sección no válida.');
        }
        $row = $this->findRegistro($meta, $memb['empresa_id'], $id);
        if ($row === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Pasivo no encontrado.');
        }

        $fechas   = (array) $this->request->getPost('fecha');
        $divs     = (array) $this->request->getPost('dividendo_n');
        $cuotaUf  = (array) $this->request->getPost('cuota_uf');
        $cuotaClp = (array) $this->request->getPost('cuota_clp');
        $amortUf  = (array) $this->request->getPost('amortizacion_uf');
        $amortClp = (array) $this->request->getPost('amortizacion_clp');
        $intUf    = (array) $this->request->getPost('interes_uf');
        $intClp   = (array) $this->request->getPost('interes_clp');
        $saldoUf  = (array) $this->request->getPost('saldo_uf');
        $saldoClp = (array) $this->request->getPost('saldo_clp');
        $n = max(count($fechas), count($divs), count($cuotaUf), count($cuotaClp));

        $filas = [];
        for ($i = 0; $i < $n; $i++) {
            $filas[] = [
                'fecha'            => $fechas[$i] ?? null,
                'dividendo_n'      => $divs[$i] ?? null,
                'cuota_uf'         => $cuotaUf[$i] ?? null,
                'cuota_clp'        => $cuotaClp[$i] ?? null,
                'amortizacion_uf'  => $amortUf[$i] ?? null,
                'amortizacion_clp' => $amortClp[$i] ?? null,
                'interes_uf'       => $intUf[$i] ?? null,
                'interes_clp'      => $intClp[$i] ?? null,
                'saldo_uf'         => $saldoUf[$i] ?? null,
                'saldo_clp'        => $saldoClp[$i] ?? null,
            ];
        }

        $saved = EquityDesarrolloCuotas::reemplazar(
            $id,
            (string) $memb['empresa_id'],
            isset($row['persona_id']) ? (string) $row['persona_id'] : null,
            $filas,
            'manual',
            EquityEgresosProyeccion::moneda($row)
        );

        return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
            ->with('success', 'Desarrollo guardado (' . $saved . ' cuota(s)).');
    }

    public function importarDesarrolloPasivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Sin permiso para importar.');
        }
        if (! EquityDesarrolloCuotas::tablasListas()) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Falta la migración de cuotas (000039).');
        }

        $clave = $this->clavePasivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Sección no válida.');
        }
        $empresaId = $memb['empresa_id'];
        $row = $this->findRegistro($meta, $empresaId, $id);
        if ($row === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Pasivo no encontrado.');
        }

        $file = $this->request->getFile('desarrollo_archivo');
        if ($file === null || ! $file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Selecciona un archivo Excel (.xlsx), CSV o PDF.');
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'El archivo supera 20 MB.');
        }

        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'No se pudo leer el archivo.');
        }

        $parsed = EquityDesarrolloImport::parse(
            $file->getClientName(),
            $binary,
            (string) ($file->getMimeType() ?: '')
        );

        // Guardar siempre el archivo como respaldo del desarrollo (aunque el parse falle).
        helper('id');
        $docId = new_id();
        $archivoOk = false;
        try {
            $savedFile = DocumentStorage::save(
                $empresaId,
                $docId,
                $file->getClientName(),
                $binary,
                'equity'
            );
            $patch = [
                'desarrollo_archivo_nombre' => $file->getClientName(),
                'desarrollo_archivo_ruta'   => $savedFile['ruta_relativa'],
                'desarrollo_archivo_mime'   => $file->getMimeType() ?: 'application/octet-stream',
                'desarrollo_archivo_bytes'  => $savedFile['tamano_bytes'],
            ];
            if (db_connect()->fieldExists('desarrollo_archivo_nombre', 'equity_pasivos')) {
                // Borrar archivo anterior si existía.
                if (! empty($row['desarrollo_archivo_ruta'])) {
                    try {
                        DocumentStorage::delete((string) $row['desarrollo_archivo_ruta']);
                    } catch (\Throwable) {
                    }
                }
                model(EquityPasivoModel::class)->update($id, $patch);
                $archivoOk = true;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Equity desarrollo upload failed: {msg}', ['msg' => $e->getMessage()]);
        }

        $redir = '/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo';
        if (! ($parsed['ok'] ?? false)) {
            $msg = $parsed['error'] ?? 'No se pudo importar el desarrollo.';
            if ($archivoOk) {
                $msg .= ' El archivo quedó guardado como respaldo.';
            }

            return redirect()->to($redir)->with('error', $msg);
        }

        $stats = EquityDesarrolloCuotas::fusionarImport(
            $id,
            $empresaId,
            isset($row['persona_id']) ? (string) $row['persona_id'] : null,
            $parsed['filas'] ?? [],
            EquityEgresosProyeccion::moneda($row)
        );

        $cols = implode(', ', $parsed['columnas'] ?? []);
        $msg = 'Importadas ' . (int) ($stats['inserted'] ?? 0) . ' fila(s) nuevas, '
            . (int) ($stats['updated'] ?? 0) . ' actualizada(s)';
        if ($cols !== '') {
            $msg .= ' · columnas: ' . $cols;
        }
        if (! empty($parsed['detalle'])) {
            $msg .= '. ' . $parsed['detalle'];
        }
        if ($archivoOk) {
            $msg .= ' Archivo adjunto guardado.';
        }

        return redirect()->to($redir)->with('success', $msg);
    }

    public function regenerarDesarrolloPasivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Sin permiso.');
        }

        $clave = $this->clavePasivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Sección no válida.');
        }
        $row = $this->findRegistro($meta, $memb['empresa_id'], $id);
        if ($row === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Pasivo no encontrado.');
        }

        $ufRate = EquityUf::valorClp($memb['empresa_id']);
        $proy = EquityEgresosProyeccion::proyectar($row, $ufRate, 24);
        $n = EquityDesarrolloCuotas::reemplazar(
            $id,
            (string) $memb['empresa_id'],
            isset($row['persona_id']) ? (string) $row['persona_id'] : null,
            $proy,
            'proyeccion',
            EquityEgresosProyeccion::moneda($row)
        );

        return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
            ->with('success', 'Desarrollo regenerado desde proyección (' . $n . ' cuota(s)). Puedes editarlo.');
    }

    public function descargarDesarrolloArchivo(string $seccion, string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        $clave = $this->clavePasivo($seccion);
        $meta  = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/equity/pasivos/' . $seccion)->with('error', 'Sección no válida.');
        }
        $row = $this->findRegistro($meta, $memb['empresa_id'], $id);
        if ($row === null || empty($row['desarrollo_archivo_ruta'])) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Sin archivo de desarrollo.');
        }

        try {
            $data = DocumentStorage::read((string) $row['desarrollo_archivo_ruta']);
        } catch (\Throwable) {
            return redirect()->to('/equity/pasivos/' . $seccion . '/' . $id . '/desarrollo')
                ->with('error', 'Archivo no encontrado en storage.');
        }
        $nombre = (string) ($row['desarrollo_archivo_nombre'] ?? 'desarrollo.bin');
        $mime   = (string) ($row['desarrollo_archivo_mime'] ?? 'application/octet-stream');

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . str_replace('"', '', $nombre) . '"')
            ->setBody($data);
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
        $rowsDirectos = [];
        $rowsIndirecta = [];
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
            if (($row['categoria'] ?? '') === 'indirecta') {
                $rowsIndirecta[] = $row;
            } else {
                $rowsDirectos[] = $row;
            }
        }
        unset($row);

        // Misma lógica que hub Ingresos / Balance (contratos → ficha → cánones; sin doble conteo).
        $fuentesIngreso = EquityCatalogo::fuentesIngreso();
        $ingTotales     = $this->totalesIngresosMensuales($empresaId, $personaId, $ufRate);
        $totalesFuente  = $ingTotales['porFuente'];
        $totalIngresosReportados = (float) $ingTotales['total'];
        $ingresoMensual = $totalIngresosReportados > 0 ? $totalIngresosReportados : null;

        // Última ficha por persona (detalle; no se suman cortes históricos).
        $ingresos = [];
        foreach ($ingTotales['fichasBase'] as $ing) {
            $ing['persona'] = $personaMap[$ing['persona_id']] ?? null;
            $ingresos[] = $ing;
        }
        $totalFichasIngresos = 0.0;
        foreach ($ingresos as $ing) {
            $v = $ing['ingreso_mensual_total'] ?? $ing['renta_liquida'] ?? null;
            if ($v !== null && $v !== '') {
                $totalFichasIngresos += (float) $v;
            }
        }

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
                'persona'         => $p,
                'fuente'          => 'Antecedentes laborales',
                'renta_liquida'   => $renta1,
                'renta_liquida_2' => $renta2,
                'empleador'       => $p['nombre_empleador'] ?? null,
                'cargo'           => $p['cargo_actividad'] ?? null,
            ];
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

        // Detalle de arriendos: contratos vigentes (1 por bien); no se suman otra vez al total.
        $arriendos = $this->detalleArriendosSituacion($empresaId, $personaId, $ufRate, $personaMap);
        $totalArriendos = 0.0;
        foreach ($arriendos as $ar) {
            $totalArriendos += (float) ($ar['canon_show'] ?? 0);
        }

        $totalCuotas = 0.0;
        foreach ($rowsDirectos as $r) {
            if ($r['cuota_show'] !== null) {
                $totalCuotas += (float) $r['cuota_show'];
            }
        }

        return view('equity/situacion_financiera', [
            'title'                   => 'Situación financiera',
            'memb'                    => $memb,
            'equityNav'               => 'situacion-financiera',
            'rows'                    => $rowsDirectos,
            'rowsIndirecta'           => $rowsIndirecta,
            'ingresos'                => $ingresos,
            'ingresosLaborales'       => $ingresosLaborales,
            'arriendos'               => $arriendos,
            'totalArriendos'          => $totalArriendos,
            'totalFichasIngresos'     => $totalFichasIngresos,
            'fuentesIngreso'          => $fuentesIngreso,
            'totalesFuente'           => $totalesFuente,
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

        $fuentes = EquityCatalogo::fuentesIngreso();
        $ingTotales = $this->totalesIngresosMensuales($empresaId, $personaId, $ufRate);
        $fichasBase = $ingTotales['fichasBase'];
        $ficha = $fichasBase[0] ?? null;
        $totalesFuente = $ingTotales['porFuente'];
        $totalGeneral = (float) $ingTotales['total'];

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
            foreach ($rows as $row) {
                $cuota = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
                if ($cuota !== null) {
                    $sum += $cuota;
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
            if ($cuota !== null) {
                $totalFuente += $cuota;
            }

            $cuotas = EquityDesarrolloCuotas::paraProyeccion($row, $ufRate, $meses);
            if ($cuotas !== []) {
                $seccionUrl = match ((string) ($row['categoria'] ?? '')) {
                    'inmueble'   => 'inmuebles',
                    'circulante' => 'circulantes',
                    'otro'       => 'otros',
                    default      => null,
                };
                $desarrollos[] = [
                    'pasivo_id' => (string) ($row['id'] ?? ''),
                    'seccion'   => $seccionUrl,
                    'label'     => (string) ($row['banco_descripcion'] ?? 'Crédito'),
                    'numero'    => $row['numero_credito'] ?? null,
                    'moneda'    => EquityEgresosProyeccion::moneda($row),
                    'editable'  => EquityDesarrolloCuotas::listar((string) ($row['id'] ?? '')) !== [],
                    'cuotas'    => $cuotas,
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
        $desdeId   = trim((string) $this->request->getGet('desde'));

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
        $desdeRow = null;
        foreach ($contratos as &$c) {
            $c['monto_mensual'] = EquityCatalogo::montoMensualEquivalente(
                isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                $c['periodicidad'] ?? 'mensual'
            );
            if ($editId !== '' && ($c['id'] ?? '') === $editId) {
                $editRow = $c;
            }
            if ($desdeId !== '' && ($c['id'] ?? '') === $desdeId) {
                $desdeRow = $c;
            }
        }
        unset($c);

        // Prefill de sucesor: nuevo contrato con hilo histórico al vencido/anterior.
        if ($editRow === null && is_array($desdeRow)) {
            $editRow = $desdeRow;
            $editRow['_sucesor_de'] = (string) ($desdeRow['id'] ?? '');
            $editRow['estado'] = 'vigente';
            $editRow['fecha_inicio_contrato'] = date('Y-m-d');
            $editRow['fecha_vencimiento'] = null;
            unset($editRow['id'], $editRow['contrato_archivo_nombre'], $editRow['contrato_ruta_relativa'],
                $editRow['contrato_mime'], $editRow['contrato_tamano_bytes']);
        }

        $arriendos = [];
        $totalCanon = 0.0;
        $bienesOpciones = [];
        $contratosPorBien = [];
        $reclasificable = in_array($fuente, EquityCatalogo::fuentesIngresoReclasificables(), true);
        // Bienes disponibles también en Otros: permite reclasificar a/desde Arriendos.
        if ($fuente === 'inversiones' || $reclasificable) {
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
                if ($fuente !== 'inversiones') {
                    continue;
                }
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
            'title'           => $meta['label'],
            'memb'            => $memb,
            'equityNav'       => 'ingresos-' . $fuente,
            'fuente'          => $fuente,
            'meta'            => $meta,
            'ficha'           => $ficha,
            'contratos'       => $contratos,
            'editRow'         => $editRow,
            'personas'        => $personas,
            'personaId'       => $personaId,
            'arriendos'       => $arriendos,
            'bienesOpciones'  => $bienesOpciones,
            'totalCanon'      => $totalCanon,
            'reclasificable'  => $reclasificable,
            'ufVigente'       => $ufVigente,
            'canManage'       => EquityAcl::canDeclare($memb),
            'success'         => session()->getFlashdata('success'),
            'error'           => session()->getFlashdata('error'),
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

        $reclasificable = in_array($fuente, EquityCatalogo::fuentesIngresoReclasificables(), true);
        $subtipos = [];
        if ($reclasificable) {
            foreach (EquityCatalogo::subtiposIngresoReclasificables() as $st => $c) {
                $subtipos[$st] = $c;
            }
        } else {
            foreach ($fuentes[$fuente]['campos'] as $c) {
                $subtipos[$c['subtipo']] = $c;
            }
        }
        $subtipo = trim((string) $this->request->getPost('subtipo'));
        if (! isset($subtipos[$subtipo])) {
            return redirect()->to($redirect)->with('error', 'Tipo de ingreso no válido.');
        }
        // Si el tipo pertenece a otra fuente reclasificable, mover el contrato allí.
        $fuenteFinal = $fuente;
        if ($reclasificable) {
            $fuenteDeTipo = EquityCatalogo::fuenteDeSubtipoIngreso($subtipo);
            if ($fuenteDeTipo !== null && in_array($fuenteDeTipo, EquityCatalogo::fuentesIngresoReclasificables(), true)) {
                $fuenteFinal = $fuenteDeTipo;
            }
        }
        $redirectFinal = '/equity/ingresos/' . $fuenteFinal . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : '');

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
            'fuente'                => $fuenteFinal,
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

        // Hilo histórico: al crear un contrato nuevo que reemplaza uno vencido.
        $hasReemplaza = $db->fieldExists('reemplaza_contrato_id', 'equity_ingresos_contratos');
        if ($hasReemplaza && ! $isEdit) {
            $reemplazaId = trim((string) $this->request->getPost('reemplaza_contrato_id'));
            if ($reemplazaId !== '') {
                $prev = model(EquityIngresoContratoModel::class)
                    ->where('id', $reemplazaId)
                    ->where('empresa_id', $empresaId)
                    ->where('persona_id', $personaId)
                    ->first();
                if (is_array($prev)) {
                    $data['reemplaza_contrato_id'] = $reemplazaId;
                    // Si el anterior sigue vigente, marcarlo vencido al crear el sucesor.
                    if (($prev['estado'] ?? '') === 'vigente') {
                        model(EquityIngresoContratoModel::class)->update($reemplazaId, [
                            'estado' => 'vencido',
                        ]);
                    }
                }
            }
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
            $msg = $fuenteFinal !== $fuente
                ? 'Contrato actualizado y movido a ' . ($fuentes[$fuenteFinal]['label'] ?? $fuenteFinal) . '.'
                : 'Contrato / contraparte actualizado.';
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
            $msg = $fuenteFinal !== $fuente
                ? 'Contrato agregado en ' . ($fuentes[$fuenteFinal]['label'] ?? $fuenteFinal) . '.'
                : 'Contrato / contraparte agregado.';
        }

        $this->sincronizarFichaDesdeContratos($empresaId, $personaId, $memb['usuario_id']);

        return redirect()->to($redirectFinal)->with('success', $msg);
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
            'ingreso_capital_er', 'ingreso_intereses_er',
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

    /**
     * Totales mensuales de ingresos por fuente (hub Ingresos / Balance / Situación financiera).
     * Orden: contratos vigentes → campos de última ficha → cánones de bienes (solo si inversiones=0).
     * Arriendos con el mismo bien_raiz_id: se cuenta un solo contrato vigente (evita duplicados).
     *
     * @return array{porFuente: array<string, float>, total: float, fichasBase: list<array<string, mixed>>}
     */
    private function totalesIngresosMensuales(string $empresaId, string $personaId, float $ufRate): array
    {
        $fuentes = EquityCatalogo::fuentesIngreso();
        $fichasBase = [];
        if ($personaId !== '') {
            $f = $this->ultimaFicha($empresaId, $personaId);
            if (is_array($f)) {
                $fichasBase[] = $f;
            }
        } else {
            $seen = [];
            foreach (model(EquityFichaModel::class)
                ->whereIn('empresa_id', $this->empresaScopeIds($empresaId))
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

        $cq = model(EquityIngresoContratoModel::class)->where('estado', 'vigente');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }

        $sumasContrato = [];
        $subtipoFuente = [];
        foreach ($fuentes as $clave => $meta) {
            $sumasContrato[$clave] = 0.0;
            foreach ($meta['campos'] as $c) {
                $subtipoFuente[$c['subtipo']] = $clave;
            }
        }

        $seenBienArriendo = [];
        foreach ($cq->findAll() as $ct) {
            $subtipo = (string) ($ct['subtipo'] ?? '');
            $fClave = $subtipoFuente[$subtipo] ?? ($ct['fuente'] ?? null);
            if ($fClave === null || ! isset($sumasContrato[$fClave])) {
                continue;
            }
            // Un solo contrato vigente de arriendo por bien raíz.
            if ($subtipo === 'arriendos') {
                $bid = (string) ($ct['bien_raiz_id'] ?? '');
                if ($bid !== '') {
                    if (isset($seenBienArriendo[$bid])) {
                        continue;
                    }
                    $seenBienArriendo[$bid] = true;
                }
            }
            $sumasContrato[$fClave] += EquityCatalogo::montoMensualEquivalente(
                isset($ct['monto_contrato_clp']) ? (float) $ct['monto_contrato_clp'] : null,
                $ct['periodicidad'] ?? 'mensual'
            );
        }

        $porFuente = [];
        $total = 0.0;
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
            $porFuente[$clave] = $sum;
            $total += $sum;
        }

        return [
            'porFuente'  => $porFuente,
            'total'      => $total,
            'fichasBase' => $fichasBase,
        ];
    }

    /**
     * Detalle de arriendos para Situación financiera (informativo; el total ya va en Inversiones).
     * Preferir contratos vigentes (1 por bien); si no hay, cánones de bienes raíces.
     *
     * @param array<string, array<string, mixed>> $personaMap
     * @return list<array<string, mixed>>
     */
    private function detalleArriendosSituacion(string $empresaId, string $personaId, float $ufRate, array $personaMap): array
    {
        $cq = model(EquityIngresoContratoModel::class)
            ->where('estado', 'vigente')
            ->where('subtipo', 'arriendos')
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        $contratos = $cq->findAll();

        $bienIds = [];
        foreach ($contratos as $c) {
            $bid = (string) ($c['bien_raiz_id'] ?? '');
            if ($bid !== '') {
                $bienIds[$bid] = true;
            }
        }
        $bienMap = [];
        if ($bienIds !== []) {
            foreach (model(EquityBienRaizModel::class)->whereIn('id', array_keys($bienIds))->findAll() as $br) {
                $bienMap[(string) $br['id']] = $br;
            }
        }

        $arriendos = [];
        $seenBien = [];
        foreach ($contratos as $c) {
            $bid = (string) ($c['bien_raiz_id'] ?? '');
            if ($bid !== '') {
                if (isset($seenBien[$bid])) {
                    continue;
                }
                $seenBien[$bid] = true;
            }
            $br = $bid !== '' ? ($bienMap[$bid] ?? null) : null;
            $monto = EquityCatalogo::montoMensualEquivalente(
                isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                $c['periodicidad'] ?? 'mensual'
            );
            if ($monto <= 0) {
                continue;
            }
            $arriendos[] = [
                'id'           => $c['id'] ?? null,
                'bien_raiz_id' => $bid !== '' ? $bid : null,
                'direccion'    => $br['direccion'] ?? ($br['descripcion'] ?? null)
                    ?? (preg_match('/Bien raíz:\s*(.+?)(?:\s·|$)/u', (string) ($c['notas'] ?? ''), $m) ? $m[1] : null),
                'arrendatario' => $c['contraparte_nombre'] ?? ($br['arrendatario'] ?? null),
                'mes_canon'    => $br['mes_canon'] ?? null,
                'medio_pago'   => $br['medio_pago'] ?? null,
                'canon_show'   => $monto,
                'persona'      => $personaMap[$c['persona_id'] ?? ''] ?? null,
                'origen'       => 'contrato',
            ];
        }

        if ($arriendos !== []) {
            return $arriendos;
        }

        // Fallback: bienes con cánon (solo detalle; el total ya los incluye si no hay contratos/ficha).
        $bq = model(EquityBienRaizModel::class)->orderBy('direccion', 'ASC');
        $this->soloBienesActivos($bq);
        if ($personaId !== '') {
            $bq->where('persona_id', $personaId);
        } else {
            $bq->whereIn('empresa_id', $this->empresaScopeIds($empresaId));
        }
        foreach ($bq->findAll() as $br) {
            $canon = EquityArriendoSync::canonClp($br, $ufRate);
            if ($canon <= 0) {
                continue;
            }
            $br['persona'] = $personaMap[$br['persona_id'] ?? ''] ?? null;
            $br['canon_show'] = $canon;
            $br['origen'] = 'bien';
            $arriendos[] = $br;
        }

        return $arriendos;
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
            $n = EquityUf::parseDecimal((string) $raw, 2);

            return $n !== null ? number_format($n, 2, '.', '') : null;
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
        } elseif ($clave === 'pasivos-indirecta' || $clave === 'pasivos-lineas') {
            $esIndirecta = ($clave === 'pasivos-indirecta');
            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La institución financiera es obligatoria.');
            }
            $monedaCupo = strtoupper(trim((string) $this->request->getPost('moneda')));
            if ($monedaCupo !== 'UF' && $monedaCupo !== 'CLP') {
                $monedaCupo = 'CLP';
            }

            if ($esIndirecta) {
                $tipoCredito = trim((string) $this->request->getPost('tipo_credito'));
                if ($tipoCredito === '') {
                    return redirect()->to($redirectPath)->with('error', 'El tipo de crédito es obligatorio.');
                }
                $tomadorNombre = trim((string) $this->request->getPost('tomador_nombre'));
                $tomadorRut    = trim((string) $this->request->getPost('tomador_rut'));
                if ($tomadorNombre === '' || $tomadorRut === '') {
                    return redirect()->to($redirectPath)->with('error', 'Nombre/razón social y RUT del tomador son obligatorios.');
                }
                $tipoLabel = $tipoCredito;
            } else {
                $tipoLinea = $intOrNull($this->request->getPost('tipo_linea'));
                if ($tipoLinea === null || ! isset(EquityCatalogo::opcionesTipoLineaDisponible()[$tipoLinea])) {
                    return redirect()->to($redirectPath)->with('error', 'Selecciona el tipo de línea.');
                }
                $tipoLabel     = EquityCatalogo::opcionesTipoLineaDisponible()[$tipoLinea];
                $tomadorNombre = null;
                $tomadorRut    = null;
            }

            $parTotal = EquityUf::resolverPar(
                (string) $this->request->getPost('total_credito_uf'),
                (string) $this->request->getPost('total_credito_clp'),
                $memb['empresa_id'],
                null,
                $monedaCupo
            );
            $parVig = EquityUf::resolverPar(
                (string) $this->request->getPost('vigente_uf'),
                (string) $this->request->getPost('vigente_clp'),
                $memb['empresa_id'],
                null,
                $monedaCupo
            );
            $compMora = EquityUf::resolverParesComponentes(
                ['mora_30_59', 'mora_60_89', 'mora_90_mas'],
                fn (string $k) => $this->request->getPost($k),
                $memb['empresa_id'],
                $monedaCupo
            );

            $model = model(EquityPasivoModel::class);
            $data  = [
                'persona_id'         => $personaId,
                'categoria'          => $esIndirecta ? 'indirecta' : 'linea',
                'banco_descripcion'  => $desc,
                'tipo'               => $tipoLabel,
                'moneda'             => $monedaCupo,
                'tomador_nombre'     => $tomadorNombre,
                'tomador_rut'        => $tomadorRut,
                'fecha_otorgamiento' => trim((string) $this->request->getPost('fecha_otorgamiento')) ?: null,
                'total_credito_uf'   => $parTotal['uf'],
                'total_credito_clp'  => $parTotal['clp'],
                'vigente_uf'         => $parVig['uf'],
                'vigente_clp'        => $parVig['clp'],
                'mora_30_59_uf'      => $compMora['mora_30_59_uf'],
                'mora_30_59_clp'     => $compMora['mora_30_59_clp'],
                'mora_60_89_uf'      => $compMora['mora_60_89_uf'],
                'mora_60_89_clp'     => $compMora['mora_60_89_clp'],
                'mora_90_mas_uf'     => $compMora['mora_90_mas_uf'],
                'mora_90_mas_clp'    => $compMora['mora_90_mas_clp'],
                'saldo_uf'           => $parVig['uf'] ?? $parTotal['uf'],
                'saldo_clp'          => $parVig['clp'] ?? $parTotal['clp'],
                'notas'              => trim((string) $this->request->getPost('notas')) ?: null,
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
            $saldoUfRaw  = trim((string) $this->request->getPost('saldo_uf'));
            $saldoClpRaw = trim((string) $this->request->getPost('saldo_clp'));
            if ($monedaOtro === 'CLP' && $saldoClpRaw !== '' && EquityUf::parseDecimal($saldoClpRaw, 2) === null) {
                return redirect()->to($redirectPath)->with('error', 'No se pudo interpretar el saldo en $. Usa 1500000 o 1.500.000');
            }
            if ($monedaOtro === 'UF' && $saldoUfRaw !== '' && EquityUf::parseDecimal($saldoUfRaw, 4) === null) {
                return redirect()->to($redirectPath)->with('error', 'No se pudo interpretar el saldo en UF.');
            }
            $parSaldoOtro = EquityUf::resolverPar(
                $saldoUfRaw,
                $saldoClpRaw,
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
            if ($model->update($editId, $data) === false) {
                $err = implode(' ', $model->errors() ?: ['No se pudo actualizar el registro.']);

                return redirect()->to($redirectPath)->with('error', $err);
            }
            $activoId = (string) $editId;
            $msg = 'Registro actualizado.';
        } else {
            $before = null;
            $data['id']            = new_id();
            $data['empresa_id']    = $memb['empresa_id'];
            $data['creado_por_id'] = $memb['usuario_id'];
            if ($model->insert($data) === false) {
                $err = implode(' ', $model->errors() ?: ['No se pudo guardar el registro.']);

                return redirect()->to($redirectPath)->with('error', $err);
            }
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
        $totalIndirectaUf  = 0.0;
        $totalIndirectaClp = 0.0;
        $totalLineasUf     = 0.0;
        $totalLineasClp    = 0.0;
        foreach ($claves as $clave) {
            $meta = EquityCatalogo::seccion($clave);
            if ($meta === null) {
                continue;
            }
            $esReferencia = $grupo === 'pasivos'
                && in_array($clave, ['pasivos-indirecta', 'pasivos-lineas'], true);
            $esIndirecta = $clave === 'pasivos-indirecta';
            $esLineas    = $clave === 'pasivos-lineas';
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
            if (! $esReferencia) {
                $granTotalUf  += $totalUf;
                $granTotalClp += $totalClp;
            } elseif ($esIndirecta) {
                $totalIndirectaUf  += $totalUf;
                $totalIndirectaClp += $totalClp;
            } elseif ($esLineas) {
                $totalLineasUf  += $totalUf;
                $totalLineasClp += $totalClp;
            }
            $secciones[] = [
                'clave'        => $clave,
                'meta'         => $meta,
                'rows'         => $rows,
                'totalUf'      => $totalUf,
                'totalClp'     => $totalClp,
                'path'         => $this->pathSeccion($clave),
                'reporte'      => $this->pathReporteSeccion($clave),
                'es_referencia'=> $esReferencia,
                'es_indirecta' => $esIndirecta,
                'es_lineas'    => $esLineas,
            ];
        }

        return view('equity/reporte_grupo', [
            'title'              => $grupo === 'pasivos' ? 'Reporte · Pasivos' : 'Reporte · Activos',
            'memb'               => $memb,
            'equityNav'          => $grupo === 'pasivos' ? 'pasivos-inmuebles' : 'bienes-raices',
            'grupo'              => $grupo,
            'secciones'          => $secciones,
            'personaId'          => $personaId,
            'personaNombre'      => $personaNombre,
            'ufVigente'          => $ufVigente,
            'granTotalUf'        => $granTotalUf,
            'granTotalClp'       => $granTotalClp,
            'totalIndirectaUf'   => $totalIndirectaUf,
            'totalIndirectaClp'  => $totalIndirectaClp,
            'totalLineasUf'      => $totalLineasUf,
            'totalLineasClp'     => $totalLineasClp,
            'backUrl'            => $grupo === 'pasivos' ? '/equity/pasivos/inmuebles' : '/equity/activos/bienes-raices',
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
                . (! empty($row['tomador_nombre']) ? ' · Tomador ' . $row['tomador_nombre'] : '')
                . (! empty($row['tomador_rut']) ? ' · RUT ' . $row['tomador_rut'] : '')
                . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
                ' ·'
            ),
            'pasivos-lineas' => trim(
                (string) ($row['banco_descripcion'] ?? '')
                . (! empty($row['tipo']) ? ' · ' . $row['tipo'] : '')
                . (! empty($row['total_credito_clp']) ? ' · Total $' . number_format((float) $row['total_credito_clp'], 0, ',', '.') : '')
                . (! empty($row['vigente_clp']) ? ' · Vigente $' . number_format((float) $row['vigente_clp'], 0, ',', '.') : ''),
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
        $hasTotal = $db->fieldExists('total_credito_uf', 'equity_pasivos');
        $sum = 0.0;
        foreach ($rows as $r) {
            // Nueva grilla: total del crédito / vigente / saldo.
            if ($hasTotal && $r['total_credito_uf'] !== null && $r['total_credito_uf'] !== '') {
                $sum += (float) $r['total_credito_uf'];
                continue;
            }
            if ($hasTotal && ! empty($r['total_credito_clp']) && $ufRate > 0) {
                $sum += (float) $r['total_credito_clp'] / $ufRate;
                continue;
            }
            if ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
                continue;
            }
            if (! empty($r['saldo_clp']) && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
                continue;
            }
            // Legado: directos + indirectos.
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
            }
        }

        return $sum;
    }
}
