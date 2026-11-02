<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaModel;
use App\Models\PlanModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Commercial entitlements: tipo (a|b), plan seats/storage, Spanish block messages.
 *
 * Rules:
 * - tipo (b): FREE until 3 users AND 10 GB; then paid plan required.
 * - tipo (a): always requires a paid plan (no free tier).
 * - No payment gateway — Admin central assigns plans manually.
 */
final class PlanEntitlement
{
    public const TIPO_A = 'a';
    public const TIPO_B = 'b';

    public const STATUS_FREE          = 'free';
    public const STATUS_ACTIVE        = 'active';
    public const STATUS_REQUIRES_PLAN = 'requires_plan';
    public const STATUS_SUSPENDED     = 'suspended';

    public const CODIGO_FREE    = 'FREE';
    public const CODIGO_STARTER = 'STARTER';
    public const CODIGO_PRO     = 'PRO';

    public const FREE_MAX_USERS   = 3;
    public const FREE_MAX_STORAGE = 10 * 1024 * 1024 * 1024; // 10 GB

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @return array{
     *   empresa: array<string, mixed>,
     *   plan: ?array<string, mixed>,
     *   seats_used: int,
     *   storage_used_bytes: int,
     *   disk_used_bytes: int,
     *   db_storage_bytes: int,
     *   seats_limit: ?int,
     *   storage_limit_bytes: ?int,
     *   seats_ok: bool,
     *   storage_ok: bool,
     *   can_operate: bool
     * }
     */
    public function snapshot(string $empresaId): array
    {
        $empresa = $this->requireEmpresa($empresaId);
        $plan    = null;
        if (! empty($empresa['plan_id'])) {
            $plan = model(PlanModel::class)->find($empresa['plan_id']) ?: null;
        }

        $limits = $this->effectiveLimits($empresa, $plan);
        $usage  = $this->usage($empresaId);

        $seatsOk    = $limits['seats_limit'] === null || $usage['seats_used'] < $limits['seats_limit'];
        $storageOk  = $limits['storage_limit_bytes'] === null
            || $usage['storage_used_bytes'] < $limits['storage_limit_bytes'];
        $canOperate = $this->canOperate($empresa, $limits);

        return [
            'empresa'              => $empresa,
            'plan'                 => $plan,
            'seats_used'           => $usage['seats_used'],
            'storage_used_bytes'   => $usage['storage_used_bytes'],
            'disk_used_bytes'      => $usage['disk_used_bytes'],
            'db_storage_bytes'     => $usage['db_storage_bytes'],
            'seats_limit'          => $limits['seats_limit'],
            'storage_limit_bytes'  => $limits['storage_limit_bytes'],
            'seats_ok'             => $seatsOk,
            'storage_ok'           => $storageOk,
            'can_operate'          => $canOperate,
        ];
    }

    /**
     * Usage for seats + storage. Billable storage = max(disk folder, SUM documentos).
     *
     * @return array{
     *   seats_used: int,
     *   storage_used_bytes: int,
     *   disk_used_bytes: int,
     *   db_storage_bytes: int
     * }
     */
    public function usage(string $empresaId): array
    {
        $seats = (int) $this->db->table('membresias')
            ->where('empresa_id', $empresaId)
            ->where('activa', 1)
            ->countAllResults();

        $row = $this->db->table('documentos')
            ->selectSum('tamano_bytes', 'total')
            ->where('empresa_id', $empresaId)
            ->get()
            ->getRowArray();

        $dbBytes   = (int) ($row['total'] ?? 0);
        $diskBytes = DocumentStorage::empresaDiskBytes($empresaId);

        return [
            'seats_used'         => $seats,
            'disk_used_bytes'    => $diskBytes,
            'db_storage_bytes'   => $dbBytes,
            'storage_used_bytes' => max($diskBytes, $dbBytes),
        ];
    }

    public function assertCanAddUser(string $empresaId): void
    {
        $snap = $this->snapshot($empresaId);
        $emp  = $snap['empresa'];
        $status = (string) ($emp['subscription_status'] ?? '');

        if ($status === self::STATUS_SUSPENDED) {
            throw new RuntimeException(
                'La suscripción de esta empresa está suspendida. Contacta al Administrador central.'
            );
        }

        if ($status === self::STATUS_REQUIRES_PLAN || ! $snap['can_operate']) {
            $tipo = (string) ($emp['tipo'] ?? self::TIPO_B);
            if ($tipo === self::TIPO_A) {
                throw new RuntimeException(
                    'Las empresas multiempresa (tipo a) requieren un Plan de pago asignado por el Administrador central antes de agregar usuarios.'
                );
            }

            throw new RuntimeException(
                'Esta empresa superó el plan FREE (máx. 3 usuarios / 10 GB) o no tiene Plan activo. Elige un Plan de pago con el Administrador central.'
            );
        }

        $limit = $snap['seats_limit'];
        if ($limit !== null && $snap['seats_used'] >= $limit) {
            throw new RuntimeException(sprintf(
                'Límite de usuarios alcanzado (%d / %d). Amplía el Plan o libera una membresía.',
                $snap['seats_used'],
                $limit
            ));
        }
    }

    public function assertCanUpload(string $empresaId, int $additionalBytes): void
    {
        if ($additionalBytes < 0) {
            $additionalBytes = 0;
        }

        $snap   = $this->snapshot($empresaId);
        $emp    = $snap['empresa'];
        $status = (string) ($emp['subscription_status'] ?? '');

        if ($status === self::STATUS_SUSPENDED) {
            throw new RuntimeException(
                'La suscripción de esta empresa está suspendida. No se pueden subir archivos.'
            );
        }

        if ($status === self::STATUS_REQUIRES_PLAN || ! $snap['can_operate']) {
            $tipo = (string) ($emp['tipo'] ?? self::TIPO_B);
            if ($tipo === self::TIPO_A) {
                throw new RuntimeException(
                    'Las empresas multiempresa (tipo a) requieren un Plan de pago asignado por el Administrador central antes de subir archivos.'
                );
            }

            throw new RuntimeException(
                'Esta empresa superó el plan FREE (máx. 3 usuarios / 10 GB) o no tiene Plan activo. Elige un Plan de pago con el Administrador central.'
            );
        }

        $limit = $snap['storage_limit_bytes'];
        if ($limit !== null) {
            $used      = (int) $snap['storage_used_bytes']; // max(disco, BD)
            $projected = $used + $additionalBytes;
            if ($projected > $limit) {
                throw new RuntimeException(sprintf(
                    'Límite de almacenamiento alcanzado (%s en disco/uso de %s del plan). Amplía el Plan o elimina documentos.',
                    self::formatBytes($used),
                    self::formatBytes($limit)
                ));
            }
        }
    }

    /**
     * Assign a plan to an empresa (Admin central). Copies seats/storage limits onto empresa.
     *
     * @return array<string, mixed> updated empresa row
     */
    public function assignPlan(string $empresaId, string $planId): array
    {
        $empresa = $this->requireEmpresa($empresaId);
        $plan    = model(PlanModel::class)->find($planId);
        if ($plan === null || ! (int) ($plan['activo'] ?? 0)) {
            throw new RuntimeException('Plan no válido o inactivo.');
        }

        $tipo = (string) ($empresa['tipo'] ?? self::TIPO_B);
        $aplicable = (string) ($plan['tipo_aplicable'] ?? 'ambos');
        if ($aplicable !== 'ambos' && $aplicable !== $tipo) {
            throw new RuntimeException(sprintf(
                'El plan %s no aplica a empresas tipo (%s).',
                $plan['codigo'],
                $tipo
            ));
        }

        if ($tipo === self::TIPO_A && (int) ($plan['es_gratuito'] ?? 0) === 1) {
            throw new RuntimeException(
                'Las empresas multiempresa (tipo a) no pueden usar el plan FREE. Asigna un Plan de pago.'
            );
        }

        $status = (int) ($plan['es_gratuito'] ?? 0) === 1
            ? self::STATUS_FREE
            : self::STATUS_ACTIVE;

        $data = [
            'plan_id'              => $plan['id'],
            'subscription_status'  => $status,
            'seats_limit'          => $plan['max_usuarios'],
            'storage_limit_bytes'  => $plan['max_storage_bytes'],
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        model(EmpresaModel::class)->update($empresaId, $data);

        $updated = $this->requireEmpresa($empresaId);

        return $updated;
    }

    /**
     * Defaults for a newly promoted tipo-(a) empresa: no FREE, needs paid plan.
     *
     * @return array{tipo: string, plan_id: null, subscription_status: string, seats_limit: int, storage_limit_bytes: int}
     */
    public static function defaultsForPromotedTipoA(): array
    {
        return [
            'tipo'                 => self::TIPO_A,
            'plan_id'              => null,
            'subscription_status'  => self::STATUS_REQUIRES_PLAN,
            'seats_limit'          => 0,
            'storage_limit_bytes'  => 0,
        ];
    }

    /**
     * Defaults for a new tipo-(b) filial on FREE.
     *
     * @return array{tipo: string, subscription_status: string, seats_limit: int, storage_limit_bytes: int}
     */
    public static function defaultsForTipoBFree(): array
    {
        return [
            'tipo'                 => self::TIPO_B,
            'subscription_status'  => self::STATUS_FREE,
            'seats_limit'          => self::FREE_MAX_USERS,
            'storage_limit_bytes'  => self::FREE_MAX_STORAGE,
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
            }
        }

        return number_format($value, 2, '.', '') . ' PB';
    }

    /**
     * @param array<string, mixed> $empresa
     * @param array<string, mixed>|null $plan
     *
     * @return array{seats_limit: ?int, storage_limit_bytes: ?int}
     */
    private function effectiveLimits(array $empresa, ?array $plan): array
    {
        $status = (string) ($empresa['subscription_status'] ?? self::STATUS_FREE);

        if ($status === self::STATUS_REQUIRES_PLAN || $status === self::STATUS_SUSPENDED) {
            return [
                'seats_limit'         => (int) ($empresa['seats_limit'] ?? 0),
                'storage_limit_bytes' => (int) ($empresa['storage_limit_bytes'] ?? 0),
            ];
        }

        $seats = array_key_exists('seats_limit', $empresa) && $empresa['seats_limit'] !== null
            ? (int) $empresa['seats_limit']
            : ($plan['max_usuarios'] ?? self::FREE_MAX_USERS);

        $storage = array_key_exists('storage_limit_bytes', $empresa) && $empresa['storage_limit_bytes'] !== null
            ? (int) $empresa['storage_limit_bytes']
            : ($plan['max_storage_bytes'] ?? self::FREE_MAX_STORAGE);

        // null on plan means unlimited; prefer explicit empresa columns when set
        if ($plan !== null && $empresa['seats_limit'] === null && $plan['max_usuarios'] === null) {
            $seats = null;
        }
        if ($plan !== null && $empresa['storage_limit_bytes'] === null && $plan['max_storage_bytes'] === null) {
            $storage = null;
        }

        return [
            'seats_limit'         => $seats,
            'storage_limit_bytes' => $storage,
        ];
    }

    /**
     * @param array<string, mixed> $empresa
     * @param array{seats_limit: ?int, storage_limit_bytes: ?int} $limits
     */
    private function canOperate(array $empresa, array $limits): bool
    {
        $status = (string) ($empresa['subscription_status'] ?? '');
        if ($status === self::STATUS_SUSPENDED || $status === self::STATUS_REQUIRES_PLAN) {
            return false;
        }

        $tipo = (string) ($empresa['tipo'] ?? self::TIPO_B);
        if ($tipo === self::TIPO_A && (empty($empresa['plan_id']) || $status === self::STATUS_FREE)) {
            return false;
        }

        return $status === self::STATUS_FREE || $status === self::STATUS_ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireEmpresa(string $empresaId): array
    {
        $row = model(EmpresaModel::class)->find($empresaId);
        if ($row === null) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        return $row;
    }
}
