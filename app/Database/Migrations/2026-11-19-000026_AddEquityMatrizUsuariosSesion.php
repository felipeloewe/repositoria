<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Matriz/subsidiarias Equity + verificación de usuarios + representación.
 * Additive — no wipe.
 */
class AddEquityMatrizUsuariosSesion extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('empresas')) {
            $cols = [
                'equity_es_matriz'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
                'equity_matriz_id'  => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            ];
            foreach ($cols as $name => $def) {
                if (! $this->db->fieldExists($name, 'empresas')) {
                    $this->forge->addColumn('empresas', [$name => $def]);
                }
            }
            if (! $this->db->fieldExists('equity_matriz_id', 'empresas')) {
                // already added
            }
        }

        if ($this->db->tableExists('usuarios')) {
            $cols = [
                'email_verified_at'     => ['type' => 'DATETIME', 'null' => true],
                'email_verify_token'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'email_verify_sent_at'  => ['type' => 'DATETIME', 'null' => true],
                'must_change_password'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            ];
            foreach ($cols as $name => $def) {
                if (! $this->db->fieldExists($name, 'usuarios')) {
                    $this->forge->addColumn('usuarios', [$name => $def]);
                }
            }
        }

        if ($this->db->tableExists('equity_personas') && ! $this->db->fieldExists('representado_por_usuario_id', 'equity_personas')) {
            $this->forge->addColumn('equity_personas', [
                'representado_por_usuario_id' => ['type' => 'VARCHAR', 'constraint' => 36, 'null' => true],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('empresas')) {
            foreach (['equity_es_matriz', 'equity_matriz_id'] as $col) {
                if ($this->db->fieldExists($col, 'empresas')) {
                    $this->forge->dropColumn('empresas', $col);
                }
            }
        }
        if ($this->db->tableExists('usuarios')) {
            foreach (['email_verified_at', 'email_verify_token', 'email_verify_sent_at', 'must_change_password'] as $col) {
                if ($this->db->fieldExists($col, 'usuarios')) {
                    $this->forge->dropColumn('usuarios', $col);
                }
            }
        }
        if ($this->db->tableExists('equity_personas') && $this->db->fieldExists('representado_por_usuario_id', 'equity_personas')) {
            $this->forge->dropColumn('equity_personas', 'representado_por_usuario_id');
        }
    }
}
