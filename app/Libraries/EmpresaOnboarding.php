<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaModel;
use App\Models\PlanModel;
use App\Models\UsuarioModel;
// DepartamentoCatalogo in same namespace
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
     *   alias?: ?string,
     *   slug?: ?string,
     *   rut?: ?string,
     *   tipo?: string,
     *   plan_id?: ?string,
     *   admin_nombre: string,
     *   admin_email: string,
     *   admin_password?: string,
     *   admin_alias: string,
     *   admin_cargo?: ?string,
     *   admin_rut?: ?string,
     *   create_defaults?: bool,
     *   invite_admin?: bool,
     *   equity_matriz?: bool,
     *   admin_es_declarante?: bool
     * } $input
     *
     * @return array{
     *   empresa_id: string,
     *   usuario_id: string,
     *   membresia_id: string,
     *   departamento_id: ?string,
     *   sucursal_id: ?string,
     *   tipo: string,
     *   plan_codigo: ?string,
     *   admin_temp_password?: ?string,
     *   admin_verify_url?: ?string,
     *   admin_email?: string,
     *   admin_es_declarante?: bool,
     *   persona_id?: ?string
     * }
     */
    public function createEmpresaWithAdmin(array $input): array
    {
        helper('id');

        $nombre = trim((string) ($input['nombre'] ?? ''));
        // Empresa Alias → DB slug. Accept alias or slug keys.
        $aliasIn = trim((string) ($input['alias'] ?? $input['slug'] ?? ''));
        $rut     = trim((string) ($input['rut'] ?? '')) ?: null;
        $tipo    = (string) ($input['tipo'] ?? PlanEntitlement::TIPO_B);
        if ($tipo !== PlanEntitlement::TIPO_A && $tipo !== PlanEntitlement::TIPO_B) {
            throw new RuntimeException('Tipo de empresa inválido (usa a o b).');
        }

        $adminNombre = trim((string) ($input['admin_nombre'] ?? ''));
        $adminEmail  = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminPass   = (string) ($input['admin_password'] ?? '');
        $adminAlias  = trim((string) ($input['admin_alias'] ?? ''));
        $adminCargo  = trim((string) ($input['admin_cargo'] ?? '')) ?: null;
        $adminRut    = trim((string) ($input['admin_rut'] ?? '')) ?: null;
        $createDefs  = ($input['create_defaults'] ?? true) !== false;
        $inviteAdmin = (bool) ($input['invite_admin'] ?? false);
        $equityMatriz = (bool) ($input['equity_matriz'] ?? false);
        $adminEsDeclarante = (bool) ($input['admin_es_declarante'] ?? false);
        if ($adminEsDeclarante && ($adminRut === null || IdentityHomolog::normalizeRut($adminRut) === '')) {
            throw new RuntimeException(
                'Si el Admin también es Declarante, el RUT es obligatorio (homologa usuario y ficha persona).'
            );
        }

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        $slug = $this->normalizeRequiredAlias($aliasIn);

        if ($adminNombre === '' || $adminEmail === '') {
            throw new RuntimeException('Nombre y correo del administrador son obligatorios.');
        }
        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo del administrador no es válido.');
        }

        $tempPassword = null;
        $verifyToken  = null;
        if ($inviteAdmin) {
            // Superadmin no define la clave: se minta temporal y el Admin la cambia al entrar.
            $tempPassword = EquityPassword::mintDefault(8);
            $adminPass    = $tempPassword;
            $verifyToken  = bin2hex(random_bytes(24));
        } elseif (strlen($adminPass) < 6) {
            throw new RuntimeException('La contraseña del administrador debe tener al menos 6 caracteres.');
        }

        $userAlias = $this->normalizeRequiredUserAlias($adminAlias);

        $existingUser = model(UsuarioModel::class)->findByEmail($adminEmail);
        if ($existingUser !== null) {
            throw new RuntimeException(
                'Ese correo ya está registrado. Ingresa con tu cuenta o usa otro correo para el administrador.'
            );
        }

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
        $personaId   = null;

        $this->db->transBegin();

        try {
            $empresaRow = [
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
            ];
            if ($this->db->fieldExists('equity_es_matriz', 'empresas')) {
                $empresaRow['equity_es_matriz'] = $equityMatriz ? 1 : 0;
                $empresaRow['equity_matriz_id'] = null;
            }
            $this->db->table('empresas')->insert($empresaRow);

            $usuarioRow = [
                'id'               => $usuarioId,
                'email'            => $adminEmail,
                'nombre'           => $adminNombre,
                'alias'            => $userAlias,
                'cargo'            => $adminCargo,
                'rut'              => $adminRut,
                'password_hash'    => password_hash($adminPass, PASSWORD_DEFAULT),
                'activo'           => 1,
                'es_admin_central' => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            if ($inviteAdmin) {
                $usuarioRow['email_verified_at']    = null;
                $usuarioRow['email_verify_token']   = $verifyToken;
                $usuarioRow['email_verify_sent_at'] = $now;
                $usuarioRow['must_change_password'] = 1;
            }
            $this->db->table('usuarios')->insert($usuarioRow);

            if ($createDefs) {
                $deptoId = new_id();
                $this->db->table('departamentos')->insert([
                    'id'            => $deptoId,
                    'empresa_id'    => $empresaId,
                    'nombre'        => 'General',
                    'clave_sistema' => null,
                    'activo'        => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                // 10 genéricos activos por defecto (Administración → Departamentos).
                DepartamentoCatalogo::ensureForEmpresa($empresaId, $this->db);
                // Agrupaciones / entidades (Administración → Recursos); checkboxes OFF.
                RecursoAgrupacionCatalogo::ensureForEmpresa($empresaId, $this->db);

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

            $membRow = [
                'id'          => $membresiaId,
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'rol'         => Roles::ADMINISTRADOR_MASTER,
                'sucursal_id' => null,
                'activa'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            if ($this->db->fieldExists('es_declarante', 'membresias')) {
                $membRow['es_declarante'] = $adminEsDeclarante ? 1 : 0;
            }
            $this->db->table('membresias')->insert($membRow);

            if ($adminEsDeclarante) {
                $personaId = $this->ensureDeclarantePersona(
                    $empresaId,
                    $usuarioId,
                    $adminNombre,
                    $adminEmail,
                    $adminRut,
                    $usuarioId
                );
            }

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

        $verifyUrl = null;
        if ($inviteAdmin && $verifyToken !== null) {
            $verifyUrl = site_url('verificar-email?t=' . rawurlencode($verifyToken));
            EquityMail::enviarInvitacionAdmin($adminEmail, $adminNombre, $verifyUrl, $tempPassword, $nombre);
        }

        return [
            'empresa_id'           => $empresaId,
            'usuario_id'           => $usuarioId,
            'membresia_id'         => $membresiaId,
            'departamento_id'      => $deptoId,
            'sucursal_id'          => $sucursalId,
            'tipo'                 => $tipo,
            'plan_codigo'          => $planCodigo,
            'admin_email'          => $adminEmail,
            'admin_temp_password'  => $tempPassword,
            'admin_verify_url'     => $verifyUrl,
            'admin_es_declarante'  => $adminEsDeclarante,
            'persona_id'           => $personaId ?? null,
        ];
    }

    /**
     * Validate empresa draft fields (step 1) without writing.
     * Alias (slug) is required and must be unique.
     *
     * @return array{nombre: string, slug: string, rut: ?string}
     */
    public function validateEmpresaDraft(string $nombre, ?string $alias = null, ?string $rut = null): array
    {
        $nombre  = trim($nombre);
        $aliasIn = trim((string) $alias);
        $rut     = trim((string) $rut) ?: null;

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        $slug = $this->normalizeRequiredAlias($aliasIn);

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

    /**
     * Actualiza datos de empresa (nombre, alias, RUT, activa, matriz Equity).
     *
     * @param array{
     *   nombre?: string,
     *   alias?: ?string,
     *   slug?: ?string,
     *   rut?: ?string,
     *   activa?: bool|int,
     *   equity_matriz?: bool|int
     * } $input
     *
     * @return array<string, mixed> fila empresas actualizada
     */
    public function updateEmpresa(string $empresaId, array $input): array
    {
        $empresa = model(EmpresaModel::class)->find($empresaId);
        if ($empresa === null) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        $nombre = array_key_exists('nombre', $input)
            ? trim((string) $input['nombre'])
            : (string) $empresa['nombre'];
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        $aliasIn = array_key_exists('alias', $input) || array_key_exists('slug', $input)
            ? trim((string) ($input['alias'] ?? $input['slug'] ?? ''))
            : (string) $empresa['slug'];
        $slug = $this->normalizeRequiredAlias($aliasIn, $empresaId);

        $rut = array_key_exists('rut', $input)
            ? (trim((string) $input['rut']) ?: null)
            : ($empresa['rut'] ?? null);

        $now  = date('Y-m-d H:i:s');
        $patch = [
            'nombre'     => $nombre,
            'slug'       => $slug,
            'rut'        => $rut,
            'updated_at' => $now,
        ];

        if (array_key_exists('activa', $input)) {
            $patch['activa'] = ((int) $input['activa']) === 1 ? 1 : 0;
        }

        if ($this->db->fieldExists('equity_es_matriz', 'empresas') && array_key_exists('equity_matriz', $input)) {
            $patch['equity_es_matriz'] = ((int) $input['equity_matriz']) === 1 ? 1 : 0;
            if ((int) $patch['equity_es_matriz'] === 1) {
                $patch['equity_matriz_id'] = null;
            }
        }

        $this->db->table('empresas')->where('id', $empresaId)->update($patch);

        $updated = model(EmpresaModel::class)->find($empresaId);
        if ($updated === null) {
            throw new RuntimeException('No se pudo actualizar la empresa.');
        }

        return $updated;
    }

    /**
     * Admins Master activos/inactivos de la empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function listMasterAdmins(string $empresaId): array
    {
        $select = 'm.id AS membresia_id, m.activa AS membresia_activa, m.created_at AS membresia_desde, u.*';
        if ($this->db->fieldExists('es_declarante', 'membresias')) {
            $select .= ', m.es_declarante';
        }

        return $this->db->table('membresias m')
            ->select($select)
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->where('m.empresa_id', $empresaId)
            ->where('m.rol', Roles::ADMINISTRADOR_MASTER)
            ->orderBy('u.nombre', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Invita (o agrega) un Admin Master a una empresa ya existente.
     *
     * @param array{
     *   admin_nombre: string,
     *   admin_email: string,
     *   admin_alias: string,
     *   admin_cargo?: ?string,
     *   admin_rut?: ?string,
     *   admin_es_declarante?: bool
     * } $input
     *
     * @return array{
     *   empresa_id: string,
     *   usuario_id: string,
     *   membresia_id: string,
     *   created_user: bool,
     *   admin_email: string,
     *   admin_temp_password: ?string,
     *   admin_verify_url: ?string,
     *   admin_es_declarante: bool,
     *   persona_id?: ?string
     * }
     */
    public function inviteAdminMaster(string $empresaId, array $input): array
    {
        helper('id');

        $empresa = model(EmpresaModel::class)->find($empresaId);
        if ($empresa === null) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        $adminNombre = trim((string) ($input['admin_nombre'] ?? ''));
        $adminEmail  = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $adminAlias  = trim((string) ($input['admin_alias'] ?? ''));
        $adminCargo  = trim((string) ($input['admin_cargo'] ?? '')) ?: null;
        $adminRut    = trim((string) ($input['admin_rut'] ?? '')) ?: null;
        $adminEsDeclarante = (bool) ($input['admin_es_declarante'] ?? false);

        if ($adminNombre === '' || $adminEmail === '') {
            throw new RuntimeException('Nombre y correo del administrador son obligatorios.');
        }
        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo del administrador no es válido.');
        }

        $existing = model(UsuarioModel::class)->findByEmail($adminEmail);
        if ($adminRut === null && $existing !== null) {
            $adminRut = trim((string) ($existing['rut'] ?? '')) ?: null;
        }
        if ($adminEsDeclarante && ($adminRut === null || IdentityHomolog::normalizeRut($adminRut) === '')) {
            throw new RuntimeException(
                'Si el Admin también es Declarante, el RUT es obligatorio (homologa usuario y ficha persona).'
            );
        }
        if ($existing !== null) {
            $memb = $this->db->table('membresias')
                ->where('empresa_id', $empresaId)
                ->where('usuario_id', $existing['id'])
                ->where('rol', Roles::ADMINISTRADOR_MASTER)
                ->get()
                ->getRowArray();
            if ($memb !== null) {
                throw new RuntimeException(
                    'Ese correo ya es Admin Master de esta empresa. Usa Reinvitar para reenviar acceso.'
                );
            }
        }

        $tempPassword = EquityPassword::mintDefault(8);
        $verifyToken  = bin2hex(random_bytes(24));
        $now          = date('Y-m-d H:i:s');
        $createdUser  = false;
        $usuarioId    = null;
        $membresiaId  = null;

        $this->db->transBegin();

        try {
            if ($existing === null) {
                (new PlanEntitlement($this->db))->assertCanAddUser($empresaId);
                $userAlias = $this->normalizeRequiredUserAlias($adminAlias);
                $usuarioId = new_id();
                $this->db->table('usuarios')->insert([
                    'id'                    => $usuarioId,
                    'email'                 => $adminEmail,
                    'nombre'                => $adminNombre,
                    'alias'                 => $userAlias,
                    'cargo'                 => $adminCargo,
                    'rut'                   => $adminRut,
                    'password_hash'         => password_hash($tempPassword, PASSWORD_DEFAULT),
                    'activo'                => 1,
                    'es_admin_central'      => 0,
                    'email_verified_at'     => null,
                    'email_verify_token'    => $verifyToken,
                    'email_verify_sent_at'  => $now,
                    'must_change_password'  => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);
                $createdUser = true;
            } else {
                $usuarioId = (string) $existing['id'];
                $anyMemb = $this->db->table('membresias')
                    ->where('empresa_id', $empresaId)
                    ->where('usuario_id', $usuarioId)
                    ->get()
                    ->getRowArray();
                if ($anyMemb === null) {
                    (new PlanEntitlement($this->db))->assertCanAddUser($empresaId);
                }

                $userPatch = [
                    'nombre'               => $adminNombre,
                    'cargo'                => $adminCargo,
                    'rut'                  => $adminRut,
                    'activo'               => 1,
                    'password_hash'        => password_hash($tempPassword, PASSWORD_DEFAULT),
                    'email_verified_at'    => null,
                    'email_verify_token'   => $verifyToken,
                    'email_verify_sent_at' => $now,
                    'must_change_password' => 1,
                    'updated_at'           => $now,
                ];
                if ($adminAlias !== '') {
                    $currentAlias = (string) ($existing['alias'] ?? '');
                    if ($currentAlias === '' || strcasecmp($currentAlias, $adminAlias) !== 0) {
                        $userPatch['alias'] = $this->normalizeRequiredUserAlias($adminAlias);
                    }
                }
                $this->db->table('usuarios')->where('id', $usuarioId)->update($userPatch);

                if ($anyMemb !== null && ($anyMemb['rol'] ?? '') !== Roles::ADMINISTRADOR_MASTER) {
                    $patch = [
                        'rol'        => Roles::ADMINISTRADOR_MASTER,
                        'activa'     => 1,
                        'updated_at' => $now,
                    ];
                    if ($this->db->fieldExists('es_declarante', 'membresias')) {
                        $patch['es_declarante'] = $adminEsDeclarante ? 1 : 0;
                    }
                    $this->db->table('membresias')->where('id', $anyMemb['id'])->update($patch);
                    $membresiaId = (string) $anyMemb['id'];
                } elseif ($anyMemb !== null) {
                    $patch = [
                        'activa'     => 1,
                        'updated_at' => $now,
                    ];
                    if ($this->db->fieldExists('es_declarante', 'membresias')) {
                        $patch['es_declarante'] = $adminEsDeclarante ? 1 : 0;
                    }
                    $this->db->table('membresias')->where('id', $anyMemb['id'])->update($patch);
                    $membresiaId = (string) $anyMemb['id'];
                }
            }

            if ($membresiaId === null) {
                $membresiaId = new_id();
                $membRow = [
                    'id'          => $membresiaId,
                    'empresa_id'  => $empresaId,
                    'usuario_id'  => $usuarioId,
                    'rol'         => Roles::ADMINISTRADOR_MASTER,
                    'sucursal_id' => null,
                    'activa'      => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                if ($this->db->fieldExists('es_declarante', 'membresias')) {
                    $membRow['es_declarante'] = $adminEsDeclarante ? 1 : 0;
                }
                $this->db->table('membresias')->insert($membRow);
            }

            $personaId = null;
            if ($adminEsDeclarante) {
                $personaId = $this->ensureDeclarantePersona(
                    $empresaId,
                    (string) $usuarioId,
                    $adminNombre,
                    $adminEmail,
                    $adminRut,
                    (string) $usuarioId
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('No se pudo completar la invitación del Admin.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $verifyUrl = site_url('verificar-email?t=' . rawurlencode($verifyToken));
        EquityMail::enviarInvitacionAdmin(
            $adminEmail,
            $adminNombre,
            $verifyUrl,
            $tempPassword,
            (string) $empresa['nombre']
        );

        return [
            'empresa_id'          => $empresaId,
            'usuario_id'          => (string) $usuarioId,
            'membresia_id'        => (string) $membresiaId,
            'created_user'        => $createdUser,
            'admin_email'         => $adminEmail,
            'admin_temp_password' => $tempPassword,
            'admin_verify_url'    => $verifyUrl,
            'admin_es_declarante' => $adminEsDeclarante,
            'persona_id'          => $personaId ?? null,
        ];
    }

    /**
     * Marca/desmarca Admin Master como declarante en esa empresa (crea o desactiva ficha).
     *
     * @return array{es_declarante: bool, persona_id: ?string}
     */
    public function setAdminEsDeclarante(string $empresaId, string $usuarioId, bool $esDeclarante): array
    {
        helper('id');

        $memb = $this->db->table('membresias')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->where('rol', Roles::ADMINISTRADOR_MASTER)
            ->get()
            ->getRowArray();
        if ($memb === null) {
            throw new RuntimeException('Ese usuario no es Admin Master de esta empresa.');
        }

        $user = model(UsuarioModel::class)->find($usuarioId);
        if ($user === null) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        $now = date('Y-m-d H:i:s');
        if ($this->db->fieldExists('es_declarante', 'membresias')) {
            $this->db->table('membresias')->where('id', $memb['id'])->update([
                'es_declarante' => $esDeclarante ? 1 : 0,
                'updated_at'    => $now,
            ]);
        }

        $personaId = null;
        if ($esDeclarante) {
            $personaId = $this->ensureDeclarantePersona(
                $empresaId,
                $usuarioId,
                (string) ($user['nombre'] ?? $user['email']),
                (string) $user['email'],
                isset($user['rut']) ? (string) $user['rut'] : null,
                $usuarioId
            );
            // Patrimonio va por persona_id: alinea empresa_id residual (seed/import).
            (new PatrimonioRelink($this->db))->alignEmpresaToPersona($personaId);
        } else {
            $this->db->table('equity_personas')
                ->where('empresa_id', $empresaId)
                ->where('usuario_id', $usuarioId)
                ->update(['activo' => 0, 'updated_at' => $now]);
            $row = $this->db->table('equity_personas')
                ->where('empresa_id', $empresaId)
                ->where('usuario_id', $usuarioId)
                ->get()
                ->getRowArray();
            $personaId = $row['id'] ?? null;
        }

        return [
            'es_declarante' => $esDeclarante,
            'persona_id'    => $personaId !== null ? (string) $personaId : null,
        ];
    }

    /**
     * Crea o vincula la ficha equity_personas del Admin declarante en la empresa.
     * Homologa RUT/nombre/email con el usuario; si ya hay persona con ese RUT, la reutiliza (no duplica).
     */
    private function ensureDeclarantePersona(
        string $empresaId,
        string $usuarioId,
        string $nombreCompleto,
        string $email,
        ?string $rut,
        ?string $creadoPorId
    ): string {
        helper('id');

        $rut = trim((string) $rut) ?: null;
        if ($rut === null || IdentityHomolog::normalizeRut($rut) === '') {
            throw new RuntimeException(
                'Para marcar Admin como Declarante debe homologar el RUT (mismo RUT en usuario y ficha persona).'
            );
        }

        $existing = IdentityHomolog::findPersonaForUsuario($this->db, $empresaId, $usuarioId, $rut);
        $now      = date('Y-m-d H:i:s');

        if ($existing !== null) {
            $personaId = (string) $existing['id'];
            IdentityHomolog::syncUsuarioPersona($this->db, $usuarioId, $personaId, [
                'nombre_completo' => $nombreCompleto,
                'email'           => $email,
                'rut'             => $rut,
            ]);

            return $personaId;
        }

        [$nombre, $apellido] = IdentityHomolog::splitNombre($nombreCompleto);
        $personaId           = new_id();
        $row                 = [
            'id'            => $personaId,
            'empresa_id'    => $empresaId,
            'tipo'          => 'socio',
            'nombre'        => $nombre,
            'apellido'      => $apellido,
            'rut'           => $rut,
            'email'         => $email !== '' ? $email : null,
            'usuario_id'    => $usuarioId,
            'activo'        => 1,
            'creado_por_id' => $creadoPorId,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
        if ($this->db->fieldExists('representado_por_usuario_id', 'equity_personas')) {
            $row['representado_por_usuario_id'] = null;
        }
        $this->db->table('equity_personas')->insert($row);

        IdentityHomolog::syncUsuarioPersona($this->db, $usuarioId, $personaId, [
            'nombre_completo' => $nombreCompleto,
            'email'           => $email,
            'rut'             => $rut,
        ]);

        return $personaId;
    }

    /**
     * Reenvía acceso (clave temporal + verificación) a un Admin Master existente.
     *
     * @return array{
     *   empresa_id: string,
     *   usuario_id: string,
     *   admin_email: string,
     *   admin_temp_password: string,
     *   admin_verify_url: string
     * }
     */
    public function reinviteAdminMaster(string $empresaId, string $usuarioId): array
    {
        $empresa = model(EmpresaModel::class)->find($empresaId);
        if ($empresa === null) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        $memb = $this->db->table('membresias')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', $usuarioId)
            ->where('rol', Roles::ADMINISTRADOR_MASTER)
            ->get()
            ->getRowArray();
        if ($memb === null) {
            throw new RuntimeException('Ese usuario no es Admin Master de esta empresa.');
        }

        $user = model(UsuarioModel::class)->find($usuarioId);
        if ($user === null) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        $tempPassword = EquityPassword::mintDefault(8);
        $verifyToken  = bin2hex(random_bytes(24));
        $now          = date('Y-m-d H:i:s');

        $this->db->table('usuarios')->where('id', $usuarioId)->update([
            'activo'               => 1,
            'password_hash'        => password_hash($tempPassword, PASSWORD_DEFAULT),
            'email_verified_at'    => null,
            'email_verify_token'   => $verifyToken,
            'email_verify_sent_at' => $now,
            'must_change_password' => 1,
            'updated_at'           => $now,
        ]);
        $this->db->table('membresias')->where('id', $memb['id'])->update([
            'activa'     => 1,
            'updated_at' => $now,
        ]);

        $email     = (string) $user['email'];
        $nombre    = (string) ($user['nombre'] ?? $email);
        $verifyUrl = site_url('verificar-email?t=' . rawurlencode($verifyToken));
        EquityMail::enviarInvitacionAdmin(
            $email,
            $nombre,
            $verifyUrl,
            $tempPassword,
            (string) $empresa['nombre']
        );

        return [
            'empresa_id'          => $empresaId,
            'usuario_id'          => $usuarioId,
            'admin_email'         => $email,
            'admin_temp_password' => $tempPassword,
            'admin_verify_url'    => $verifyUrl,
        ];
    }

    /**
     * Normalize empresa Alias → empresas.slug; required + unique (no auto-suffix).
     *
     * @param string|null $excludeEmpresaId al editar, permite conservar el mismo alias
     */
    public function normalizeRequiredAlias(string $alias, ?string $excludeEmpresaId = null): string
    {
        $alias = trim($alias);
        if ($alias === '') {
            throw new RuntimeException('El Alias de la empresa es obligatorio.');
        }

        if (! preg_match('/[a-zA-Z0-9]/', $alias)) {
            throw new RuntimeException(
                'El Alias de la empresa no es válido. Usa letras, números o guiones (ej. servicios-andes).'
            );
        }

        $slug = PromoverSucursalService::slugify($alias);
        if ($slug === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new RuntimeException(
                'El Alias de la empresa no es válido. Usa letras, números o guiones (ej. servicios-andes).'
            );
        }

        $q = model(EmpresaModel::class)->where('slug', $slug);
        if ($excludeEmpresaId !== null && $excludeEmpresaId !== '') {
            $q->where('id !=', $excludeEmpresaId);
        }
        if ($q->countAllResults() > 0) {
            throw new RuntimeException('Ese Alias de empresa ya está en uso. Elige otro.');
        }

        return $slug;
    }

    /**
     * Normalize user Alias → usuarios.alias; required + unique.
     */
    public function normalizeRequiredUserAlias(string $alias): string
    {
        $alias = trim($alias);
        if ($alias === '') {
            throw new RuntimeException('El Alias del administrador es obligatorio.');
        }

        $normalized = strtolower($alias);
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '.-_');

        if ($normalized === '' || ! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $normalized)) {
            throw new RuntimeException(
                'El Alias del administrador no es válido. Usa letras, números, puntos, guiones o guion bajo (ej. ana.admin).'
            );
        }

        if (strlen($normalized) > 64) {
            throw new RuntimeException('El Alias del administrador es demasiado largo (máx. 64).');
        }

        if (model(UsuarioModel::class)->findByAlias($normalized) !== null) {
            throw new RuntimeException('Ese Alias de usuario ya está en uso. Elige otro.');
        }

        return $normalized;
    }
}
