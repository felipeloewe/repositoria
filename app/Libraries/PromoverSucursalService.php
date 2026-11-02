<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Admin-central job: promote Sucursal/Filial (mode b) into its own Empresa tenant (mode a).
 *
 * Remaps filial-scoped depto sections/docs/ACL/memberships + moves blobs under STORAGE_ROOT.
 * Hierarchy stays in MySQL; storage is flat by IDs.
 */
final class PromoverSucursalService
{
    public const ESTADO_ACTIVA    = 'ACTIVA';
    public const ESTADO_PROMOVIDA = 'PROMOVIDA';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array{nombre?: string, slug?: string, actor_id?: ?string} $options
     *
     * @return array{
     *   nueva_empresa_id: string,
     *   nueva_departamento_id: string,
     *   nueva_sucursal_id: string,
     *   origen_empresa_id: string,
     *   origen_sucursal_id: string,
     *   documentos_movidos: int,
     *   secciones_movidas: int,
     *   membresias_creadas: int,
     *   audit_id: string
     * }
     */
    public function promover(string $sucursalId, array $options = []): array
    {
        helper('id');

        $sucursalId = trim($sucursalId);
        if ($sucursalId === '') {
            throw new RuntimeException('sucursalId requerido.');
        }

        $actorId = isset($options['actor_id']) && is_string($options['actor_id']) && $options['actor_id'] !== ''
            ? $options['actor_id']
            : null;

        $this->db->transBegin();

        try {
            $origen = $this->db->table('sucursales')->where('id', $sucursalId)->get()->getRowArray();
            if ($origen === null) {
                throw new RuntimeException('Sucursal no encontrada.');
            }

            $estado = (string) ($origen['estado'] ?? self::ESTADO_ACTIVA);
            if ($estado === self::ESTADO_PROMOVIDA || ! empty($origen['promoted_to_empresa_id'])) {
                throw new RuntimeException('La sucursal ya fue promovida.');
            }
            if (! (int) ($origen['activa'] ?? 0)) {
                throw new RuntimeException('La sucursal no está activa.');
            }

            $origenEmpresaId = (string) $origen['empresa_id'];
            $origenDeptoId   = (string) $origen['departamento_id'];

            $deptoOrigen = $this->db->table('departamentos')->where('id', $origenDeptoId)->get()->getRowArray();
            $deptoNombre = is_array($deptoOrigen) ? (string) $deptoOrigen['nombre'] : 'General';

            $nombre = trim((string) ($options['nombre'] ?? $origen['nombre']));
            if ($nombre === '') {
                $nombre = 'Empresa ' . $sucursalId;
            }

            $slug = $this->uniqueSlug(trim((string) ($options['slug'] ?? '')) ?: self::slugify($nombre));

            $now               = date('Y-m-d H:i:s');
            $nuevaEmpresaId    = new_id();
            $nuevaDeptoId      = new_id();
            $nuevaSucursalId   = new_id();

            $this->db->table('empresas')->insert([
                'id'                        => $nuevaEmpresaId,
                'nombre'                    => $nombre,
                'slug'                      => $slug,
                'rut'                       => null,
                'activa'                    => 1,
                'promoted_from_sucursal_id' => $sucursalId,
                'origen_empresa_id'         => $origenEmpresaId,
                'created_at'                => $now,
                'updated_at'                => $now,
            ]);

            $this->db->table('departamentos')->insert([
                'id'         => $nuevaDeptoId,
                'empresa_id' => $nuevaEmpresaId,
                'nombre'     => $deptoNombre,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->db->table('sucursales')->insert([
                'id'              => $nuevaSucursalId,
                'empresa_id'      => $nuevaEmpresaId,
                'departamento_id' => $nuevaDeptoId,
                'nombre'          => 'Casa Matriz',
                'codigo'          => $origen['codigo'] ?? 'CM',
                'activa'          => 1,
                'estado'          => self::ESTADO_ACTIVA,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            // Optional: copy tags scoped to the origin departamento (MVP: none — tags stay on parent)

            $secciones = $this->db->table('documento_secciones')
                ->where('sucursal_id', $sucursalId)
                ->get()
                ->getResultArray();

            $seccionIds = [];
            foreach ($secciones as $sec) {
                $seccionIds[] = $sec['id'];
                $this->db->table('documento_secciones')->where('id', $sec['id'])->update([
                    'empresa_id'      => $nuevaEmpresaId,
                    'departamento_id' => $nuevaDeptoId,
                    'sucursal_id'     => $nuevaSucursalId,
                    'updated_at'      => $now,
                ]);
            }

            $documentos = $this->db->table('documentos')
                ->where('sucursal_id', $sucursalId)
                ->get()
                ->getResultArray();

            $docIds = [];
            foreach ($documentos as $doc) {
                $docIds[] = $doc['id'];
                $ruta     = (string) ($doc['ruta_relativa'] ?? '');
                $nuevaRuta = DocumentStorage::relocateToEmpresa($ruta, $nuevaEmpresaId, (string) $doc['id']);

                $this->db->table('documentos')->where('id', $doc['id'])->update([
                    'empresa_id'      => $nuevaEmpresaId,
                    'departamento_id' => $nuevaDeptoId,
                    'sucursal_id'     => $nuevaSucursalId,
                    'ruta_relativa'   => $nuevaRuta,
                    'updated_at'      => $now,
                ]);
            }

            if ($docIds !== []) {
                $this->db->table('documento_autorizaciones')
                    ->whereIn('documento_id', $docIds)
                    ->update(['empresa_id' => $nuevaEmpresaId]);

                // Detach from parent canal packages (MVP: leave packages on parent empresa)
                if ($this->db->tableExists('canal_paquete_documentos')) {
                    $this->db->table('canal_paquete_documentos')
                        ->whereIn('documento_id', $docIds)
                        ->delete();
                }
            }

            // Fiscalización requests scoped to this filial
            $fiscalMoved = 0;
            if ($this->db->tableExists('fiscalizacion_solicitudes')) {
                $this->db->table('fiscalizacion_solicitudes')
                    ->where('sucursal_id', $sucursalId)
                    ->update([
                        'empresa_id'      => $nuevaEmpresaId,
                        'departamento_id' => $nuevaDeptoId,
                        'sucursal_id'     => $nuevaSucursalId,
                        'updated_at'      => $now,
                    ]);
                $fiscalMoved = $this->db->affectedRows();
            }

            $membresiasOrigen = $this->db->table('membresias')
                ->where('sucursal_id', $sucursalId)
                ->where('activa', 1)
                ->get()
                ->getResultArray();

            $membresiasCreadas = 0;
            foreach ($membresiasOrigen as $memb) {
                $usuarioId = (string) $memb['usuario_id'];
                $rolOrigen = (string) $memb['rol'];
                $rolNuevo  = $rolOrigen === Roles::JEFE_SUCURSAL
                    ? Roles::ADMINISTRADOR_MASTER
                    : $rolOrigen;

                $membresiasCreadas += $this->ensureMembresia(
                    $usuarioId,
                    $nuevaEmpresaId,
                    $rolNuevo,
                    $rolNuevo === Roles::ADMINISTRADOR_MASTER ? null : $nuevaSucursalId,
                    $now
                );

                // Archive filial-scoped membership on parent
                $this->db->table('membresias')->where('id', $memb['id'])->update([
                    'activa'      => 0,
                    'sucursal_id' => null,
                    'updated_at'  => $now,
                ]);
            }

            // Users with ACL on moved docs: ensure membership on new tenant (keep parent role)
            if ($docIds !== []) {
                $aclUsers = $this->db->table('documento_autorizaciones')
                    ->select('usuario_id')
                    ->whereIn('documento_id', $docIds)
                    ->distinct()
                    ->get()
                    ->getResultArray();

                foreach ($aclUsers as $acl) {
                    $uid = (string) $acl['usuario_id'];
                    $parentMemb = $this->db->table('membresias')
                        ->where('usuario_id', $uid)
                        ->where('empresa_id', $origenEmpresaId)
                        ->orderBy('activa', 'DESC')
                        ->get()
                        ->getRowArray();
                    $rolAcl = is_array($parentMemb) ? (string) $parentMemb['rol'] : Roles::CONTADOR;
                    if ($rolAcl === Roles::ADMINISTRADOR_MASTER || $rolAcl === Roles::JEFE_SUCURSAL) {
                        $rolAcl = Roles::CONTADOR;
                    }
                    $membresiasCreadas += $this->ensureMembresia($uid, $nuevaEmpresaId, $rolAcl, null, $now);
                }
            }

            // Mark origin filial as promoted / archived
            $this->db->table('sucursales')->where('id', $sucursalId)->update([
                'activa'                 => 0,
                'estado'                 => self::ESTADO_PROMOVIDA,
                'promoted_to_empresa_id' => $nuevaEmpresaId,
                'promoted_at'            => $now,
                'updated_at'             => $now,
            ]);

            $detalle = [
                'nombre'               => $nombre,
                'slug'                 => $slug,
                'nueva_departamento_id'=> $nuevaDeptoId,
                'nueva_sucursal_id'    => $nuevaSucursalId,
                'secciones'            => $seccionIds,
                'documentos'           => $docIds,
                'membresias_creadas'   => $membresiasCreadas,
                'fiscalizacion_movidas'=> $fiscalMoved,
            ];

            $auditId = new_id();
            $this->db->table('promocion_sucursal_auditoria')->insert([
                'id'                 => $auditId,
                'actor_id'           => $actorId,
                'origen_empresa_id'  => $origenEmpresaId,
                'origen_sucursal_id' => $sucursalId,
                'nueva_empresa_id'   => $nuevaEmpresaId,
                'detalle'            => json_encode($detalle, JSON_UNESCAPED_UNICODE),
                'created_at'         => $now,
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Falló la transacción de promoción.');
            }

            $this->db->transCommit();

            return [
                'nueva_empresa_id'       => $nuevaEmpresaId,
                'nueva_departamento_id'  => $nuevaDeptoId,
                'nueva_sucursal_id'      => $nuevaSucursalId,
                'origen_empresa_id'      => $origenEmpresaId,
                'origen_sucursal_id'     => $sucursalId,
                'documentos_movidos'     => count($docIds),
                'secciones_movidas'      => count($seccionIds),
                'membresias_creadas'     => $membresiasCreadas,
                'audit_id'               => $auditId,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public static function slugify(string $nombre): string
    {
        $s = strtolower(trim($nombre));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');

        return $s !== '' ? $s : 'empresa';
    }

    /**
     * @return int 1 if created, 0 if already existed
     */
    private function ensureMembresia(
        string $usuarioId,
        string $empresaId,
        string $rol,
        ?string $sucursalId,
        string $now
    ): int {
        $existing = $this->db->table('membresias')
            ->where('usuario_id', $usuarioId)
            ->where('empresa_id', $empresaId)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            if (! (int) ($existing['activa'] ?? 0)) {
                $this->db->table('membresias')->where('id', $existing['id'])->update([
                    'activa'      => 1,
                    'rol'         => $rol,
                    'sucursal_id' => $sucursalId,
                    'updated_at'  => $now,
                ]);
            }

            return 0;
        }

        $this->db->table('membresias')->insert([
            'id'          => new_id(),
            'empresa_id'  => $empresaId,
            'usuario_id'  => $usuarioId,
            'rol'         => $rol,
            'sucursal_id' => $sucursalId,
            'activa'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return 1;
    }

    private function uniqueSlug(string $base): string
    {
        $base = self::slugify($base);
        $slug = $base;
        $i    = 2;

        while ($this->db->table('empresas')->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 500) {
                $slug = $base . '-' . substr(new_id(), -8);
                break;
            }
        }

        return $slug;
    }
}
