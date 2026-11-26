<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Número de cuenta corriente completo (referencial entre bancos), no solo últimos dígitos.
 * Renombra/amplía la columna en cuentas. Additive — no wipe.
 */
class ExpandNumeroCuentaBancarios extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('equity_persona_cuentas')) {
            return;
        }

        if (! $this->db->fieldExists('numero_cuenta', 'equity_persona_cuentas')) {
            if ($this->db->fieldExists('ultimos_digitos', 'equity_persona_cuentas')) {
                // Ampliar y renombrar: el valor previo (últimos dígitos) se conserva.
                $this->db->query(
                    'ALTER TABLE equity_persona_cuentas
                     CHANGE ultimos_digitos numero_cuenta VARCHAR(64) NULL'
                );
            } else {
                $this->forge->addColumn('equity_persona_cuentas', [
                    'numero_cuenta' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 64,
                        'null'       => true,
                    ],
                ]);
            }
        } elseif ($this->db->fieldExists('ultimos_digitos', 'equity_persona_cuentas')) {
            // Ya existe numero_cuenta: copiar residual y dejar de depender de ultimos_digitos.
            $this->db->query(
                'UPDATE equity_persona_cuentas
                 SET numero_cuenta = ultimos_digitos
                 WHERE (numero_cuenta IS NULL OR numero_cuenta = "")
                   AND ultimos_digitos IS NOT NULL AND ultimos_digitos != ""'
            );
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('equity_persona_cuentas')) {
            return;
        }
        if ($this->db->fieldExists('numero_cuenta', 'equity_persona_cuentas')
            && ! $this->db->fieldExists('ultimos_digitos', 'equity_persona_cuentas')) {
            $this->db->query(
                'ALTER TABLE equity_persona_cuentas
                 CHANGE numero_cuenta ultimos_digitos VARCHAR(8) NULL'
            );
        }
    }
}
