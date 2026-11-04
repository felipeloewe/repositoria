<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Secciones de formulario (agrupación UI alineada al PDF) + backfill de las 3 plantillas genéricas.
 * Additive — no wipe.
 */
class AddFormCampoSeccion extends Migration
{
    public function up(): void
    {
        $this->addSeccionColumn('socio_form_campos');
        $this->addSeccionColumn('empresa_form_campos');

        $this->backfillFromSeed(
            'socio_form_plantillas',
            'socio_form_campos',
            'estado_situacion_solicitud_productos',
            'estado_situacion_solicitud_productos_campos.php'
        );
        $this->backfillFromSeed(
            'empresa_form_plantillas',
            'empresa_form_campos',
            'dj_beneficiarios_finales_circular_57',
            'dj_beneficiarios_finales_circular_57_campos.php'
        );
        $this->backfillFromSeed(
            'empresa_form_plantillas',
            'empresa_form_campos',
            'dj_art_85_ley_general_bancos',
            'dj_art_85_ley_general_bancos_campos.php'
        );
    }

    public function down(): void
    {
        $this->dropSeccionColumn('socio_form_campos');
        $this->dropSeccionColumn('empresa_form_campos');
    }

    private function addSeccionColumn(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        $fields = $this->db->getFieldNames($table);
        if (in_array('seccion', $fields, true)) {
            return;
        }
        $this->forge->addColumn($table, [
            'seccion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'orden',
            ],
        ]);
    }

    private function dropSeccionColumn(string $table): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        $fields = $this->db->getFieldNames($table);
        if (! in_array('seccion', $fields, true)) {
            return;
        }
        $this->forge->dropColumn($table, 'seccion');
    }

    private function backfillFromSeed(
        string $plantillaTable,
        string $campoTable,
        string $claveSistema,
        string $camposFile
    ): void {
        if (! $this->db->tableExists($plantillaTable) || ! $this->db->tableExists($campoTable)) {
            return;
        }

        $plantilla = $this->db->table($plantillaTable)
            ->where('clave_sistema', $claveSistema)
            ->get()
            ->getRowArray();
        if (! is_array($plantilla)) {
            return;
        }

        $path = APPPATH . 'Database/Seeds/data/' . $camposFile;
        if (! is_file($path)) {
            return;
        }

        /** @var list<array{clave: string, seccion?: string}> $campos */
        $campos = require $path;
        $plantillaId = (string) $plantilla['id'];

        foreach ($campos as $c) {
            $clave   = (string) ($c['clave'] ?? '');
            $seccion = isset($c['seccion']) ? trim((string) $c['seccion']) : '';
            if ($clave === '' || $seccion === '') {
                continue;
            }
            $this->db->table($campoTable)
                ->where('plantilla_id', $plantillaId)
                ->where('clave', $clave)
                ->update(['seccion' => $seccion]);
        }
    }
}
