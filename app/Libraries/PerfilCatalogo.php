<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Perfiles por empresa: 4 plantillas estereotipadas + variantes con calificativo.
 */
final class PerfilCatalogo
{
    public static function ensureForEmpresa(string $empresaId): void
    {
        $db = db_connect();
        if (! $db->tableExists('perfiles')) {
            return;
        }

        foreach (PerfilPermisos::ESTEREOTIPOS as $est) {
            $exists = $db->table('perfiles')
                ->where('empresa_id', $empresaId)
                ->where('estereotipo', $est)
                ->where('calificativo', null)
                ->where('es_plantilla', 1)
                ->countAllResults();
            if ($exists > 0) {
                continue;
            }

            self::createPerfil($empresaId, $est, null, true);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForEmpresa(string $empresaId): array
    {
        self::ensureForEmpresa($empresaId);

        return db_connect()->table('perfiles')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('estereotipo', 'ASC')
            ->orderBy('calificativo', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResultArray();
    }

    public static function find(string $perfilId): ?array
    {
        $row = db_connect()->table('perfiles')->where('id', $perfilId)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * @return array<string, bool>
     */
    public static function permisosMap(string $perfilId): array
    {
        $rows = db_connect()->table('perfil_permisos')
            ->where('perfil_id', $perfilId)
            ->get()
            ->getResultArray();

        $map = array_fill_keys(PerfilPermisos::allClaves(), false);
        foreach ($rows as $r) {
            $map[(string) $r['clave']] = (int) $r['habilitado'] === 1;
        }

        return $map;
    }

    public static function createPerfil(
        string $empresaId,
        string $estereotipo,
        ?string $calificativo = null,
        bool $esPlantilla = false,
        ?string $nombreOverride = null
    ): string {
        if (! in_array($estereotipo, PerfilPermisos::ESTEREOTIPOS, true)) {
            throw new \InvalidArgumentException('Estereotipo inválido.');
        }

        $cal = $calificativo !== null ? trim($calificativo) : null;
        if ($cal === '') {
            $cal = null;
        }

        $id   = new_id();
        $now  = date('Y-m-d H:i:s');
        $name = $nombreOverride !== null && trim($nombreOverride) !== ''
            ? trim($nombreOverride)
            : PerfilPermisos::displayName($estereotipo, $cal);

        $db = db_connect();
        $db->table('perfiles')->insert([
            'id'           => $id,
            'empresa_id'   => $empresaId,
            'estereotipo'  => $estereotipo,
            'nombre'       => $name,
            'calificativo' => $cal,
            'es_plantilla' => $esPlantilla ? 1 : 0,
            'activo'       => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $defaults = PerfilPermisos::defaultsFor($estereotipo);
        $batch    = [];
        foreach ($defaults as $clave => $hab) {
            $batch[] = [
                'id'         => new_id(),
                'perfil_id'  => $id,
                'clave'      => $clave,
                'habilitado' => $hab ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($batch !== []) {
            $db->table('perfil_permisos')->insertBatch($batch);
        }

        return $id;
    }

    /**
     * @param array<string, bool|int|string> $permisosPost clave => on/off
     */
    public static function updatePermisos(string $perfilId, array $permisosPost, ?string $calificativo = null, ?string $nombre = null): void
    {
        $perfil = self::find($perfilId);
        if ($perfil === null) {
            throw new \RuntimeException('Perfil no encontrado.');
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $cal = $calificativo !== null ? trim($calificativo) : (string) ($perfil['calificativo'] ?? '');
        if ($cal === '') {
            $cal = null;
        }

        $display = $nombre !== null && trim($nombre) !== ''
            ? trim($nombre)
            : PerfilPermisos::displayName((string) $perfil['estereotipo'], $cal);

        $db->table('perfiles')->where('id', $perfilId)->update([
            'calificativo' => $cal,
            'nombre'       => $display,
            'es_plantilla' => $cal === null && (int) ($perfil['es_plantilla'] ?? 0) === 1 ? 1 : 0,
            'updated_at'   => $now,
        ]);

        $defaults = array_fill_keys(PerfilPermisos::allClaves(), false);
        foreach ($permisosPost as $clave => $val) {
            if (! array_key_exists($clave, $defaults)) {
                continue;
            }
            $defaults[$clave] = $val === true || $val === 1 || $val === '1' || $val === 'on';
        }

        foreach ($defaults as $clave => $hab) {
            $existing = $db->table('perfil_permisos')
                ->where('perfil_id', $perfilId)
                ->where('clave', $clave)
                ->get()
                ->getRowArray();
            if ($existing) {
                $db->table('perfil_permisos')->where('id', $existing['id'])->update([
                    'habilitado' => $hab ? 1 : 0,
                    'updated_at' => $now,
                ]);
            } else {
                $db->table('perfil_permisos')->insert([
                    'id'         => new_id(),
                    'perfil_id'  => $perfilId,
                    'clave'      => $clave,
                    'habilitado' => $hab ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public static function assignToMembresia(string $membresiaId, string $perfilId): void
    {
        $perfil = self::find($perfilId);
        if ($perfil === null) {
            throw new \RuntimeException('Perfil no encontrado.');
        }

        $map = PerfilPermisos::membershipFor((string) $perfil['estereotipo']);
        $db  = db_connect();

        $memb = $db->table('membresias')->where('id', $membresiaId)->get()->getRowArray();
        if ($memb === null) {
            throw new \RuntimeException('Membresía no encontrada.');
        }
        if ((string) $memb['empresa_id'] !== (string) $perfil['empresa_id']) {
            throw new \RuntimeException('Perfil de otra empresa.');
        }

        $db->table('membresias')->where('id', $membresiaId)->update([
            'perfil_id'  => $perfilId,
            'rol'        => $map['rol'],
            'es_socio'   => (int) $map['es_socio'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
