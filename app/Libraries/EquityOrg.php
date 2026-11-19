<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\EmpresaModel;
use App\Models\EquityPersonaModel;
use App\Models\MembresiaModel;
use App\Models\UsuarioModel;
use App\Libraries\Roles;

/**
 * Matriz Equity y subsidiarias apadrinadas (sin exigir participación societaria).
 */
final class EquityOrg
{
    /**
     * @return array{ok: bool, error?: string, empresa_id?: string}
     */
    public function registrarSubsidiaria(string $matrizEmpresaId, array $input): array
    {
        helper(['id', 'text']);

        $matriz = model(EmpresaModel::class)->find($matrizEmpresaId);
        if (! is_array($matriz)) {
            return ['ok' => false, 'error' => 'Empresa matriz no encontrada.'];
        }
        if (! (int) ($matriz['equity_es_matriz'] ?? 0)) {
            // Auto-marcar como matriz al primer apadrinamiento
            model(EmpresaModel::class)->update($matrizEmpresaId, [
                'equity_es_matriz' => 1,
                'equity_matriz_id' => null,
            ]);
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        $rut    = trim((string) ($input['rut'] ?? ''));
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Nombre de la subsidiaria es obligatorio.'];
        }

        $slugBase = url_title($nombre, '-', true) ?: 'subsidiaria';
        $slug     = $slugBase;
        $n        = 1;
        while (model(EmpresaModel::class)->where('slug', $slug)->first()) {
            $slug = $slugBase . '-' . (++$n);
        }

        $id = new_id();
        model(EmpresaModel::class)->insert([
            'id'               => $id,
            'nombre'           => $nombre,
            'slug'             => $slug,
            'rut'              => $rut !== '' ? $rut : null,
            'activa'           => 1,
            'tipo'             => 'b',
            'equity_es_matriz' => 0,
            'equity_matriz_id' => $matrizEmpresaId,
            'origen_empresa_id'=> $matrizEmpresaId,
        ]);

        return ['ok' => true, 'empresa_id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function subsidiariasDe(string $matrizEmpresaId): array
    {
        return model(EmpresaModel::class)
            ->where('equity_matriz_id', $matrizEmpresaId)
            ->where('activa', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    public function esMatriz(array $empresa): bool
    {
        return (int) ($empresa['equity_es_matriz'] ?? 0) === 1
            || empty($empresa['equity_matriz_id']);
    }

    public function matrizIdDe(array $empresa): string
    {
        if (! empty($empresa['equity_matriz_id'])) {
            return (string) $empresa['equity_matriz_id'];
        }

        return (string) ($empresa['id'] ?? '');
    }

    /**
     * Empresas del grupo (matriz + subsidiarias) visibles para un tenant.
     *
     * @return list<string>
     */
    public function empresaIdsDelGrupo(string $empresaId): array
    {
        $empresa = model(EmpresaModel::class)->find($empresaId);
        if (! is_array($empresa)) {
            return [$empresaId];
        }
        $matrizId = $this->matrizIdDe($empresa);
        $ids = [$matrizId];
        foreach ($this->subsidiariasDe($matrizId) as $s) {
            $ids[] = (string) $s['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Alta de persona + usuario (opcional) con verificación y clave por defecto.
     *
     * @return array{ok: bool, error?: string, persona_id?: string, usuario_id?: string, password?: string, verify_url?: string}
     */
    public function altaPersonaConUsuario(string $empresaId, array $input, string $actorUsuarioId): array
    {
        helper('id');

        $nombre   = trim((string) ($input['nombre'] ?? ''));
        $apellido = trim((string) ($input['apellido'] ?? ''));
        $email    = strtolower(trim((string) ($input['email'] ?? '')));
        $rut      = trim((string) ($input['rut'] ?? ''));
        $tipo     = trim((string) ($input['tipo'] ?? 'socio')) ?: 'socio';
        $repId    = trim((string) ($input['representado_por_usuario_id'] ?? ''));

        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Nombre es obligatorio.'];
        }

        if ($repId !== '') {
            $rep = model(UsuarioModel::class)->find($repId);
            if (! is_array($rep) || ! (int) ($rep['activo'] ?? 0)) {
                return ['ok' => false, 'error' => 'El usuario representante no existe o está inactivo.'];
            }
            $grupo = $this->empresaIdsDelGrupo($empresaId);
            $okRep = false;
            foreach (model(MembresiaModel::class)->forUsuario($repId) as $m) {
                if (in_array($m['empresa_id'], $grupo, true)) {
                    $okRep = true;
                    break;
                }
            }
            if (! $okRep) {
                return ['ok' => false, 'error' => 'El representante debe pertenecer a la matriz o una subsidiaria del grupo.'];
            }

            // Representado: solo ficha persona; declara el usuario representante (sin login propio).
            $personaId = new_id();
            model(EquityPersonaModel::class)->insert([
                'id'                          => $personaId,
                'empresa_id'                  => $empresaId,
                'tipo'                        => $tipo,
                'nombre'                      => $nombre,
                'apellido'                    => $apellido !== '' ? $apellido : null,
                'rut'                         => $rut !== '' ? $rut : null,
                'email'                       => $email !== '' ? $email : null,
                'usuario_id'                  => null,
                'representado_por_usuario_id' => $repId,
                'activo'                      => 1,
                'creado_por_id'               => $actorUsuarioId,
            ]);

            return [
                'ok'         => true,
                'persona_id' => $personaId,
                'usuario_id' => null,
                'password'   => null,
                'verify_url' => null,
            ];
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Correo válido es obligatorio para habilitar el usuario.'];
        }

        $usuarios = model(UsuarioModel::class);
        $existing = $usuarios->findByEmail($email);
        $plain    = null;
        $verifyToken = bin2hex(random_bytes(24));
        $verifyUrl   = null;

        if ($existing === null) {
            $plain = EquityPassword::mintDefault(8);
            $usuarioId = new_id();
            $usuarios->insert([
                'id'                   => $usuarioId,
                'email'                => $email,
                'nombre'               => trim($nombre . ' ' . $apellido),
                'password_hash'        => password_hash($plain, PASSWORD_DEFAULT),
                'activo'               => 1,
                'es_admin_central'     => 0,
                'email_verified_at'    => null,
                'email_verify_token'   => $verifyToken,
                'email_verify_sent_at' => date('Y-m-d H:i:s'),
                'must_change_password' => 1,
            ]);
        } else {
            $usuarioId = (string) $existing['id'];
            if (empty($existing['email_verified_at'])) {
                $usuarios->update($usuarioId, [
                    'email_verify_token'   => $verifyToken,
                    'email_verify_sent_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $verifyToken = null;
            }
        }

        $membExist = model(MembresiaModel::class)
            ->where('usuario_id', $usuarioId)
            ->where('empresa_id', $empresaId)
            ->first();
        if ($membExist === null) {
            model(MembresiaModel::class)->insert([
                'id'         => new_id(),
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'rol'        => Roles::JEFE_SUCURSAL,
                'activa'     => 1,
                'es_socio'   => $tipo === 'socio' ? 1 : 0,
            ]);
        }

        $personaId = new_id();
        model(EquityPersonaModel::class)->insert([
            'id'                          => $personaId,
            'empresa_id'                  => $empresaId,
            'tipo'                        => $tipo,
            'nombre'                      => $nombre,
            'apellido'                    => $apellido !== '' ? $apellido : null,
            'rut'                         => $rut !== '' ? $rut : null,
            'email'                       => $email,
            'usuario_id'                  => $usuarioId,
            'representado_por_usuario_id' => null,
            'activo'                      => 1,
            'creado_por_id'               => $actorUsuarioId,
        ]);

        if ($verifyToken !== null) {
            $verifyUrl = site_url('verificar-email?t=' . rawurlencode($verifyToken));
            EquityMail::enviarVerificacion($email, trim($nombre . ' ' . $apellido), $verifyUrl, $plain);
        }

        return [
            'ok'         => true,
            'persona_id' => $personaId,
            'usuario_id' => $usuarioId,
            'password'   => $plain,
            'verify_url' => $verifyUrl,
        ];
    }

    /**
     * Empresas visibles para el usuario en Equity: grupos de todas sus membresías
     * (evita “sin persona” cuando la ficha vive en otra empresa del mismo usuario).
     *
     * @return list<string>
     */
    public function empresaIdsVisiblesParaUsuario(string $usuarioId, ?string $empresaSesionId = null): array
    {
        $ids = [];
        if ($empresaSesionId !== null && $empresaSesionId !== '') {
            foreach ($this->empresaIdsDelGrupo($empresaSesionId) as $eid) {
                $ids[$eid] = true;
            }
        }
        foreach (model(MembresiaModel::class)->forUsuario($usuarioId) as $m) {
            $eid = (string) ($m['empresa_id'] ?? '');
            if ($eid === '') {
                continue;
            }
            foreach ($this->empresaIdsDelGrupo($eid) as $gid) {
                $ids[$gid] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Personas que el usuario puede declarar en esta sesión (propia + representados).
     *
     * @return list<array<string, mixed>>
     */
    public function personasDeclarables(string $usuarioId, string $empresaId): array
    {
        $grupo = $this->empresaIdsVisiblesParaUsuario($usuarioId, $empresaId);
        $model = model(EquityPersonaModel::class);

        $propias = [];
        $reps    = [];
        if ($grupo !== []) {
            $propias = $model
                ->whereIn('empresa_id', $grupo)
                ->where('usuario_id', $usuarioId)
                ->where('activo', 1)
                ->orderBy('nombre', 'ASC')
                ->findAll();
            $reps = $model
                ->whereIn('empresa_id', $grupo)
                ->where('representado_por_usuario_id', $usuarioId)
                ->where('activo', 1)
                ->orderBy('nombre', 'ASC')
                ->findAll();
        }

        // Fallback: ficha propia ligada al usuario aunque el empresa_id no esté en el grupo
        // (datos cargados bajo otro tenant / seed). Así el menú patrimonial no queda vacío.
        if ($propias === [] && $reps === []) {
            $propias = $model
                ->where('usuario_id', $usuarioId)
                ->where('activo', 1)
                ->orderBy('nombre', 'ASC')
                ->findAll();
        }

        $map = [];
        foreach (array_merge($propias, $reps) as $p) {
            $map[$p['id']] = $p;
        }

        return array_values($map);
    }
}
