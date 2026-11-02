<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hilo de renovación (renueva_de_id / cadena_id) + cabeceras de documento.
 */
class AddDocumentRenewalHeaders extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('documentos', [
            'renueva_de_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'notas_ciclo',
            ],
            'cadena_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'renueva_de_id',
            ],
            'cabecera_partes' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'cadena_id',
            ],
            'cabecera_asunto' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'cabecera_partes',
            ],
            'cabecera_inicio' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'cabecera_asunto',
            ],
            'cabecera_termino' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'cabecera_inicio',
            ],
            'cabecera_autorizantes' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'cabecera_termino',
            ],
        ]);

        $this->db->query('ALTER TABLE documentos ADD KEY documentos_renueva_de_id (renueva_de_id)');
        $this->db->query('ALTER TABLE documentos ADD KEY documentos_cadena_id (cadena_id)');
        $this->db->query(
            'ALTER TABLE documentos ADD CONSTRAINT documentos_renueva_de_id_foreign '
            . 'FOREIGN KEY (renueva_de_id) REFERENCES documentos(id) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        try {
            $this->db->query('ALTER TABLE documentos DROP FOREIGN KEY documentos_renueva_de_id_foreign');
        } catch (\Throwable) {
            // ignore
        }

        foreach ([
            'renueva_de_id', 'cadena_id',
            'cabecera_partes', 'cabecera_asunto', 'cabecera_inicio',
            'cabecera_termino', 'cabecera_autorizantes',
        ] as $col) {
            if ($this->db->fieldExists($col, 'documentos')) {
                $this->forge->dropColumn('documentos', $col);
            }
        }
    }
}
