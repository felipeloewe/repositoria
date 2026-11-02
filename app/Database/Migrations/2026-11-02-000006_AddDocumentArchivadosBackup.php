<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Metadatos de archivado + tabla de respaldos ZIP mensuales.
 */
class AddDocumentArchivadosBackup extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('documentos', [
            'archivado_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'cabecera_autorizantes',
            ],
            'archivado_por_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'archivado_at',
            ],
            'blob_eliminado_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'archivado_por_id',
            ],
        ]);
        $this->db->query('ALTER TABLE documentos ADD KEY documentos_archivado_at (archivado_at)');
        $this->db->query(
            'ALTER TABLE documentos ADD CONSTRAINT documentos_archivado_por_id_foreign '
            . 'FOREIGN KEY (archivado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE'
        );

        $this->forge->addField([
            'id'            => ['type' => 'VARCHAR', 'constraint' => 36],
            'empresa_id'    => ['type' => 'VARCHAR', 'constraint' => 36],
            'periodo'       => ['type' => 'VARCHAR', 'constraint' => 7], // YYYY-MM
            'ruta_zip'      => ['type' => 'VARCHAR', 'constraint' => 512],
            'tamano_bytes'  => ['type' => 'BIGINT', 'null' => true],
            'docs_count'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'creado_por_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('periodo');
        $this->forge->addForeignKey('empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('creado_por_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('documento_archivo_respaldos', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('documento_archivo_respaldos', true);
        try {
            $this->db->query('ALTER TABLE documentos DROP FOREIGN KEY documentos_archivado_por_id_foreign');
        } catch (\Throwable) {
            // ignore
        }
        foreach (['archivado_at', 'archivado_por_id', 'blob_eliminado_at'] as $col) {
            if ($this->db->fieldExists($col, 'documentos')) {
                $this->forge->dropColumn('documentos', $col);
            }
        }
    }
}
