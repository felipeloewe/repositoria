<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EquityBienRaizModel;
use App\Models\EquityIngresoContratoModel;

/**
 * Onboarding / vínculo Bien raíz → Contrato de arriendo (Ingresos · Inversiones).
 *
 * Fuente operativa: el contrato (vigente → vencido → nuevo).
 * Carga inicial: datos del bien (arrendatario / cánon) alimentan o actualizan el contrato.
 */
final class EquityArriendoSync
{
    /** Etiquetas que no representan un arrendatario real (ocupación / vacío). */
    private const SKIP_ARRENDATARIOS = [
        'vivienda', 'sin', 'n/a', 'na', 'no', 'ninguno', 'ninguna', 'publicado',
        'disponible', 'vacante', 'vacio', 'vacío', '-', '—',
    ];

    /**
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   linked: int,
     *   details: list<string>
     * }
     */
    public static function sincronizarDesdeBienes(
        string $empresaId,
        string $personaId,
        string $usuarioId,
        float $ufRate,
        ?string $soloBienId = null
    ): array {
        helper('id');
        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'linked' => 0, 'details' => []];
        if ($personaId === '') {
            return $out;
        }

        $db = db_connect();
        $hasLink = $db->tableExists('equity_ingresos_contratos')
            && $db->fieldExists('bien_raiz_id', 'equity_ingresos_contratos');

        $bq = model(EquityBienRaizModel::class)
            ->where('persona_id', $personaId)
            ->orderBy('direccion', 'ASC');
        if ($db->fieldExists('activo', 'equity_bienes_raices')) {
            $bq->where('activo', 1);
        }
        if ($soloBienId !== null && $soloBienId !== '') {
            $bq->where('id', $soloBienId);
        }
        $bienes = $bq->findAll();

        $contratos = model(EquityIngresoContratoModel::class)
            ->where('persona_id', $personaId)
            ->where('fuente', 'inversiones')
            ->where('subtipo', 'arriendos')
            ->findAll();

        /** @var array<string, array<string, mixed>> $byBien */
        $byBien = [];
        /** @var list<array<string, mixed>> $unlinked */
        $unlinked = [];
        foreach ($contratos as $c) {
            $bid = (string) ($c['bien_raiz_id'] ?? '');
            if ($hasLink && $bid !== '') {
                $byBien[$bid] = $c;
            } else {
                $unlinked[] = $c;
            }
        }

        $usedUnlinked = [];

        foreach ($bienes as $br) {
            $bienId = (string) ($br['id'] ?? '');
            $canon  = self::canonClp($br, $ufRate);
            $arr    = trim((string) ($br['arrendatario'] ?? ''));
            $dir    = trim((string) (($br['direccion'] ?? '') !== '' ? $br['direccion'] : ($br['descripcion'] ?? '')));

            if ($canon <= 0 || $arr === '' || self::esArrendatarioPlaceholder($arr)) {
                $out['skipped']++;
                $out['details'][] = 'Omitido (sin cánon/arrendatario): ' . ($dir !== '' ? $dir : $bienId);
                continue;
            }

            $match = $byBien[$bienId] ?? null;
            $matchedFromUnlinked = false;
            if ($match === null) {
                $idx = self::buscarContratoLibre($unlinked, $usedUnlinked, $arr, $dir);
                if ($idx !== null) {
                    $match = $unlinked[$idx];
                    $usedUnlinked[$idx] = true;
                    $matchedFromUnlinked = true;
                }
            }

            $notas = self::notaDesdeBien($br);
            $payload = [
                'persona_id'         => $personaId,
                'fuente'             => 'inversiones',
                'subtipo'            => 'arriendos',
                'contraparte_nombre' => $arr,
                'monto_contrato_clp' => number_format($canon, 2, '.', ''),
                'periodicidad'       => 'mensual',
                'notas'              => $notas,
                'updated_at'         => date('Y-m-d H:i:s'),
            ];
            if ($hasLink) {
                $payload['bien_raiz_id'] = $bienId;
            }
            if (! empty($br['canon_mensual_uf'])) {
                $payload['monto_contrato_uf'] = number_format((float) $br['canon_mensual_uf'], 4, '.', '');
            }

            if (is_array($match)) {
                // No reabrir vencidos automáticamente: solo enlazar + actualizar montos si sigue vigente.
                $estado = (string) ($match['estado'] ?? 'vigente');
                if ($estado === 'vigente') {
                    model(EquityIngresoContratoModel::class)->update($match['id'], $payload);
                    $out['updated']++;
                    $out['details'][] = 'Actualizado: ' . $arr . ' · ' . $dir;
                } elseif ($hasLink && empty($match['bien_raiz_id'])) {
                    model(EquityIngresoContratoModel::class)->update($match['id'], [
                        'bien_raiz_id' => $bienId,
                        'updated_at'   => date('Y-m-d H:i:s'),
                    ]);
                    $out['linked']++;
                    $out['details'][] = 'Enlazado (vencido, sin reabrir): ' . $arr . ' · ' . $dir;
                } else {
                    // Vencido ya vinculado: crear uno nuevo vigente desde el bien.
                    $payload['id']                   = new_id();
                    $payload['empresa_id']           = $empresaId !== '' ? $empresaId : (string) ($br['empresa_id'] ?? '');
                    $payload['estado']               = 'vigente';
                    $payload['fecha_inicio_contrato'] = date('Y-m-01');
                    $payload['creado_por_id']        = $usuarioId !== '' ? $usuarioId : null;
                    $payload['created_at']           = date('Y-m-d H:i:s');
                    model(EquityIngresoContratoModel::class)->insert($payload);
                    $out['created']++;
                    $out['details'][] = 'Nuevo (había vencido): ' . $arr . ' · ' . $dir;
                }
                if ($matchedFromUnlinked && $hasLink) {
                    $out['linked']++;
                }
            } else {
                $payload['id']                    = new_id();
                $payload['empresa_id']            = $empresaId !== '' ? $empresaId : (string) ($br['empresa_id'] ?? '');
                $payload['estado']                = 'vigente';
                $payload['fecha_inicio_contrato'] = date('Y-m-01');
                $payload['creado_por_id']         = $usuarioId !== '' ? $usuarioId : null;
                $payload['created_at']            = date('Y-m-d H:i:s');
                model(EquityIngresoContratoModel::class)->insert($payload);
                $out['created']++;
                $out['details'][] = 'Creado: ' . $arr . ' · ' . $dir;
            }
        }

        return $out;
    }

    /** Al dar de baja un bien: cierra contratos de arriendo vigentes vinculados. */
    public static function cerrarContratosDelBien(string $bienRaizId): int
    {
        $db = db_connect();
        if (! $db->tableExists('equity_ingresos_contratos')
            || ! $db->fieldExists('bien_raiz_id', 'equity_ingresos_contratos')
        ) {
            return 0;
        }
        $rows = model(EquityIngresoContratoModel::class)
            ->where('bien_raiz_id', $bienRaizId)
            ->where('subtipo', 'arriendos')
            ->where('estado', 'vigente')
            ->findAll();
        $n = 0;
        foreach ($rows as $r) {
            $patch = [
                'estado'     => 'vencido',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (empty($r['fecha_vencimiento'])) {
                $patch['fecha_vencimiento'] = date('Y-m-d');
            }
            model(EquityIngresoContratoModel::class)->update($r['id'], $patch);
            $n++;
        }

        return $n;
    }

    /** @param array<string, mixed> $br */
    public static function canonClp(array $br, float $ufRate): float
    {
        $canon = $br['canon_mensual_clp'] ?? null;
        if (($canon === null || $canon === '') && ! empty($br['canon_mensual_uf']) && $ufRate > 0) {
            return round((float) $br['canon_mensual_uf'] * $ufRate, 2);
        }
        if ($canon === null || $canon === '') {
            return 0.0;
        }

        return (float) $canon;
    }

    public static function esArrendatarioPlaceholder(string $arr): bool
    {
        $n = self::norm($arr);

        return $n === '' || in_array($n, self::SKIP_ARRENDATARIOS, true);
    }

    /** @param array<string, mixed> $br */
    public static function notaDesdeBien(array $br): string
    {
        $dir = trim((string) (($br['direccion'] ?? '') !== '' ? $br['direccion'] : ($br['descripcion'] ?? '')));
        $pago = trim((string) ($br['medio_pago'] ?? ''));
        $parts = ['Bien raíz: ' . ($dir !== '' ? $dir : (string) ($br['id'] ?? ''))];
        if ($pago !== '') {
            $parts[] = 'Pago ' . $pago;
        }

        return implode(' · ', $parts);
    }

    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /**
     * @param list<array<string, mixed>> $unlinked
     * @param array<int, true> $used
     */
    private static function buscarContratoLibre(array $unlinked, array $used, string $arr, string $dir): ?int
    {
        $nArr = self::norm($arr);
        $nDir = self::norm($dir);
        $best = null;
        $bestScore = 0;
        foreach ($unlinked as $i => $c) {
            if (isset($used[$i])) {
                continue;
            }
            if ((string) ($c['estado'] ?? '') === 'vencido') {
                // Preferir vigentes; vencidos solo si score alto de dirección.
            }
            $score = 0;
            $nNom = self::norm((string) ($c['contraparte_nombre'] ?? ''));
            if ($nNom !== '' && $nArr !== '' && ($nNom === $nArr || str_contains($nNom, $nArr) || str_contains($nArr, $nNom))) {
                $score += 3;
            }
            $nNotas = self::norm((string) ($c['notas'] ?? ''));
            if ($nDir !== '' && $nNotas !== '') {
                // Tokens de dirección (números / palabras largas)
                $tokens = array_filter(explode(' ', $nDir), static fn ($t) => strlen($t) >= 3);
                $hits = 0;
                foreach ($tokens as $t) {
                    if (str_contains($nNotas, $t)) {
                        $hits++;
                    }
                }
                if ($hits >= 2) {
                    $score += 4;
                } elseif ($hits === 1) {
                    $score += 1;
                }
            }
            if ((string) ($c['estado'] ?? '') === 'vencido') {
                $score -= 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $i;
            }
        }

        return $bestScore >= 3 ? $best : null;
    }
}
