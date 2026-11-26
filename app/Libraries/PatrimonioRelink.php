<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Reasigna / alinea relaciones patrimoniales al Declarante (persona_id).
 * El patrimonio no guarda RUT: solo IDs; empresa_id debe coincidir con la ficha.
 */
final class PatrimonioRelink
{
    /** Tablas Equity con persona_id (+ empresa_id cuando existe). */
    private const TABLES = [
        'equity_pasivos',
        'equity_bienes_raices',
        'equity_vehiculos',
        'equity_sociedades',
        'equity_otros_activos',
        'equity_fondos_pension',
        'equity_seguros',
        'equity_ingresos_contratos',
        'equity_respaldos',
        'equity_fichas',
        'equity_activo_valores',
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Alinea empresa_id de todo el patrimonio de una persona a persona.empresa_id.
     *
     * @return array<string, int> filas actualizadas por tabla
     */
    public function alignEmpresaToPersona(string $personaId): array
    {
        $persona = $this->db->table('equity_personas')->where('id', $personaId)->get()->getRowArray();
        if ($persona === null) {
            return [];
        }
        $empresaId = (string) $persona['empresa_id'];
        $now       = date('Y-m-d H:i:s');
        $out       = [];

        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }
            if (! $this->db->fieldExists('persona_id', $table) || ! $this->db->fieldExists('empresa_id', $table)) {
                continue;
            }
            $builder = $this->db->table($table)
                ->where('persona_id', $personaId)
                ->where('empresa_id !=', $empresaId);
            $count = $builder->countAllResults(false);
            if ($count < 1) {
                $out[$table] = 0;
                continue;
            }
            $patch = ['empresa_id' => $empresaId];
            if ($this->db->fieldExists('updated_at', $table)) {
                $patch['updated_at'] = $now;
            }
            $this->db->table($table)
                ->where('persona_id', $personaId)
                ->where('empresa_id !=', $empresaId)
                ->update($patch);
            $out[$table] = $count;
        }

        return $out;
    }

    /**
     * Mueve patrimonio de una persona origen a la persona declarante destino (mismo o distinto ID).
     * También alinea empresa_id al destino.
     *
     * @return array<string, int>
     */
    public function reassignPersona(string $fromPersonaId, string $toPersonaId): array
    {
        if ($fromPersonaId === $toPersonaId) {
            return $this->alignEmpresaToPersona($toPersonaId);
        }

        $destino = $this->db->table('equity_personas')->where('id', $toPersonaId)->get()->getRowArray();
        if ($destino === null) {
            return [];
        }
        $empresaId = (string) $destino['empresa_id'];
        $now       = date('Y-m-d H:i:s');
        $out       = [];

        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists('persona_id', $table)) {
                continue;
            }
            $count = $this->db->table($table)->where('persona_id', $fromPersonaId)->countAllResults();
            if ($count < 1) {
                $out[$table] = 0;
                continue;
            }
            $patch = ['persona_id' => $toPersonaId];
            if ($this->db->fieldExists('empresa_id', $table)) {
                $patch['empresa_id'] = $empresaId;
            }
            if ($this->db->fieldExists('updated_at', $table)) {
                $patch['updated_at'] = $now;
            }
            $this->db->table($table)->where('persona_id', $fromPersonaId)->update($patch);
            $out[$table] = $count;
        }

        return $out;
    }

    /**
     * Corrige desalineaciones empresa↔persona en todas las fichas activas.
     *
     * @return array{personas: int, updates: array<string, int>}
     */
    public function alignAllMismatches(): array
    {
        $personas = $this->db->table('equity_personas')->select('id')->where('activo', 1)->get()->getResultArray();
        $totals   = [];
        $touched  = 0;
        foreach ($personas as $p) {
            $upd = $this->alignEmpresaToPersona((string) $p['id']);
            $sum = array_sum($upd);
            if ($sum > 0) {
                $touched++;
                foreach ($upd as $t => $n) {
                    $totals[$t] = ($totals[$t] ?? 0) + $n;
                }
            }
        }

        return ['personas' => $touched, 'updates' => $totals];
    }
}
