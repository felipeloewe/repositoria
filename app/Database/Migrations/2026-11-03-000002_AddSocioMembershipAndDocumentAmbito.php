<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Membresía es_socio (Menú Socios) + documentos.ambito (general|socio).
 * Additive — no wipe.
 */
class AddSocioMembershipAndDocumentAmbito extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('es_socio', 'membresias')) {
            $this->forge->addColumn('membresias', [
                'es_socio' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'activa',
                ],
            ]);
            $this->db->query('ALTER TABLE membresias ADD KEY membresias_es_socio (es_socio)');
        }

        if (! $this->db->fieldExists('ambito', 'documentos')) {
            $this->forge->addColumn('documentos', [
                'ambito' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'default'    => 'general',
                    'null'       => false,
                    'after'      => 'seccion_id',
                ],
            ]);
            $this->db->query('ALTER TABLE documentos ADD KEY documentos_ambito (ambito)');
            // Existing rows keep DEFAULT general.
            $this->db->table('documentos')
                ->where('ambito', null)
                ->orWhere('ambito', '')
                ->update(['ambito' => 'general']);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('ambito', 'documentos')) {
            try {
                $this->db->query('ALTER TABLE documentos DROP KEY documentos_ambito');
            } catch (\Throwable) {
                // ignore
            }
            $this->forge->dropColumn('documentos', 'ambito');
        }

        if ($this->db->fieldExists('es_socio', 'membresias')) {
            try {
                $this->db->query('ALTER TABLE membresias DROP KEY membresias_es_socio');
            } catch (\Throwable) {
                // ignore
            }
            $this->forge->dropColumn('membresias', 'es_socio');
        }
    }
}
