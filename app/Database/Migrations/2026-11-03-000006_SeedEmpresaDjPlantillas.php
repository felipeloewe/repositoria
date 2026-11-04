<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Libraries\RecursoSistema;
use CodeIgniter\Database\Migration;

/**
 * Seed plantillas genéricas empresa DJ (Circular 57 + Art. 85).
 * Additive — no schema changes; idempotent via RecursoSistema::ensure*.
 */
class SeedEmpresaDjPlantillas extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('empresa_form_plantillas')
            || ! $this->db->tableExists('empresa_form_campos')
        ) {
            return;
        }

        RecursoSistema::ensureDjBeneficiariosFinalesPlantilla($this->db);
        RecursoSistema::ensureDjArt85LeyGeneralBancosPlantilla($this->db);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('empresa_form_plantillas')) {
            return;
        }

        foreach ([
            RecursoSistema::CLAVE_DJ_BENEFICIARIOS_FINALES,
            RecursoSistema::CLAVE_DJ_ART_85_LEY_GENERAL_BANCOS,
        ] as $clave) {
            $row = $this->db->table('empresa_form_plantillas')
                ->where('clave_sistema', $clave)
                ->get()
                ->getRowArray();
            if (! is_array($row)) {
                continue;
            }
            if ($this->db->tableExists('empresa_form_campos')) {
                $this->db->table('empresa_form_campos')->where('plantilla_id', $row['id'])->delete();
            }
            $this->db->table('empresa_form_plantillas')->where('id', $row['id'])->delete();
        }
    }
}
