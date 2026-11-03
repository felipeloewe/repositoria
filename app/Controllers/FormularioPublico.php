<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\SocioFormService;
use App\Models\SocioFormPlantillaModel;
use App\Models\SocioFormShareModel;
use RuntimeException;

/**
 * Fill público de formularios de socios vía /f/{token} + contraseña dinámica.
 * Sin login; sesión solo para gate de unlock del share.
 */
class FormularioPublico extends BaseController
{
    private function sessionKey(string $shareId): string
    {
        return 'socio_form_unlock_' . $shareId;
    }

    /**
     * @return array{share: array<string, mixed>, plantilla: array<string, mixed>}|null
     */
    private function resolveShare(string $token): ?array
    {
        $share = model(SocioFormShareModel::class)->where('token', $token)->first();
        if (! is_array($share)) {
            return null;
        }
        $plantilla = model(SocioFormPlantillaModel::class)->find($share['plantilla_id']);
        if (! is_array($plantilla) || (int) $plantilla['activo'] !== 1) {
            return null;
        }

        return ['share' => $share, 'plantilla' => $plantilla];
    }

    public function gate(string $token)
    {
        $resolved = $this->resolveShare($token);
        if ($resolved === null) {
            return view('socios/publico_error', [
                'title' => 'Formulario',
                'error' => 'Enlace no válido o plantilla inactiva.',
            ]);
        }

        $usable = SocioFormService::shareIsUsable($resolved['share']);
        if (! $usable['ok']) {
            return view('socios/publico_error', [
                'title' => 'Formulario',
                'error' => $usable['error'] ?? 'Enlace no disponible.',
            ]);
        }

        $unlocked = (bool) session()->get($this->sessionKey((string) $resolved['share']['id']));
        if (! $unlocked) {
            return view('socios/publico_unlock', [
                'title'     => 'Formulario',
                'token'     => $token,
                'plantilla' => $resolved['plantilla'],
                'error'     => session()->getFlashdata('error'),
            ]);
        }

        $campos = SocioFormService::camposOrdenados((string) $resolved['plantilla']['id']);

        return view('socios/publico_fill', [
            'title'     => 'Formulario',
            'token'     => $token,
            'plantilla' => $resolved['plantilla'],
            'campos'    => $campos,
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

        $usable = SocioFormService::shareIsUsable($resolved['share']);
        if (! $usable['ok']) {
            return redirect()->to('/f/' . $token)->with('error', $usable['error'] ?? 'No disponible.');
        }

        $password = (string) $this->request->getPost('password');
        if (! password_verify($password, (string) $resolved['share']['password_hash'])) {
            return redirect()->to('/f/' . $token)->with('error', 'Contraseña incorrecta.');
        }

        session()->set($this->sessionKey((string) $resolved['share']['id']), true);

        return redirect()->to('/f/' . $token);
    }

    public function submit(string $token)
    {
        $resolved = $this->resolveShare($token);
        if ($resolved === null) {
            return view('socios/publico_error', [
                'title' => 'Formulario',
                'error' => 'Enlace no válido.',
            ]);
        }

        $share     = $resolved['share'];
        $plantilla = $resolved['plantilla'];
        $shareId   = (string) $share['id'];

        if (! session()->get($this->sessionKey($shareId))) {
            return redirect()->to('/f/' . $token)->with('error', 'Debes desbloquear el formulario con la contraseña.');
        }

        // Re-read share for race on usos
        $share = model(SocioFormShareModel::class)->find($shareId);
        if (! is_array($share)) {
            return redirect()->to('/f/' . $token)->with('error', 'Enlace no válido.');
        }
        $usable = SocioFormService::shareIsUsable($share);
        if (! $usable['ok']) {
            return redirect()->to('/f/' . $token)->with('error', $usable['error'] ?? 'No disponible.');
        }

        $campos = SocioFormService::camposOrdenados((string) $plantilla['id']);
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
            (new SocioFormService())->completeFill(
                $plantilla,
                $campos,
                $titulo,
                SocioFormService::ORIGEN_PUBLICO,
                null,
                $actorId,
                $shareId,
                $post,
                $files
            );
        } catch (RuntimeException $e) {
            return redirect()->to('/f/' . $token)->with('error', $e->getMessage());
        }

        session()->remove($this->sessionKey($shareId));

        return view('socios/publico_done', [
            'title'     => 'Formulario',
            'plantilla' => $plantilla,
        ]);
    }
}
