<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Membresía: Admin (u otro) también declara patrimonio en esa empresa.
 */
class AddMembresiaEsDeclarante extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('membresias')) {
            return;
        }
        if (! $this->db->fieldExists('es_declarante', 'membresias')) {
            $this->forge->addColumn('membresias', [
                'es_declarante' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'null'       => false,
                    'after'      => 'es_socio',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('membresias') && $this->db->fieldExists('es_declarante', 'membresias')) {
            $this->forge->dropColumn('membresias', 'es_declarante');
        }
    }
}
