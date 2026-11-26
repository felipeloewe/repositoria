<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\MembresiaModel;
use App\Models\UsuarioModel;

/**
 * Session auth by real membership (usuarioId + membresiaId).
 * No role/persona switcher as product entry.
 */
final class MembershipAuth
{
    private const SESSION_USER = 'auth_usuario_id';
    private const SESSION_MEMB = 'auth_membresia_id';
    private const SESSION_NOTICE = 'auth_empresa_notice';
    private const SESSION_PERSONA = 'auth_equity_persona_id';

    public function attempt(string $email, string $password): array
    {
        helper('id');

        $usuarios = model(UsuarioModel::class);
        $user     = $usuarios->findByEmail(strtolower(trim($email)));

        if ($user === null || ! IdentityVault::isUsuarioActivo($user)) {
            return ['ok' => false, 'error' => 'Credenciales inválidas.'];
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || ! password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Credenciales inválidas.'];
        }

        if (empty($user['email_verified_at']) && ! empty($user['email_verify_token'])) {
            return ['ok' => false, 'error' => 'Debes verificar tu correo antes de ingresar. Revisa el enlace de invitación.'];
        }

        $membresias = model(MembresiaModel::class)->forUsuario($user['id']);
        if ($membresias === []) {
            return ['ok' => false, 'error' => 'El usuario no tiene membresías activas.'];
        }

        $session = session();
        $session->set(self::SESSION_USER, $user['id']);
        $session->set('auth_last_activity', time());
        $session->remove(self::SESSION_PERSONA);

        if (count($membresias) === 1) {
            $session->set(self::SESSION_MEMB, $membresias[0]['id']);

            return ['ok' => true, 'need_empresa' => false];
        }

        $session->remove(self::SESSION_MEMB);

        return ['ok' => true, 'need_empresa' => true, 'membresias' => $membresias];
    }

    public function selectMembresia(string $membresiaId): array
    {
        $usuarioId = $this->usuarioId();
        if ($usuarioId === null) {
            return ['ok' => false, 'error' => 'Sesión no iniciada.'];
        }

        $memb = model(MembresiaModel::class)->findActive($membresiaId);
        if ($memb === null || $memb['usuario_id'] !== $usuarioId) {
            return ['ok' => false, 'error' => 'Membresía no válida.'];
        }

        $session   = session();
        $prevMemb  = $this->membresia();
        $prevRol   = $prevMemb['rol'] ?? null;
        $notice    = null;

        if ($prevRol !== null && $prevRol !== $memb['rol']) {
            $notice = sprintf(
                'Cambiaste a %s. El perfil se restableció a: %s.',
                $memb['empresa_nombre'],
                Roles::label($memb['rol'])
            );
            $session->setFlashdata(self::SESSION_NOTICE, $notice);
        }

        $session->set(self::SESSION_MEMB, $memb['id']);

        return ['ok' => true, 'notice' => $notice];
    }

    /**
     * Establish session after signup / admin-created tenant (no password re-check).
     */
    public function establish(string $usuarioId, string $membresiaId): array
    {
        $memb = model(MembresiaModel::class)->findActive($membresiaId);
        if ($memb === null || $memb['usuario_id'] !== $usuarioId) {
            return ['ok' => false, 'error' => 'Membresía no válida.'];
        }

        $session = session();
        $session->set(self::SESSION_USER, $usuarioId);
        $session->set(self::SESSION_MEMB, $membresiaId);
        $session->set('auth_last_activity', time());

        return ['ok' => true];
    }

    public function logout(): void
    {
        session()->remove([self::SESSION_USER, self::SESSION_MEMB, self::SESSION_NOTICE, self::SESSION_PERSONA, 'auth_last_activity']);
        session()->destroy();
    }

    public function equityPersonaId(): ?string
    {
        $id = session()->get(self::SESSION_PERSONA);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function setEquityPersonaId(?string $personaId): void
    {
        if ($personaId === null || $personaId === '') {
            session()->remove(self::SESSION_PERSONA);

            return;
        }
        session()->set(self::SESSION_PERSONA, $personaId);
    }

    public function clearEquityPersona(): void
    {
        session()->remove(self::SESSION_PERSONA);
    }

    public function check(): bool
    {
        return $this->usuarioId() !== null && $this->membresiaId() !== null;
    }

    public function usuarioId(): ?string
    {
        $id = session()->get(self::SESSION_USER);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function membresiaId(): ?string
    {
        $id = session()->get(self::SESSION_MEMB);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function membresia(): ?array
    {
        $id = $this->membresiaId();
        if ($id === null) {
            return null;
        }

        return model(MembresiaModel::class)->findActive($id);
    }

    public function pullEmpresaNotice(): ?string
    {
        $n = session()->getFlashdata(self::SESSION_NOTICE);

        return is_string($n) ? $n : null;
    }
}
