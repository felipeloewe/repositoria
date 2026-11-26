<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityBienRaizModel;
use App\Models\EquityFondoPensionModel;
use App\Models\EquityIngresoContratoModel;
use App\Models\EquityOtroActivoModel;
use App\Models\EquityPasivoCuotaModel;
use App\Models\EquityPasivoModel;
use App\Models\EquitySeguroModel;
use App\Models\EquitySociedadModel;
use App\Models\EquityVehiculoModel;

/**
 * Pre-chequeo de cierre mensual: ítems con fechas / actualización
 * deben estar al día respecto al periodo que se cierra.
 *
 * @phpstan-type Hallazgo array{
 *   seccion: string,
 *   severidad: 'error'|'aviso',
 *   titulo: string,
 *   detalle: string,
 *   href: string
 * }
 */
final class EquityCierreValidacion
{
    /**
     * @return array{
     *   ok: bool,
     *   bloqueantes: int,
     *   avisos: int,
     *   hallazgos: list<Hallazgo>,
     *   fecha_cierre: string,
     *   mes: int,
     *   anio: int
     * }
     */
    public static function validar(string $empresaId, string $personaId, int $anio, int $mes): array
    {
        $fechaCierre = EquityUf::cierreDeMes($anio, $mes);
        $inicioMes   = sprintf('%04d-%02d-01', $anio, $mes);
        $ym          = sprintf('%04d-%02d', $anio, $mes);
        $hallazgos   = [];

        $hallazgos = array_merge($hallazgos, self::checkUf($empresaId, $fechaCierre, $mes, $anio));
        $hallazgos = array_merge($hallazgos, self::checkPasivos($empresaId, $personaId, $ym, $inicioMes, $fechaCierre));
        $hallazgos = array_merge($hallazgos, self::checkActivosBitacora($empresaId, $personaId, $fechaCierre, $inicioMes));
        $hallazgos = array_merge($hallazgos, self::checkFondosPension($empresaId, $personaId, $ym, $fechaCierre));
        $hallazgos = array_merge($hallazgos, self::checkSeguros($empresaId, $personaId, $fechaCierre));
        $hallazgos = array_merge($hallazgos, self::checkIngresos($empresaId, $personaId, $fechaCierre));

        $bloqueantes = 0;
        $avisos      = 0;
        foreach ($hallazgos as $h) {
            if (($h['severidad'] ?? '') === 'error') {
                $bloqueantes++;
            } else {
                $avisos++;
            }
        }

        return [
            'ok'           => $bloqueantes === 0,
            'bloqueantes'  => $bloqueantes,
            'avisos'       => $avisos,
            'hallazgos'    => $hallazgos,
            'fecha_cierre' => $fechaCierre,
            'mes'          => $mes,
            'anio'         => $anio,
        ];
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkUf(string $empresaId, string $fechaCierre, int $mes, int $anio): array
    {
        $uf = EquityUf::vigente($empresaId, $fechaCierre);
        if ($uf === null || empty($uf['valor_clp'])) {
            return [[
                'seccion'   => 'Configuración · UF',
                'severidad' => 'error',
                'titulo'    => 'Falta UF de cierre',
                'detalle'   => 'No hay UF cargada para el cierre de ' . EquityCierre::labelMes($mes) . ' ' . $anio . ' (' . $fechaCierre . ').',
                'href'      => '/equity/configuracion',
            ]];
        }
        // UF debe ser exactamente del día de cierre del mes (o igual fecha_cierre).
        if (($uf['fecha_cierre'] ?? '') !== $fechaCierre) {
            return [[
                'seccion'   => 'Configuración · UF',
                'severidad' => 'aviso',
                'titulo'    => 'UF vigente no es del día de cierre',
                'detalle'   => 'La UF usada es del ' . ($uf['fecha_cierre'] ?? '—') . '; el cierre contable es ' . $fechaCierre . '. Revisa si corresponde cargar la UF de este mes.',
                'href'      => '/equity/configuracion',
            ]];
        }

        return [];
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkPasivos(
        string $empresaId,
        string $personaId,
        string $ym,
        string $inicioMes,
        string $fechaCierre
    ): array {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return [];
        }
        $out = [];
        $q = model(EquityPasivoModel::class)
            ->whereIn('categoria', ['inmueble', 'circulante', 'otro']);
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->where('empresa_id', $empresaId);
        }
        $rows = $q->findAll();
        $mapaCat = [
            'inmueble'   => ['label' => 'Hipotecarios', 'path' => '/equity/pasivos/inmuebles'],
            'circulante' => ['label' => 'Circulantes', 'path' => '/equity/pasivos/circulantes'],
            'otro'       => ['label' => 'Otros pasivos', 'path' => '/equity/pasivos/otros'],
        ];

        foreach ($rows as $row) {
            $cat = (string) ($row['categoria'] ?? 'otro');
            $meta = $mapaCat[$cat] ?? ['label' => 'Pasivos', 'path' => '/equity/pasivos/inmuebles'];
            $banco = trim((string) ($row['banco_descripcion'] ?? 'Pasivo'));
            $nro = trim((string) ($row['numero_credito'] ?? ''));
            $label = $banco . ($nro !== '' ? ' · ' . $nro : '');
            $href = $meta['path'] . '/' . rawurlencode((string) $row['id']) . '/editar';

            $fechaReg = trim((string) ($row['fecha_registro'] ?? ''));
            if ($fechaReg === '') {
                $out[] = [
                    'seccion'   => 'Pasivos · ' . $meta['label'],
                    'severidad' => 'error',
                    'titulo'    => 'Sin fecha de registro (dividendo)',
                    'detalle'   => $label . ': falta la fecha de registro del dividendo del mes de cierre.',
                    'href'      => $href,
                ];
            } elseif (! str_starts_with($fechaReg, $ym)) {
                $out[] = [
                    'seccion'   => 'Pasivos · ' . $meta['label'],
                    'severidad' => 'error',
                    'titulo'    => 'Fecha de registro fuera del mes de cierre',
                    'detalle'   => $label . ': registro ' . $fechaReg . ' (se espera ' . $ym . '-…). Actualiza el dividendo del periodo.',
                    'href'      => $href,
                ];
            }

            // Si hay desarrollo editable, debe existir al menos una cuota en el mes.
            if (db_connect()->tableExists('equity_pasivo_cuotas')) {
                $cuotasMes = model(EquityPasivoCuotaModel::class)
                    ->where('pasivo_id', $row['id'])
                    ->where('fecha >=', $inicioMes)
                    ->where('fecha <=', $fechaCierre)
                    ->countAllResults();
                $totalCuotas = model(EquityPasivoCuotaModel::class)
                    ->where('pasivo_id', $row['id'])
                    ->countAllResults();
                if ($totalCuotas > 0 && $cuotasMes === 0) {
                    $out[] = [
                        'seccion'   => 'Pasivos · Desarrollo',
                        'severidad' => 'aviso',
                        'titulo'    => 'Desarrollo sin cuota del mes',
                        'detalle'   => $label . ': hay desarrollo editable pero ninguna cuota entre ' . $inicioMes . ' y ' . $fechaCierre . '.',
                        'href'      => $meta['path'] . '/' . rawurlencode((string) $row['id']) . '/desarrollo',
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkActivosBitacora(
        string $empresaId,
        string $personaId,
        string $fechaCierre,
        string $inicioMes
    ): array {
        $out = [];
        $specs = [
            [
                'tipo'  => EquityActivoBitacora::TIPO_BIENES,
                'clave' => 'bienes-raices',
                'label' => 'Bienes raíces',
                'path'  => '/equity/activos/bienes-raices',
                'model' => EquityBienRaizModel::class,
                'name'  => static fn (array $r): string => trim((string) ($r['direccion'] ?? $r['descripcion'] ?? 'Bien')),
                'activo'=> true,
            ],
            [
                'tipo'  => EquityActivoBitacora::TIPO_VEHICULOS,
                'clave' => 'vehiculos',
                'label' => 'Vehículos',
                'path'  => '/equity/activos/vehiculos',
                'model' => EquityVehiculoModel::class,
                'name'  => static fn (array $r): string => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '') . ' ' . ($r['patente'] ?? '')) ?: 'Vehículo',
                'activo'=> false,
            ],
            [
                'tipo'  => EquityActivoBitacora::TIPO_SOCIEDADES,
                'clave' => 'sociedades',
                'label' => 'Sociedades',
                'path'  => '/equity/activos/sociedades',
                'model' => EquitySociedadModel::class,
                'name'  => static fn (array $r): string => (string) ($r['nombre_sociedad'] ?? 'Sociedad'),
                'activo'=> false,
            ],
            [
                'tipo'  => EquityActivoBitacora::TIPO_OTROS,
                'clave' => 'otros-activos',
                'label' => 'Otros activos',
                'path'  => '/equity/activos/otros',
                'model' => EquityOtroActivoModel::class,
                'name'  => static fn (array $r): string => trim((string) ($r['tipo'] ?? '') . ' ' . (string) ($r['institucion'] ?? '')) ?: 'Otro activo',
                'activo'=> false,
            ],
        ];

        $tieneBitacora = db_connect()->tableExists('equity_activo_valores');

        foreach ($specs as $spec) {
            /** @var class-string $modelClass */
            $modelClass = $spec['model'];
            $table = model($modelClass)->getTable();
            if (! db_connect()->tableExists($table)) {
                continue;
            }
            $q = model($modelClass);
            if ($personaId !== '') {
                $q->where('persona_id', $personaId);
            } else {
                $q->where('empresa_id', $empresaId);
            }
            if ($spec['activo'] && db_connect()->fieldExists('activo', $table)) {
                $q->where('activo', 1);
            }
            $rows = $q->findAll();
            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $nombre = ($spec['name'])($row);
                $href = $spec['path'] . '/' . rawurlencode($id) . '/editar';

                if ($tieneBitacora) {
                    $corte = EquityActivoBitacora::valorAlCierre(
                        $spec['tipo'],
                        $id,
                        EquityActivoBitacora::CAMPO_VALOR_COMERCIAL,
                        $fechaCierre
                    );
                    if ($corte === null) {
                        $out[] = [
                            'seccion'   => 'Activos · ' . $spec['label'],
                            'severidad' => 'aviso',
                            'titulo'    => 'Sin corte de valor para el mes',
                            'detalle'   => $nombre . ': no hay bitácora de valor comercial ≤ ' . $fechaCierre . '. Actualiza el valor y guarda para generar el corte.',
                            'href'      => $href,
                        ];
                        continue;
                    }
                    if (($corte['fecha_cierre'] ?? '') !== $fechaCierre) {
                        // Hay valor anterior, pero no actualizado este mes.
                        $updated = (string) ($row['updated_at'] ?? '');
                        $touchOk = $updated !== '' && $updated >= $inicioMes;
                        if (! $touchOk) {
                            $out[] = [
                                'seccion'   => 'Activos · ' . $spec['label'],
                                'severidad' => 'aviso',
                                'titulo'    => 'Valor no actualizado en el mes de cierre',
                                'detalle'   => $nombre . ': último corte ' . ($corte['fecha_cierre'] ?? '—') . ' (cierre ' . $fechaCierre . '). Confirma o actualiza el valor comercial.',
                                'href'      => $href,
                            ];
                        }
                    }
                } else {
                    $updated = (string) ($row['updated_at'] ?? '');
                    if ($updated === '' || $updated < $inicioMes) {
                        $out[] = [
                            'seccion'   => 'Activos · ' . $spec['label'],
                            'severidad' => 'aviso',
                            'titulo'    => 'Registro sin actualización en el mes',
                            'detalle'   => $nombre . ': última modificación ' . ($updated !== '' ? $updated : 'desconocida') . '.',
                            'href'      => $href,
                        ];
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkFondosPension(
        string $empresaId,
        string $personaId,
        string $ym,
        string $fechaCierre
    ): array {
        if (! db_connect()->tableExists('equity_fondos_pension')) {
            return [];
        }
        $out = [];
        $q = model(EquityFondoPensionModel::class);
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->where('empresa_id', $empresaId);
        }
        foreach ($q->findAll() as $row) {
            $afp = (string) ($row['afp'] ?? 'Fondo');
            $fondo = (string) ($row['tipo_fondo'] ?? '');
            $label = $afp . ($fondo !== '' ? ' · Fondo ' . $fondo : '');
            $href = '/equity/activos/fondos-pension/' . rawurlencode((string) $row['id']) . '/editar';
            $periodo = trim((string) ($row['periodo_cierre'] ?? ''));
            if ($periodo === '') {
                $out[] = [
                    'seccion'   => 'Activos · Fondos de pensión',
                    'severidad' => 'error',
                    'titulo'    => 'Sin periodo de cierre en cartola',
                    'detalle'   => $label . ': falta el periodo de cierre del estado de cuenta.',
                    'href'      => $href,
                ];
            } elseif (! str_starts_with($periodo, $ym) && $periodo !== $fechaCierre) {
                $out[] = [
                    'seccion'   => 'Activos · Fondos de pensión',
                    'severidad' => 'error',
                    'titulo'    => 'Cartola de otro periodo',
                    'detalle'   => $label . ': periodo ' . $periodo . ' (cierre esperado ' . $fechaCierre . ').',
                    'href'      => $href,
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkSeguros(string $empresaId, string $personaId, string $fechaCierre): array
    {
        if (! db_connect()->tableExists('equity_seguros')) {
            return [];
        }
        $out = [];
        $q = model(EquitySeguroModel::class)->where('estado', 'vigente');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->where('empresa_id', $empresaId);
        }
        foreach ($q->findAll() as $row) {
            $hasta = trim((string) ($row['vigencia_hasta'] ?? ''));
            if ($hasta === '') {
                $out[] = [
                    'seccion'   => 'Seguros · Pólizas',
                    'severidad' => 'aviso',
                    'titulo'    => 'Póliza vigente sin fecha de término',
                    'detalle'   => trim((string) ($row['compania'] ?? 'Póliza') . ' ' . (string) ($row['numero_poliza'] ?? '')) . ': completa vigencia hasta o revisa el estado.',
                    'href'      => '/equity/seguros/' . rawurlencode((string) $row['id']) . '/editar',
                ];
                continue;
            }
            if ($hasta < $fechaCierre) {
                $out[] = [
                    'seccion'   => 'Seguros · Pólizas',
                    'severidad' => 'error',
                    'titulo'    => 'Póliza vencida aún marcada vigente',
                    'detalle'   => trim((string) ($row['compania'] ?? 'Póliza') . ' · ' . (string) ($row['numero_poliza'] ?? ''))
                        . ': vigencia hasta ' . $hasta . ' (cierre ' . $fechaCierre . '). Actualiza estado o renovación.',
                    'href'      => '/equity/seguros/' . rawurlencode((string) $row['id']) . '/editar',
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<Hallazgo>
     */
    private static function checkIngresos(string $empresaId, string $personaId, string $fechaCierre): array
    {
        if (! db_connect()->tableExists('equity_ingresos_contratos')) {
            return [];
        }
        $out = [];
        $q = model(EquityIngresoContratoModel::class)->where('estado', 'vigente');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        } else {
            $q->where('empresa_id', $empresaId);
        }
        foreach ($q->findAll() as $row) {
            $venc = trim((string) ($row['fecha_vencimiento'] ?? ''));
            if ($venc === '' || $venc >= $fechaCierre) {
                continue;
            }
            $nombre = trim((string) ($row['contraparte_nombre'] ?? $row['subtipo'] ?? 'Contrato'));
            $fuente = (string) ($row['fuente'] ?? 'inversiones');
            $out[] = [
                'seccion'   => 'Ingresos · ' . ucfirst($fuente),
                'severidad' => 'error',
                'titulo'    => 'Contrato vigente con fecha de vencimiento pasada',
                'detalle'   => $nombre . ': venció el ' . $venc . ' (cierre ' . $fechaCierre . '). Márcalo vencido o crea sucesor.',
                'href'      => '/equity/ingresos/' . rawurlencode($fuente),
            ];
        }

        return $out;
    }
}
