<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Homologa datos referenciales entre usuario (Admin) y equity_personas (Declarante).
 * El patrimonio opera solo con persona_id; RUT/nombre/email deben coincidir en la capa de identidad.
 */
final class IdentityHomolog
{
    public static function normalizeRut(?string $rut): string
    {
        return EquityAcl::normalizeRut($rut);
    }

    /**
     * Particiona un nombre completo en nombre + apellido (primera palabra / resto).
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitNombre(string $nombreCompleto): array
    {
        $parts    = preg_split('/\s+/', trim($nombreCompleto), 2) ?: [];
        $nombre   = trim((string) ($parts[0] ?? ''));
        $apellido = trim((string) ($parts[1] ?? ''));

        return [$nombre !== '' ? $nombre : 'Declarante', $apellido !== '' ? $apellido : null];
    }

    /**
     * Busca ficha declarante en la empresa: primero por usuario_id, luego por RUT normalizado.
     *
     * @return array<string, mixed>|null
     */
    public static function findPersonaForUsuario(
        BaseConnection $db,
        string $empresaId,
        string $usuarioId,
        ?string $rut = null
    ): ?array {
        $byUser = $db->table('equity_personas')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->get()
            ->getRowArray();
        if ($byUser !== null) {
            return $byUser;
        }

        $norm = self::normalizeRut($rut);
        if ($norm === '') {
            return null;
        }

        $rows = $db->table('equity_personas')
            ->where('empresa_id', $empresaId)
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            if (self::normalizeRut($row['rut'] ?? null) === $norm) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Escribe los mismos referenciales en usuario y persona (prioriza valores no vacíos del Admin).
     *
     * @param array{
     *   nombre?: ?string,
     *   apellido?: ?string,
     *   nombre_completo?: ?string,
     *   email?: ?string,
     *   rut?: ?string
     * } $prefs
     */
    public static function syncUsuarioPersona(
        BaseConnection $db,
        string $usuarioId,
        string $personaId,
        array $prefs = []
    ): void {
        $user    = $db->table('usuarios')->where('id', $usuarioId)->get()->getRowArray();
        $persona = $db->table('equity_personas')->where('id', $personaId)->get()->getRowArray();
        if ($user === null || $persona === null) {
            return;
        }

        $rutPref = trim((string) ($prefs['rut'] ?? ''));
        $rut = $rutPref !== ''
            ? $rutPref
            : (trim((string) ($user['rut'] ?? '')) ?: trim((string) ($persona['rut'] ?? '')));
        $rut = $rut !== '' ? $rut : null;

        $emailPref = strtolower(trim((string) ($prefs['email'] ?? '')));
        $email = $emailPref !== ''
            ? $emailPref
            : (strtolower(trim((string) ($user['email'] ?? ''))) ?: strtolower(trim((string) ($persona['email'] ?? ''))));
        $email = $email !== '' ? $email : null;

        $nombreCompleto = trim((string) ($prefs['nombre_completo'] ?? ''));
        if ($nombreCompleto === '') {
            $nombreCompleto = trim((string) ($user['nombre'] ?? ''));
        }
        if (isset($prefs['nombre']) || isset($prefs['apellido'])) {
            $pNombre   = trim((string) ($prefs['nombre'] ?? ($persona['nombre'] ?? '')));
            $pApellido = trim((string) ($prefs['apellido'] ?? ($persona['apellido'] ?? '')));
        } elseif ($nombreCompleto !== '') {
            [$pNombre, $pApellido] = self::splitNombre($nombreCompleto);
        } else {
            $pNombre   = trim((string) ($persona['nombre'] ?? '')) ?: 'Declarante';
            $pApellido = trim((string) ($persona['apellido'] ?? '')) ?: null;
            $nombreCompleto = trim($pNombre . ' ' . (string) $pApellido);
        }

        $now = date('Y-m-d H:i:s');
        $userPatch = [
            'nombre'     => $nombreCompleto !== '' ? $nombreCompleto : (string) ($user['nombre'] ?? 'Admin'),
            'updated_at' => $now,
        ];
        if ($rut !== null) {
            $userPatch['rut'] = $rut;
        }
        if ($email !== null && $email !== '') {
            $userPatch['email'] = $email;
        }
        $db->table('usuarios')->where('id', $usuarioId)->update($userPatch);

        $personaPatch = [
            'nombre'     => $pNombre !== '' ? $pNombre : (string) ($persona['nombre'] ?? 'Declarante'),
            'apellido'   => $pApellido,
            'usuario_id' => $usuarioId,
            'activo'     => 1,
            'updated_at' => $now,
        ];
        if ($rut !== null) {
            $personaPatch['rut'] = $rut;
        }
        if ($email !== null && $email !== '') {
            $personaPatch['email'] = $email;
        }
        if ($db->fieldExists('deactivated_at', 'equity_personas')) {
            $personaPatch['deactivated_at'] = null;
        }
        $db->table('equity_personas')->where('id', $personaId)->update($personaPatch);
    }
}
