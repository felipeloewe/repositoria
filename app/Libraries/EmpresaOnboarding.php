<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaModel;
use App\Models\PlanModel;
use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * Shared alta: Empresa first, then Admin Master membership.
 * Self-serve → tipo (b) FREE; Admin central may choose (a) + paid plan.
 */
final class EmpresaOnboarding
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param array{
     *   nombre: string,
     *   slug?: ?string,
     *   rut?: ?string,
     *   tipo?: string,
     *   plan_id?: ?string,
     *   admin_nombre: string,
     *   admin_email: string,
     *   admin_password: string,
     *   create_defaults?: bool
     * } $input
     *
     * @return array{
     *   empresa_id: string,
     *   usuario_id: string,
     *   membresia_id: string,
     *   departamento_id: ?string,
     *   sucursal_id: ?string,
     *   tipo: string,
     *   plan_codigo: ?string
     * }
     */
    public function createEmpresaWithAdmin(array $input): array
    {
        helper('id');

        $nombre = trim((string) ($input['nombre'] ?? ''));
        $slugIn = trim((string) ($input['slug'] ?? ''));
        $rut    = trim((string) ($input['rut'] ?? '')) ?: null;
        $tipo   = (string) ($input['tipo'] ?? PlanEntitlement::TIPO_B);
        if ($tipo !== PlanEntitlement::TIPO_A && $tipo !== PlanEntitlement::TIPO_B) {
            throw new RuntimeException('Tipo de empresa inválido (usa a o b).');
        }

        $adminNombre = trim((string) ($input['admin_nombre'] ?? ''));
        $adminEmail  = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminPass   = (string) ($input['admin_password'] ?? '');
        $createDefs  = ($input['create_defaults'] ?? true) !== false;

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }
        if ($adminNombre === '' || $adminEmail === '') {
            throw new RuntimeException('Nombre y correo del administrador son obligatorios.');
        }
        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo del administrador no es válido.');
        }
        if (strlen($adminPass) < 6) {
            throw new RuntimeException('La contraseña del administrador debe tener al menos 6 caracteres.');
        }

        $existingUser = model(UsuarioModel::class)->findByEmail($adminEmail);
        if ($existingUser !== null) {
            throw new RuntimeException(
                'Ese correo ya está registrado. Ingresa con tu cuenta o usa otro correo para el administrador.'
            );
        }

        $slug = $this->uniqueSlug($slugIn !== '' ? $slugIn : PromoverSucursalService::slugify($nombre));

        $planId     = null;
        $planCodigo = null;
        $status     = PlanEntitlement::STATUS_FREE;
        $seats      = PlanEntitlement::FREE_MAX_USERS;
        $storage    = PlanEntitlement::FREE_MAX_STORAGE;

        if ($tipo === PlanEntitlement::TIPO_B) {
            $free = model(PlanModel::class)->findByCodigo(PlanEntitlement::CODIGO_FREE);
            if ($free === null || ! (int) ($free['activo'] ?? 0)) {
                throw new RuntimeException('Plan FREE no está disponible. Contacta al Administrador central.');
            }
            $planId     = $free['id'];
            $planCodigo = $free['codigo'];
            $defaults   = PlanEntitlement::defaultsForTipoBFree();
            $status     = $defaults['subscription_status'];
            $seats      = $defaults['seats_limit'];
            $storage    = $defaults['storage_limit_bytes'];
        } else {
            // tipo (a): always paid — optional plan_id from Admin central
            $requestedPlan = trim((string) ($input['plan_id'] ?? '')) ?: null;
            if ($requestedPlan !== null) {
                $plan = model(PlanModel::class)->find($requestedPlan);
                if ($plan === null || ! (int) ($plan['activo'] ?? 0)) {
                    throw new RuntimeException('Plan de pago no válido o inactivo.');
                }
                if ((int) ($plan['es_gratuito'] ?? 0) === 1) {
                    throw new RuntimeException('Las empresas multiempresa (tipo a) no pueden usar el plan FREE.');
                }
                $aplicable = (string) ($plan['tipo_aplicable'] ?? 'ambos');
                if ($aplicable !== 'ambos' && $aplicable !== PlanEntitlement::TIPO_A) {
                    throw new RuntimeException('Ese plan no aplica a empresas tipo (a).');
                }
                $planId     = $plan['id'];
                $planCodigo = $plan['codigo'];
                $status     = PlanEntitlement::STATUS_ACTIVE;
                $seats      = (int) ($plan['max_usuarios'] ?? 0);
                $storage    = (int) ($plan['max_storage_bytes'] ?? 0);
            } else {
                $defaults   = PlanEntitlement::defaultsForPromotedTipoA();
                $status     = $defaults['subscription_status'];
                $seats      = $defaults['seats_limit'];
                $storage    = $defaults['storage_limit_bytes'];
                $planId     = null;
                $planCodigo = null;
            }
        }

        $now         = date('Y-m-d H:i:s');
        $empresaId   = new_id();
        $usuarioId   = new_id();
        $membresiaId = new_id();
        $deptoId     = null;
        $sucursalId  = null;

        $this->db->transBegin();

        try {
            $this->db->table('empresas')->insert([
                'id'                   => $empresaId,
                'nombre'               => $nombre,
                'slug'                 => $slug,
                'rut'                  => $rut,
                'activa'               => 1,
                'tipo'                 => $tipo,
                'plan_id'              => $planId,
                'subscription_status'  => $status,
                'seats_limit'          => $seats,
                'storage_limit_bytes'  => $storage,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            $this->db->table('usuarios')->insert([
                'id'               => $usuarioId,
                'email'            => $adminEmail,
                'nombre'           => $adminNombre,
                'password_hash'    => password_hash($adminPass, PASSWORD_DEFAULT),
                'activo'           => 1,
                'es_admin_central' => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            if ($createDefs) {
                $deptoId = new_id();
                $this->db->table('departamentos')->insert([
                    'id'         => $deptoId,
                    'empresa_id' => $empresaId,
                    'nombre'     => 'General',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $sucursalId = new_id();
                $this->db->table('sucursales')->insert([
                    'id'              => $sucursalId,
                    'empresa_id'      => $empresaId,
                    'departamento_id' => $deptoId,
                    'nombre'          => 'Casa Matriz',
                    'codigo'          => 'CM',
                    'activa'          => 1,
                    'estado'          => 'ACTIVA',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }

            $this->db->table('membresias')->insert([
                'id'          => $membresiaId,
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'rol'         => Roles::ADMINISTRADOR_MASTER,
                'sucursal_id' => null,
                'activa'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('No se pudo completar el alta de la empresa.');
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        // Storage folder outside DB transaction
        $dir = DocumentStorage::empresaDir($empresaId);
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0750, true) && ! is_dir($dir)) {
                log_message('error', 'No se pudo crear STORAGE_ROOT/{empresaId}: {dir}', ['dir' => $dir]);
            }
        }

        return [
            'empresa_id'      => $empresaId,
            'usuario_id'      => $usuarioId,
            'membresia_id'    => $membresiaId,
            'departamento_id' => $deptoId,
            'sucursal_id'     => $sucursalId,
            'tipo'            => $tipo,
            'plan_codigo'     => $planCodigo,
        ];
    }

    /**
     * Validate empresa draft fields (step 1) without writing.
     *
     * @return array{nombre: string, slug: string, rut: ?string}
     */
    public function validateEmpresaDraft(string $nombre, ?string $slug = null, ?string $rut = null): array
    {
        $nombre = trim($nombre);
        $slugIn = trim((string) $slug);
        $rut    = trim((string) $rut) ?: null;

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        $slug = $this->uniqueSlug($slugIn !== '' ? $slugIn : PromoverSucursalService::slugify($nombre));

        // Ensure FREE exists for self-serve
        $free = model(PlanModel::class)->findByCodigo(PlanEntitlement::CODIGO_FREE);
        if ($free === null || ! (int) ($free['activo'] ?? 0)) {
            throw new RuntimeException('El plan FREE no está disponible en este momento.');
        }

        return [
            'nombre' => $nombre,
            'slug'   => $slug,
            'rut'    => $rut,
        ];
    }

    private function uniqueSlug(string $base): string
    {
        $base = PromoverSucursalService::slugify($base);
        $slug = $base;
        $i    = 2;

        while (model(EmpresaModel::class)->where('slug', $slug)->countAllResults() > 0) {
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
