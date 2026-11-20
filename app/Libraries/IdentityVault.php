<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaModel;
use App\Models\EquityPersonaModel;
use App\Models\MembresiaModel;
use App\Models\UsuarioModel;

/**
 * Vault lógico de identidad: PII en usuarios / empresas / equity_personas.
 * El dominio patrimonial debe operar con IDs; al dar de baja se desactiva
 * y luego se hace scrub del PII manteniendo los IDs para históricos.
 */
final class IdentityVault
{
    public static function isUsuarioActivo(?array $user): bool
    {
        if (! is_array($user)) {
            return false;
        }
        if (! (int) ($user['activo'] ?? 0)) {
            return false;
        }
        if (! empty($user['deactivated_at'])) {
            return false;
        }
        if (! empty($user['pii_scrubbed_at'])) {
            return false;
        }

        return true;
    }

    public static function labelPersona(?array $persona): string
    {
        if (! is_array($persona)) {
            return 'Persona';
        }
        if (! empty($persona['pii_scrubbed_at'])) {
            return 'Persona anonimizada · ' . substr((string) ($persona['id'] ?? ''), 0, 8);
        }
        $n = trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? ''));

        return $n !== '' ? $n : ('Persona · ' . substr((string) ($persona['id'] ?? ''), 0, 8));
    }

    public static function labelEmpresa(?array $empresa): string
    {
        if (! is_array($empresa)) {
            return 'Empresa';
        }
        if (! empty($empresa['pii_scrubbed_at'])) {
            return 'Empresa anonimizada · ' . substr((string) ($empresa['id'] ?? ''), 0, 8);
        }

        return (string) ($empresa['nombre'] ?? ('Empresa · ' . substr((string) ($empresa['id'] ?? ''), 0, 8)));
    }

    /**
     * Baja lógica de declarante: desactiva persona (+ usuario propio si aplica).
     * No borra patrimonio; no hace scrub todavía.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function deactivatePersona(string $personaId, string $actorUsuarioId): array
    {
        $persona = model(EquityPersonaModel::class)->find($personaId);
        if (! is_array($persona)) {
            return ['ok' => false, 'error' => 'Persona no encontrada.'];
        }
        if (! empty($persona['pii_scrubbed_at'])) {
            return ['ok' => false, 'error' => 'La persona ya fue anonimizada.'];
        }

        $now = date('Y-m-d H:i:s');
        model(EquityPersonaModel::class)->update($personaId, [
            'activo'         => 0,
            'deactivated_at' => $now,
        ]);

        $uid = (string) ($persona['usuario_id'] ?? '');
        if ($uid !== '' && $uid !== $actorUsuarioId) {
            self::deactivateUsuario($uid);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function deactivateUsuario(string $usuarioId): array
    {
        $user = model(UsuarioModel::class)->find($usuarioId);
        if (! is_array($user)) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }
        if (! empty($user['pii_scrubbed_at'])) {
            return ['ok' => false, 'error' => 'Usuario ya anonimizado.'];
        }

        $now = date('Y-m-d H:i:s');
        model(UsuarioModel::class)->update($usuarioId, [
            'activo'         => 0,
            'deactivated_at' => $now,
            // Invalidar sesión de verificación pendiente
            'email_verify_token' => null,
        ]);

        model(MembresiaModel::class)
            ->where('usuario_id', $usuarioId)
            ->set(['activa' => 0])
            ->update();

        return ['ok' => true];
    }

    /**
     * Scrub irreversible de PII de una persona (y su usuario propio).
     * Conserva el id para que activos/pasivos/bitácoras sigan enlazados.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function scrubPersona(string $personaId): array
    {
        $persona = model(EquityPersonaModel::class)->find($personaId);
        if (! is_array($persona)) {
            return ['ok' => false, 'error' => 'Persona no encontrada.'];
        }
        if (! empty($persona['pii_scrubbed_at'])) {
            return ['ok' => true]; // idempotente
        }
        if (empty($persona['deactivated_at']) && (int) ($persona['activo'] ?? 1) === 1) {
            return ['ok' => false, 'error' => 'Primero da de baja (desactiva) a la persona.'];
        }

        $now = date('Y-m-d H:i:s');
        $fields = PiiCatalog::identityFields()['equity_personas'] ?? [];
        $patch  = ['pii_scrubbed_at' => $now, 'activo' => 0];
        if (empty($persona['deactivated_at'])) {
            $patch['deactivated_at'] = $now;
        }
        foreach ($fields as $f) {
            if ($f === 'nombre') {
                $patch[$f] = 'ANONIMIZADO';
            } elseif ($f === 'apellido' || $f === 'apellido_materno') {
                $patch[$f] = null;
            } elseif ($f === 'email') {
                $patch[$f] = null;
            } else {
                $patch[$f] = null;
            }
        }
        // Solo columnas existentes
        $db = db_connect();
        foreach (array_keys($patch) as $col) {
            if ($col !== 'pii_scrubbed_at' && $col !== 'deactivated_at' && $col !== 'activo'
                && ! $db->fieldExists($col, 'equity_personas')) {
                unset($patch[$col]);
            }
        }
        model(EquityPersonaModel::class)->update($personaId, $patch);

        $uid = (string) ($persona['usuario_id'] ?? '');
        if ($uid !== '') {
            self::scrubUsuario($uid);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function scrubUsuario(string $usuarioId): array
    {
        $user = model(UsuarioModel::class)->find($usuarioId);
        if (! is_array($user)) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }
        if (! empty($user['pii_scrubbed_at'])) {
            return ['ok' => true];
        }
        if (empty($user['deactivated_at']) && (int) ($user['activo'] ?? 1) === 1) {
            self::deactivateUsuario($usuarioId);
        }

        $now = date('Y-m-d H:i:s');
        $short = substr($usuarioId, 0, 10);
        model(UsuarioModel::class)->update($usuarioId, [
            'email'              => 'scrubbed+' . $short . '@invalid.local',
            'nombre'             => 'ANONIMIZADO',
            'alias'              => 'scrubbed_' . $short,
            'cargo'              => null,
            'rut'                => null,
            'password_hash'      => null,
            'email_verify_token' => null,
            'email_verified_at'  => null,
            'activo'             => 0,
            'deactivated_at'     => $user['deactivated_at'] ?? $now,
            'pii_scrubbed_at'    => $now,
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function scrubEmpresa(string $empresaId): array
    {
        $empresa = model(EmpresaModel::class)->find($empresaId);
        if (! is_array($empresa)) {
            return ['ok' => false, 'error' => 'Empresa no encontrada.'];
        }
        if (! empty($empresa['pii_scrubbed_at'])) {
            return ['ok' => true];
        }

        $now = date('Y-m-d H:i:s');
        $short = substr($empresaId, 0, 10);
        model(EmpresaModel::class)->update($empresaId, [
            'nombre'          => 'EMPRESA ANONIMIZADA',
            'slug'            => 'scrubbed-' . $short,
            'rut'             => null,
            'activa'          => 0,
            'deactivated_at'  => $empresa['deactivated_at'] ?? $now,
            'pii_scrubbed_at' => $now,
        ]);

        return ['ok' => true];
    }
}
