<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityFichaModel;
use App\Models\EquityIngresoContratoModel;
use App\Models\EquityPasivoDesarrolloModel;
use App\Models\EquityPasivoModel;
use App\Models\EquityPeriodoCierreModel;
use App\Models\EquityReporteHistorialModel;

/**
 * Cierre mensual Equity: V.B. + snapshots de reportes + desarrollo de créditos del mes.
 */
final class EquityCierre
{
    public const TIPOS_REPORTE = [
        'balance'   => 'Balance patrimonial',
        'ingresos'  => 'Ingresos',
        'egresos'   => 'Egresos',
        'pasivos'   => 'Pasivos',
        'activos'   => 'Activos',
        'situacion' => 'Situación financiera',
    ];

    public static function labelMes(int $mes): string
    {
        $nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $nombres[$mes] ?? (string) $mes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function periodoDe(string $empresaId, string $personaId, int $anio, int $mes): ?array
    {
        if (! db_connect()->tableExists('equity_periodos_cierre')) {
            return null;
        }
        $q = model(EquityPeriodoCierreModel::class)
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes);
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->groupStart()->where('persona_id', null)->orWhere('persona_id', '')->groupEnd();
        }
        $row = $q->first();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarPeriodos(string $empresaId, string $personaId): array
    {
        if (! db_connect()->tableExists('equity_periodos_cierre')) {
            return [];
        }
        $q = model(EquityPeriodoCierreModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('anio', 'DESC')
            ->orderBy('mes', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->groupStart()->where('persona_id', null)->orWhere('persona_id', '')->groupEnd();
        }

        return $q->findAll();
    }

    /**
     * Cierra el mes con V.B., congela reportes y desarrollo de créditos.
     *
     * @return array{ok: bool, error?: string, periodo?: array<string, mixed>, reportes?: int}
     */
    public static function cerrarConVb(
        string $empresaId,
        string $personaId,
        int $anio,
        int $mes,
        string $usuarioId,
        ?string $vbNota = null,
        ?string $notas = null
    ): array {
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            return ['ok' => false, 'error' => 'Periodo mes/año no válido.'];
        }
        if (! db_connect()->tableExists('equity_periodos_cierre')
            || ! db_connect()->tableExists('equity_reportes_historial')
        ) {
            return ['ok' => false, 'error' => 'Falta la migración de cierre mensual (000037).'];
        }

        $fechaCierre = EquityUf::cierreDeMes($anio, $mes);
        $ufRow = EquityUf::vigente($empresaId, $fechaCierre);
        if ($ufRow === null || empty($ufRow['valor_clp'])) {
            return ['ok' => false, 'error' => 'Debes cargar la UF de cierre de ' . self::labelMes($mes) . ' ' . $anio . ' en Configuración.'];
        }
        $ufRate = (float) $ufRow['valor_clp'];

        $existente = self::periodoDe($empresaId, $personaId, $anio, $mes);
        if (is_array($existente) && ($existente['estado'] ?? '') === 'cerrado') {
            return ['ok' => false, 'error' => 'Este periodo ya está cerrado con V.B. Revisa Historial.'];
        }

        helper('id');
        $now = date('Y-m-d H:i:s');
        $periodoId = is_array($existente) ? (string) $existente['id'] : new_id();
        $payloadPeriodo = [
            'empresa_id'     => $empresaId,
            'persona_id'     => $personaId !== '' ? $personaId : null,
            'anio'           => $anio,
            'mes'            => $mes,
            'fecha_cierre'   => $fechaCierre,
            'uf_valor_clp'   => number_format($ufRate, 2, '.', ''),
            'estado'         => 'cerrado',
            'cerrado_por_id' => $usuarioId !== '' ? $usuarioId : null,
            'cerrado_at'     => $now,
            'vb_por_id'      => $usuarioId !== '' ? $usuarioId : null,
            'vb_at'          => $now,
            'vb_nota'        => $vbNota !== null && trim($vbNota) !== '' ? trim($vbNota) : 'V.B. de cierre: información completa y valores correctos.',
            'notas'          => $notas,
        ];

        if (is_array($existente)) {
            model(EquityPeriodoCierreModel::class)->update($periodoId, $payloadPeriodo);
        } else {
            $payloadPeriodo['id'] = $periodoId;
            model(EquityPeriodoCierreModel::class)->insert($payloadPeriodo);
        }

        // Limpiar historial/desarrollo previos del periodo (re-cierre no aplica, pero por seguridad).
        model(EquityReporteHistorialModel::class)->where('periodo_id', $periodoId)->delete();
        if (db_connect()->tableExists('equity_pasivo_desarrollo')) {
            model(EquityPasivoDesarrolloModel::class)->where('periodo_id', $periodoId)->delete();
        }

        $snapshots = [
            'balance'   => self::snapshotBalance($empresaId, $personaId, $ufRate, $fechaCierre),
            'ingresos'  => self::snapshotIngresos($empresaId, $personaId, $ufRate),
            'egresos'   => self::snapshotEgresos($empresaId, $personaId, $ufRate, $anio, $mes),
            'pasivos'   => self::snapshotPasivos($empresaId, $personaId, $ufRate),
            'activos'   => self::snapshotActivos($empresaId, $personaId, $ufRate),
            'situacion' => self::snapshotSituacion($empresaId, $personaId, $ufRate),
        ];

        $n = 0;
        foreach ($snapshots as $tipo => $data) {
            model(EquityReporteHistorialModel::class)->insert([
                'id'              => new_id(),
                'periodo_id'      => $periodoId,
                'empresa_id'      => $empresaId,
                'persona_id'      => $personaId !== '' ? $personaId : null,
                'tipo'            => $tipo,
                'titulo'          => (self::TIPOS_REPORTE[$tipo] ?? $tipo) . ' · ' . self::labelMes($mes) . ' ' . $anio,
                'payload_json'    => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'uf_valor_clp'    => number_format($ufRate, 2, '.', ''),
                'generado_por_id' => $usuarioId !== '' ? $usuarioId : null,
                'created_at'      => $now,
            ]);
            $n++;
        }

        self::congelarDesarrolloPasivos($periodoId, $empresaId, $personaId, $anio, $mes, $ufRate, $fechaCierre);

        $periodo = model(EquityPeriodoCierreModel::class)->find($periodoId);

        return [
            'ok'       => true,
            'periodo'  => is_array($periodo) ? $periodo : $payloadPeriodo + ['id' => $periodoId],
            'reportes' => $n,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function reportesDePeriodo(string $periodoId): array
    {
        if (! db_connect()->tableExists('equity_reportes_historial')) {
            return [];
        }

        return model(EquityReporteHistorialModel::class)
            ->where('periodo_id', $periodoId)
            ->orderBy('tipo', 'ASC')
            ->findAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function reporte(string $id): ?array
    {
        if (! db_connect()->tableExists('equity_reportes_historial')) {
            return null;
        }
        $row = model(EquityReporteHistorialModel::class)->find($id);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function desarrolloDePeriodo(string $periodoId): array
    {
        if (! db_connect()->tableExists('equity_pasivo_desarrollo')) {
            return [];
        }

        return model(EquityPasivoDesarrolloModel::class)
            ->where('periodo_id', $periodoId)
            ->orderBy('categoria', 'ASC')
            ->orderBy('banco_descripcion', 'ASC')
            ->findAll();
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotBalance(string $empresaId, string $personaId, float $ufRate, string $fechaCierre): array
    {
        $activos = [
            'bienes_raices' => self::sumActivoUf($empresaId, $personaId, 'equity_bienes_raices', 'valor_uf', 'valor_clp', $ufRate),
            'vehiculos'     => self::sumActivoUf($empresaId, $personaId, 'equity_vehiculos', 'valor_uf', 'valor_clp', $ufRate),
            'sociedades'    => self::sumActivoUf($empresaId, $personaId, 'equity_sociedades', 'valor_uf', 'valor_clp', $ufRate),
            'otros'         => self::sumActivoUf($empresaId, $personaId, 'equity_otros_activos', 'saldo_uf', 'saldo_clp', $ufRate),
        ];
        $activosUf = array_sum($activos);
        $pasivosDirectos = [
            'hipotecarios' => self::sumPasivoCatUf($empresaId, $personaId, 'inmueble', $ufRate),
            'circulantes'  => self::sumPasivoCatUf($empresaId, $personaId, 'circulante', $ufRate),
            'otros'        => self::sumPasivoCatUf($empresaId, $personaId, 'otro', $ufRate),
        ];
        $pasivosUf = array_sum($pasivosDirectos);
        $indirectaUf = self::sumPasivoCatUf($empresaId, $personaId, 'indirecta', $ufRate);
        $lineasUf = self::sumPasivoCatUf($empresaId, $personaId, 'linea', $ufRate);

        $ingresos = self::totalesIngresos($empresaId, $personaId, $ufRate);
        $egresos  = self::totalesEgresos($empresaId, $personaId, $ufRate);

        return [
            'fecha_cierre' => $fechaCierre,
            'uf_valor_clp' => $ufRate,
            'activos_uf'   => $activos,
            'activos_clp'  => self::mapUfToClp($activos, $ufRate),
            'total_activos_uf'  => $activosUf,
            'total_activos_clp' => round($activosUf * $ufRate, 2),
            'pasivos_directos_uf'  => $pasivosDirectos,
            'pasivos_directos_clp' => self::mapUfToClp($pasivosDirectos, $ufRate),
            'total_pasivos_directos_uf'  => $pasivosUf,
            'total_pasivos_directos_clp' => round($pasivosUf * $ufRate, 2),
            'pasivos_indirecta_uf'  => $indirectaUf,
            'pasivos_indirecta_clp' => round($indirectaUf * $ufRate, 2),
            'pasivos_con_indirectos_uf'  => $pasivosUf + $indirectaUf,
            'pasivos_con_indirectos_clp' => round(($pasivosUf + $indirectaUf) * $ufRate, 2),
            'lineas_disponibles_uf'  => $lineasUf,
            'lineas_disponibles_clp' => round($lineasUf * $ufRate, 2),
            'patrimonio_uf'  => $activosUf - $pasivosUf,
            'patrimonio_clp' => round(($activosUf - $pasivosUf) * $ufRate, 2),
            'ingresos_mensuales' => $ingresos,
            'egresos_mensuales'  => $egresos,
            'flujo_mensual_clp'  => round(($ingresos['total'] ?? 0) - ($egresos['total'] ?? 0), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotIngresos(string $empresaId, string $personaId, float $ufRate): array
    {
        $totales = self::totalesIngresos($empresaId, $personaId, $ufRate);
        $cq = model(EquityIngresoContratoModel::class)->orderBy('estado', 'ASC')->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->where('empresa_id', $empresaId);
        }
        $contratos = [];
        foreach ($cq->findAll() as $c) {
            $mensual = EquityCatalogo::montoMensualEquivalente(
                isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                $c['periodicidad'] ?? 'mensual'
            );
            $contratos[] = [
                'fuente'          => $c['fuente'] ?? null,
                'subtipo'         => $c['subtipo'] ?? null,
                'contraparte'     => $c['contraparte_nombre'] ?? null,
                'estado'          => $c['estado'] ?? null,
                'periodicidad'    => $c['periodicidad'] ?? null,
                'monto_contrato'  => isset($c['monto_contrato_clp']) ? (float) $c['monto_contrato_clp'] : null,
                'equiv_mensual'   => $mensual,
                'inicio'          => $c['fecha_inicio_contrato'] ?? null,
                'vencimiento'     => $c['fecha_vencimiento'] ?? null,
                'bien_raiz_id'    => $c['bien_raiz_id'] ?? null,
                'reemplaza_contrato_id' => $c['reemplaza_contrato_id'] ?? null,
            ];
        }

        return [
            'uf_valor_clp' => $ufRate,
            'totales'      => $totales,
            'contratos'    => $contratos,
            'nota'         => 'Los contratos vigentes se repiten mes a mes hasta que cambian, bajan/suben o se marcan vencidos (quedando el hilo histórico vía reemplaza_contrato_id).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotEgresos(string $empresaId, string $personaId, float $ufRate, int $anio, int $mes): array
    {
        $totales = self::totalesEgresos($empresaId, $personaId, $ufRate);
        $detalle = [];
        foreach (EquityCatalogo::fuentesEgreso() as $clave => $meta) {
            $q = model(EquityPasivoModel::class)->where('categoria', $meta['categoria']);
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->where('empresa_id', $empresaId);
            }
            $items = [];
            foreach ($q->findAll() as $row) {
                $items[] = self::filaPasivoMes($row, $ufRate, $anio, $mes);
            }
            $detalle[] = [
                'clave'  => $clave,
                'label'  => $meta['label'],
                'total'  => $totales['porFuente'][$clave] ?? 0,
                'items'  => $items,
            ];
        }

        return [
            'uf_valor_clp' => $ufRate,
            'totales'      => $totales,
            'detalle'      => $detalle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotPasivos(string $empresaId, string $personaId, float $ufRate): array
    {
        $directos = [];
        foreach (['inmueble' => 'Hipotecarios', 'circulante' => 'Circulantes', 'otro' => 'Otros'] as $cat => $lab) {
            $uf = self::sumPasivoCatUf($empresaId, $personaId, $cat, $ufRate);
            $directos[$cat] = ['label' => $lab, 'uf' => $uf, 'clp' => round($uf * $ufRate, 2)];
        }
        $indUf = self::sumPasivoCatUf($empresaId, $personaId, 'indirecta', $ufRate);
        $linUf = self::sumPasivoCatUf($empresaId, $personaId, 'linea', $ufRate);
        $dirUf = array_sum(array_column($directos, 'uf'));

        return [
            'uf_valor_clp' => $ufRate,
            'directos'     => $directos,
            'total_directos_uf'  => $dirUf,
            'total_directos_clp' => round($dirUf * $ufRate, 2),
            'indirecta_uf'  => $indUf,
            'indirecta_clp' => round($indUf * $ufRate, 2),
            'con_indirectos_uf'  => $dirUf + $indUf,
            'con_indirectos_clp' => round(($dirUf + $indUf) * $ufRate, 2),
            'lineas_uf'  => $linUf,
            'lineas_clp' => round($linUf * $ufRate, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotActivos(string $empresaId, string $personaId, float $ufRate): array
    {
        $map = [
            'bienes_raices' => ['equity_bienes_raices', 'valor_uf', 'valor_clp', 'Bienes raíces'],
            'vehiculos'     => ['equity_vehiculos', 'valor_uf', 'valor_clp', 'Vehículos'],
            'sociedades'    => ['equity_sociedades', 'valor_uf', 'valor_clp', 'Sociedades'],
            'otros'         => ['equity_otros_activos', 'saldo_uf', 'saldo_clp', 'Otros activos'],
        ];
        $secciones = [];
        $totalUf = 0.0;
        foreach ($map as $k => [$table, $ufCol, $clpCol, $lab]) {
            $uf = self::sumActivoUf($empresaId, $personaId, $table, $ufCol, $clpCol, $ufRate);
            $totalUf += $uf;
            $secciones[$k] = ['label' => $lab, 'uf' => $uf, 'clp' => round($uf * $ufRate, 2)];
        }

        return [
            'uf_valor_clp' => $ufRate,
            'secciones'    => $secciones,
            'total_uf'     => $totalUf,
            'total_clp'    => round($totalUf * $ufRate, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshotSituacion(string $empresaId, string $personaId, float $ufRate): array
    {
        $ingresos = self::totalesIngresos($empresaId, $personaId, $ufRate);
        $egresos  = self::totalesEgresos($empresaId, $personaId, $ufRate);
        $carga = null;
        if (($ingresos['total'] ?? 0) > 0) {
            $carga = round((($egresos['total'] ?? 0) / $ingresos['total']) * 100, 1);
        }

        return [
            'uf_valor_clp'       => $ufRate,
            'ingresos_mensuales' => $ingresos,
            'egresos_mensuales'  => $egresos,
            'carga_financiera_pct' => $carga,
            'flujo_mensual_clp'  => round(($ingresos['total'] ?? 0) - ($egresos['total'] ?? 0), 2),
        ];
    }

    private static function congelarDesarrolloPasivos(
        string $periodoId,
        string $empresaId,
        string $personaId,
        int $anio,
        int $mes,
        float $ufRate,
        string $fechaCierre
    ): void {
        if (! db_connect()->tableExists('equity_pasivo_desarrollo')) {
            return;
        }
        helper('id');
        $q = model(EquityPasivoModel::class)
            ->whereIn('categoria', ['inmueble', 'circulante', 'otro']);
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->where('empresa_id', $empresaId);
        }
        $now = date('Y-m-d H:i:s');
        foreach ($q->findAll() as $row) {
            $fila = self::filaPasivoMes($row, $ufRate, $anio, $mes);
            model(EquityPasivoDesarrolloModel::class)->insert([
                'id'                => new_id(),
                'periodo_id'        => $periodoId,
                'pasivo_id'         => (string) ($row['id'] ?? ''),
                'empresa_id'        => $empresaId,
                'persona_id'        => $row['persona_id'] ?? ($personaId !== '' ? $personaId : null),
                'anio'              => $anio,
                'mes'               => $mes,
                'categoria'         => $row['categoria'] ?? null,
                'banco_descripcion' => $row['banco_descripcion'] ?? null,
                'numero_credito'    => $row['numero_credito'] ?? null,
                'moneda'            => $fila['moneda'],
                'dividendo_n'       => $fila['dividendo_n'],
                'fecha_cuota'       => $fila['fecha_cuota'] ?? $fechaCierre,
                'cuota_uf'          => self::dec($fila['cuota_uf'], 4),
                'cuota_clp'         => self::dec($fila['cuota_clp'], 2),
                'amortizacion_uf'   => self::dec($fila['amortizacion_uf'], 4),
                'amortizacion_clp'  => self::dec($fila['amortizacion_clp'], 2),
                'interes_uf'        => self::dec($fila['interes_uf'], 4),
                'interes_clp'       => self::dec($fila['interes_clp'], 2),
                'saldo_uf'          => self::dec($fila['saldo_uf'], 4),
                'saldo_clp'         => self::dec($fila['saldo_clp'], 2),
                'uf_valor_clp'      => number_format($ufRate, 2, '.', ''),
                'created_at'        => $now,
            ]);
        }
    }

    /**
     * Cuota / amortización / interés del mes para un pasivo (desarrollo del periodo).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function filaPasivoMes(array $row, float $ufRate, int $anio, int $mes): array
    {
        $moneda = EquityEgresosProyeccion::moneda($row);
        $cuotaClp = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
        $cuotaUf  = EquityEgresosProyeccion::cuotaUf($row, $ufRate);
        $amort    = EquityEgresosProyeccion::amortizacionPar($row, $ufRate);

        $interesUf  = self::num($row, 'interes_uf');
        $interesClp = self::num($row, 'interes_clp');
        if ($moneda === 'UF') {
            if ($interesUf !== null && $interesUf > 0 && $ufRate > 0 && ($interesClp === null || $interesClp <= 0)) {
                $interesClp = round($interesUf * $ufRate, 2);
            } elseif (($interesUf === null || $interesUf <= 0) && $interesClp !== null && $interesClp > 0 && $ufRate > 0) {
                $interesUf = round($interesClp / $ufRate, 4);
            }
        } else {
            if ($interesClp !== null && $interesClp > 0 && $ufRate > 0 && ($interesUf === null || $interesUf <= 0)) {
                $interesUf = round($interesClp / $ufRate, 4);
            } elseif (($interesClp === null || $interesClp <= 0) && $interesUf !== null && $interesUf > 0 && $ufRate > 0) {
                $interesClp = round($interesUf * $ufRate, 2);
            }
        }

        // Preferir la cuota del desarrollo proyectado que cae en el mes cerrado.
        $fechaCuota = null;
        $divN = isset($row['dividendo_n']) && $row['dividendo_n'] !== '' ? (int) $row['dividendo_n'] : null;
        $proy = EquityEgresosProyeccion::proyectar($row, $ufRate, 3);
        $pref = sprintf('%04d-%02d', $anio, $mes);
        foreach ($proy as $p) {
            $f = (string) ($p['fecha'] ?? '');
            if (str_starts_with($f, $pref)) {
                $fechaCuota = $f;
                $cuotaClp = $p['cuota_clp'] ?? $cuotaClp;
                $cuotaUf  = $p['cuota_uf'] ?? $cuotaUf;
                $amort['uf']  = $p['amortizacion_uf'] ?? $amort['uf'];
                $amort['clp'] = $p['amortizacion_clp'] ?? $amort['clp'];
                $divN = $p['dividendo_n'] ?? $divN;
                break;
            }
        }

        return [
            'pasivo_id'         => $row['id'] ?? null,
            'institucion'       => $row['banco_descripcion'] ?? null,
            'numero'            => $row['numero_credito'] ?? null,
            'categoria'         => $row['categoria'] ?? null,
            'moneda'            => $moneda,
            'dividendo_n'       => $divN,
            'fecha_cuota'       => $fechaCuota,
            'cuota_uf'          => $cuotaUf,
            'cuota_clp'         => $cuotaClp,
            'amortizacion_uf'   => $amort['uf'],
            'amortizacion_clp'  => $amort['clp'],
            'interes_uf'        => $interesUf,
            'interes_clp'       => $interesClp,
            'saldo_uf'          => self::num($row, 'saldo_uf'),
            'saldo_clp'         => self::num($row, 'saldo_clp'),
        ];
    }

    /**
     * @return array{porFuente: array<string, float>, total: float}
     */
    private static function totalesIngresos(string $empresaId, string $personaId, float $ufRate): array
    {
        $fuentes = EquityCatalogo::fuentesIngreso();
        $porFuente = [];
        $total = 0.0;
        $subtipoFuente = [];
        foreach ($fuentes as $clave => $meta) {
            $porFuente[$clave] = 0.0;
            foreach ($meta['campos'] as $c) {
                $subtipoFuente[$c['subtipo']] = $clave;
            }
        }
        $cq = model(EquityIngresoContratoModel::class)->where('estado', 'vigente');
        if ($personaId !== '') {
            $cq->where('persona_id', $personaId);
        } else {
            $cq->where('empresa_id', $empresaId);
        }
        $seenBien = [];
        foreach ($cq->findAll() as $ct) {
            $sub = (string) ($ct['subtipo'] ?? '');
            $f = $subtipoFuente[$sub] ?? ($ct['fuente'] ?? null);
            if ($f === null || ! isset($porFuente[$f])) {
                continue;
            }
            if ($sub === 'arriendos') {
                $bid = (string) ($ct['bien_raiz_id'] ?? '');
                if ($bid !== '') {
                    if (isset($seenBien[$bid])) {
                        continue;
                    }
                    $seenBien[$bid] = true;
                }
            }
            $porFuente[$f] += EquityCatalogo::montoMensualEquivalente(
                isset($ct['monto_contrato_clp']) ? (float) $ct['monto_contrato_clp'] : null,
                $ct['periodicidad'] ?? 'mensual'
            );
        }
        // Fallback ficha si una fuente quedó en 0.
        $ficha = null;
        if ($personaId !== '') {
            $ficha = model(EquityFichaModel::class)
                ->where('persona_id', $personaId)
                ->orderBy('fecha_corte', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->first();
        }
        foreach ($fuentes as $clave => $meta) {
            if ($porFuente[$clave] > 0) {
                $total += $porFuente[$clave];
                continue;
            }
            if (is_array($ficha)) {
                foreach ($meta['campos'] as $c) {
                    $v = $ficha[$c['key']] ?? null;
                    if ($v !== null && $v !== '') {
                        $porFuente[$clave] += (float) $v;
                    }
                }
            }
            $total += $porFuente[$clave];
        }

        return ['porFuente' => $porFuente, 'total' => $total];
    }

    /**
     * @return array{porFuente: array<string, float>, total: float}
     */
    private static function totalesEgresos(string $empresaId, string $personaId, float $ufRate): array
    {
        $porFuente = [];
        $total = 0.0;
        foreach (EquityCatalogo::fuentesEgreso() as $clave => $meta) {
            $q = model(EquityPasivoModel::class)->where('categoria', $meta['categoria']);
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->where('empresa_id', $empresaId);
            }
            $sum = 0.0;
            foreach ($q->findAll() as $row) {
                $c = EquityEgresosProyeccion::cuotaClp($row, $ufRate);
                if ($c !== null) {
                    $sum += $c;
                }
            }
            $porFuente[$clave] = $sum;
            $total += $sum;
        }

        return ['porFuente' => $porFuente, 'total' => $total];
    }

    private static function sumActivoUf(
        string $empresaId,
        string $personaId,
        string $table,
        string $ufCol,
        string $clpCol,
        float $ufRate
    ): float {
        if (! db_connect()->tableExists($table)) {
            return 0.0;
        }
        $builder = db_connect()->table($table);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->where('empresa_id', $empresaId);
        }
        if ($table === 'equity_bienes_raices' && db_connect()->fieldExists('activo', $table)) {
            $builder->where('activo', 1);
        }
        $sum = 0.0;
        foreach ($builder->get()->getResultArray() as $r) {
            if ($r[$ufCol] !== null && $r[$ufCol] !== '') {
                $sum += (float) $r[$ufCol];
            } elseif (! empty($r[$clpCol]) && $ufRate > 0) {
                $sum += (float) $r[$clpCol] / $ufRate;
            }
        }

        return $sum;
    }

    private static function sumPasivoCatUf(string $empresaId, string $personaId, string $categoria, float $ufRate): float
    {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return 0.0;
        }
        $builder = db_connect()->table('equity_pasivos')->where('categoria', $categoria);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        } else {
            $builder->where('empresa_id', $empresaId);
        }
        $sum = 0.0;
        $hasVig = db_connect()->fieldExists('vigente_uf', 'equity_pasivos');
        foreach ($builder->get()->getResultArray() as $r) {
            if ($categoria === 'indirecta' && $hasVig) {
                if ($r['vigente_uf'] !== null && $r['vigente_uf'] !== '') {
                    $sum += (float) $r['vigente_uf'];
                    continue;
                }
                if (! empty($r['vigente_clp']) && $ufRate > 0) {
                    $sum += (float) $r['vigente_clp'] / $ufRate;
                    continue;
                }
            }
            if ($categoria === 'linea') {
                if (! empty($r['total_credito_uf'])) {
                    $sum += (float) $r['total_credito_uf'];
                    continue;
                }
                if (! empty($r['total_credito_clp']) && $ufRate > 0) {
                    $sum += (float) $r['total_credito_clp'] / $ufRate;
                    continue;
                }
            }
            if ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
            } elseif (! empty($r['saldo_clp']) && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
            }
        }

        return $sum;
    }

    /**
     * @param array<string, float> $map
     * @return array<string, float>
     */
    private static function mapUfToClp(array $map, float $ufRate): array
    {
        $out = [];
        foreach ($map as $k => $uf) {
            $out[$k] = round((float) $uf * $ufRate, 2);
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private static function num(array $row, string $key): ?float
    {
        if (! isset($row[$key]) || $row[$key] === null || $row[$key] === '') {
            return null;
        }

        return (float) $row[$key];
    }

    private static function dec(?float $v, int $scale): ?string
    {
        if ($v === null) {
            return null;
        }

        return number_format($v, $scale, '.', '');
    }
}
