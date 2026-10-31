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

    public function attempt(string $email, string $password): array
    {
        helper('id');

        $usuarios = model(UsuarioModel::class);
        $user     = $usuarios->findByEmail(strtolower(trim($email)));

        if ($user === null || ! (int) ($user['activo'] ?? 0)) {
            return ['ok' => false, 'error' => 'Credenciales inválidas.'];
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || ! password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Credenciales inválidas.'];
        }

        $membresias = model(MembresiaModel::class)->forUsuario($user['id']);
        if ($membresias === []) {
            return ['ok' => false, 'error' => 'El usuario no tiene membresías activas.'];
        }

        $session = session();
        $session->set(self::SESSION_USER, $user['id']);

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

    public function logout(): void
    {
        session()->remove([self::SESSION_USER, self::SESSION_MEMB, self::SESSION_NOTICE]);
        session()->destroy();
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
