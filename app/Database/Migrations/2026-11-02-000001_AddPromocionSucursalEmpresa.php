<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Promoción filial (b) → empresa (a): campos de estado + auditoría.
 */
class AddPromocionSucursalEmpresa extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('empresas', [
            'promoted_from_sucursal_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'origen_empresa_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
        ]);
        $empresasTable = $this->db->prefixTable('empresas');
        $prefix        = $this->db->DBPrefix;
        $this->db->query("CREATE INDEX IF NOT EXISTS {$prefix}empresas_promoted_from_sucursal_id ON {$empresasTable} (promoted_from_sucursal_id)");
        $this->db->query("CREATE INDEX IF NOT EXISTS {$prefix}empresas_origen_empresa_id ON {$empresasTable} (origen_empresa_id)");

        $this->forge->addColumn('sucursales', [
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'ACTIVA',
                'null'       => false,
            ],
            'promoted_to_empresa_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'promoted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $sucursalesTable = $this->db->prefixTable('sucursales');
        $this->db->query("CREATE INDEX IF NOT EXISTS {$prefix}sucursales_estado ON {$sucursalesTable} (estado)");
        $this->db->query("CREATE INDEX IF NOT EXISTS {$prefix}sucursales_promoted_to_empresa_id ON {$sucursalesTable} (promoted_to_empresa_id)");

        $this->forge->addColumn('usuarios', [
            'es_admin_central' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
        ]);

        $this->forge->addField([
            'id'                 => ['type' => 'VARCHAR', 'constraint' => 36],
            'actor_id'           => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            'origen_empresa_id'  => ['type' => 'VARCHAR', 'constraint' => 36],
            'origen_sucursal_id' => ['type' => 'VARCHAR', 'constraint' => 36],
            'nueva_empresa_id'   => ['type' => 'VARCHAR', 'constraint' => 36],
            'detalle'            => ['type' => 'TEXT', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('origen_sucursal_id');
        $this->forge->addKey('nueva_empresa_id');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('actor_id', 'usuarios', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('origen_empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('origen_sucursal_id', 'sucursales', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('nueva_empresa_id', 'empresas', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('promocion_sucursal_auditoria', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('promocion_sucursal_auditoria', true);
        $this->forge->dropColumn('usuarios', 'es_admin_central');
        $this->forge->dropColumn('sucursales', ['estado', 'promoted_to_empresa_id', 'promoted_at']);
        $this->forge->dropColumn('empresas', ['promoted_from_sucursal_id', 'origen_empresa_id']);
    }
}
