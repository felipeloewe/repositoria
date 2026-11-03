<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EmpresaFormService;
use App\Libraries\FormEngine;
use App\Libraries\RecursoSistema;
use App\Libraries\SocioFormService;
use App\Models\EmpresaFormPlantillaModel;
use App\Models\EmpresaFormShareModel;
use App\Models\SocioFormPlantillaModel;
use App\Models\SocioFormShareModel;
use RuntimeException;

/**
 * Fill público de formularios (socios o empresa) vía /f/{token} + contraseña dinámica.
 * Sin login; sesión solo para gate de unlock del share.
 */
class FormularioPublico extends BaseController
{
    private const SCOPE_SOCIO   = 'socio';
    private const SCOPE_EMPRESA = 'empresa';

    private function sessionKey(string $scope, string $shareId): string
    {
        return 'form_unlock_' . $scope . '_' . $shareId;
    }

    /**
     * @return array{
     *   scope: string,
     *   share: array<string, mixed>,
     *   plantilla: array<string, mixed>,
     *   label: string
     * }|null
     */
    private function resolveShare(string $token): ?array
    {
        $socio = model(SocioFormShareModel::class)->where('token', $token)->first();
        if (is_array($socio)) {
            $plantilla = model(SocioFormPlantillaModel::class)->find($socio['plantilla_id']);
            if (! is_array($plantilla) || (int) $plantilla['activo'] !== 1) {
                return null;
            }
            $empresaId = (string) $socio['empresa_id'];
            if (RecursoSistema::isGenerica($plantilla)) {
                $clave = (string) $plantilla['clave_sistema'];
                if (! RecursoSistema::isActivo($empresaId, RecursoSistema::TIPO_FORM_PLANTILLA, $clave)) {
                    return null;
                }
            } elseif ((string) ($plantilla['empresa_id'] ?? '') !== $empresaId) {
                return null;
            }

            return [
                'scope'     => self::SCOPE_SOCIO,
                'share'     => $socio,
                'plantilla' => $plantilla,
                'label'     => 'Formulario de socios',
            ];
        }

        $empresa = model(EmpresaFormShareModel::class)->where('token', $token)->first();
        if (! is_array($empresa)) {
            return null;
        }
        $plantilla = model(EmpresaFormPlantillaModel::class)->find($empresa['plantilla_id']);
        if (! is_array($plantilla) || (int) $plantilla['activo'] !== 1) {
            return null;
        }
        $empresaId = (string) $empresa['empresa_id'];
        if (RecursoSistema::isGenerica($plantilla)) {
            $clave = (string) $plantilla['clave_sistema'];
            if (! RecursoSistema::isActivo($empresaId, RecursoSistema::TIPO_FORM_PLANTILLA_EMPRESA, $clave)) {
                return null;
            }
        } elseif ((string) ($plantilla['empresa_id'] ?? '') !== $empresaId) {
            return null;
        }

        return [
            'scope'     => self::SCOPE_EMPRESA,
            'share'     => $empresa,
            'plantilla' => $plantilla,
            'label'     => 'Formulario de empresa',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function camposFor(string $scope, string $plantillaId): array
    {
        return $scope === self::SCOPE_EMPRESA
            ? EmpresaFormService::camposOrdenados($plantillaId)
            : SocioFormService::camposOrdenados($plantillaId);
    }

    public function gate(string $token)
    {
        $resolved = $this->resolveShare($token);
        if ($resolved === null) {
            return view('formularios/publico_error', [
                'title' => 'Formulario',
                'error' => 'Enlace no válido o plantilla inactiva.',
                'label' => 'Formulario',
            ]);
        }

        $usable = FormEngine::shareIsUsable($resolved['share']);
        if (! $usable['ok']) {
            return view('formularios/publico_error', [
                'title' => 'Formulario',
                'error' => $usable['error'] ?? 'Enlace no disponible.',
                'label' => $resolved['label'],
            ]);
        }

        $unlocked = (bool) session()->get($this->sessionKey($resolved['scope'], (string) $resolved['share']['id']));
        if (! $unlocked) {
            return view('formularios/publico_unlock', [
                'title'     => 'Formulario',
                'token'     => $token,
                'plantilla' => $resolved['plantilla'],
                'label'     => $resolved['label'],
                'error'     => session()->getFlashdata('error'),
            ]);
        }

        $campos = $this->camposFor($resolved['scope'], (string) $resolved['plantilla']['id']);

        return view('formularios/publico_fill', [
            'title'     => 'Formulario',
            'token'     => $token,
            'plantilla' => $resolved['plantilla'],
            'campos'    => $campos,
            'label'     => $resolved['label'],
            'error'     => session()->getFlashdata('error'),
            'success'   => session()->getFlashdata('success'),
        ]);
    }

    public function unlock(string $token)
    {
        $resolved = $this->resolveShare($token);
        if ($resolved === null) {
            return redirect()->to('/f/' . $token);
        }

        $usable = FormEngine::shareIsUsable($resolved['share']);
        if (! $usable['ok']) {
            return redirect()->to('/f/' . $token)->with('error', $usable['error'] ?? 'No disponible.');
        }

        $password = (string) $this->request->getPost('password');
        if (! password_verify($password, (string) $resolved['share']['password_hash'])) {
            return redirect()->to('/f/' . $token)->with('error', 'Contraseña incorrecta.');
        }

        session()->set($this->sessionKey($resolved['scope'], (string) $resolved['share']['id']), true);

        return redirect()->to('/f/' . $token);
    }

    public function submit(string $token)
    {
        $resolved = $this->resolveShare($token);
        if ($resolved === null) {
            return view('formularios/publico_error', [
                'title' => 'Formulario',
                'error' => 'Enlace no válido.',
                'label' => 'Formulario',
            ]);
        }

        $scope     = $resolved['scope'];
        $share     = $resolved['share'];
        $plantilla = $resolved['plantilla'];
        $shareId   = (string) $share['id'];

        if (! session()->get($this->sessionKey($scope, $shareId))) {
            return redirect()->to('/f/' . $token)->with('error', 'Debes desbloquear el formulario con la contraseña.');
        }

        if ($scope === self::SCOPE_EMPRESA) {
            $share = model(EmpresaFormShareModel::class)->find($shareId);
        } else {
            $share = model(SocioFormShareModel::class)->find($shareId);
        }
        if (! is_array($share)) {
            return redirect()->to('/f/' . $token)->with('error', 'Enlace no válido.');
        }
        $usable = FormEngine::shareIsUsable($share);
        if (! $usable['ok']) {
            return redirect()->to('/f/' . $token)->with('error', $usable['error'] ?? 'No disponible.');
        }

        $campos = $this->camposFor($scope, (string) $plantilla['id']);
        $titulo = trim((string) $this->request->getPost('titulo'));
        if ($titulo === '') {
            $titulo = (string) $plantilla['nombre'] . ' — ' . date('Y-m-d');
        }

        $post  = [];
        $files = [];
        foreach ($campos as $c) {
            $clave        = (string) $c['clave'];
            $post[$clave] = $this->request->getPost($clave);
            if ((string) $c['tipo'] === 'file') {
                $files[$clave] = $this->request->getFile($clave);
            }
        }

        $actorId = (string) $share['created_by_id'];

        try {
            if ($scope === self::SCOPE_EMPRESA) {
                (new EmpresaFormService())->completeFill(
                    $plantilla,
                    $campos,
                    $titulo,
                    EmpresaFormService::ORIGEN_PUBLICO,
                    null,
                    $actorId,
                    $shareId,
                    $post,
                    $files,
                    (string) $share['empresa_id']
                );
            } else {
                (new SocioFormService())->completeFill(
                    $plantilla,
                    $campos,
                    $titulo,
                    SocioFormService::ORIGEN_PUBLICO,
                    null,
                    $actorId,
                    $shareId,
                    $post,
                    $files,
                    (string) $share['empresa_id']
                );
            }
        } catch (RuntimeException $e) {
            return redirect()->to('/f/' . $token)->with('error', $e->getMessage());
        }

        session()->remove($this->sessionKey($scope, $shareId));

        return view('formularios/publico_done', [
            'title'     => 'Formulario',
            'plantilla' => $plantilla,
            'label'     => $resolved['label'],
        ]);
    }
}
