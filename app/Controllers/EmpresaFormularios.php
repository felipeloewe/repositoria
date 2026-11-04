<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EmpresaFormService;
use App\Libraries\MembershipAuth;
use App\Libraries\RecursoSistema;
use App\Models\EmpresaFormCampoModel;
use App\Models\EmpresaFormInstanciaModel;
use App\Models\EmpresaFormPlantillaModel;
use App\Models\EmpresaFormShareModel;
use RuntimeException;

/**
 * Menú Formulario (empresa) — plantillas, relleno interno y mint de shares públicos.
 * PDF → ambito=general. ACL: Master / Jefe (canUploadDocs).
 */
class EmpresaFormularios extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireAccess()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EmpresaFormService::canAccess($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Formulario solo para Master o Jefe de Sucursal.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantillas = EmpresaFormService::plantillasVisibles((string) $memb['empresa_id']);

        $instancias = model(EmpresaFormInstanciaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('created_at', 'DESC')
            ->findAll(40);

        $plantillaMap = [];
        foreach ($plantillas as $p) {
            $plantillaMap[$p['id']] = $p;
        }
        foreach ($instancias as $inst) {
            $pid = (string) ($inst['plantilla_id'] ?? '');
            if ($pid !== '' && ! isset($plantillaMap[$pid])) {
                $row = model(EmpresaFormPlantillaModel::class)->find($pid);
                if (is_array($row)) {
                    $plantillaMap[$pid] = $row;
                }
            }
        }

        return view('formularios/index', [
            'title'        => 'Formulario',
            'memb'         => $memb,
            'plantillas'   => $plantillas,
            'instancias'   => $instancias,
            'plantillaMap' => $plantillaMap,
            'success'      => session()->getFlashdata('success'),
            'error'        => session()->getFlashdata('error'),
            'shareCreds'   => session()->getFlashdata('share_creds'),
        ]);
    }

    public function createForm()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        return view('formularios/form', [
            'title'     => 'Formulario',
            'memb'      => $memb,
            'plantilla' => null,
            'campos'    => [],
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $desc   = trim((string) $this->request->getPost('descripcion')) ?: null;
        if ($nombre === '') {
            return redirect()->to('/formularios/nueva')->with('error', 'Nombre obligatorio.');
        }

        $id = new_id();
        model(EmpresaFormPlantillaModel::class)->insert([
            'id'            => $id,
            'empresa_id'    => $memb['empresa_id'],
            'nombre'        => $nombre,
            'descripcion'   => $desc,
            'activo'        => 1,
            'creado_por_id' => $memb['usuario_id'],
        ]);

        return redirect()->to('/formularios/' . $id)->with('success', 'Plantilla creada. Agrega campos.');
    }

    public function show(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $campos = EmpresaFormService::camposOrdenados($id);
        $shares = model(EmpresaFormShareModel::class)
            ->where('plantilla_id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('created_at', 'DESC')
            ->findAll(20);
        $instancias = model(EmpresaFormInstanciaModel::class)
            ->where('plantilla_id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('created_at', 'DESC')
            ->findAll(20);

        return view('formularios/show', [
            'title'      => 'Formulario',
            'memb'       => $memb,
            'plantilla'  => $plantilla,
            'esGenerica' => RecursoSistema::isGenerica($plantilla),
            'campos'     => $campos,
            'shares'     => $shares,
            'instancias' => $instancias,
            'tiposCampo' => EmpresaFormService::TIPOS,
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
            'shareCreds' => session()->getFlashdata('share_creds'),
        ]);
    }

    public function update(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return redirect()->to('/formularios/' . $id)
                ->with('error', 'Las plantillas genéricas no se editan aquí. Actívalas/desactívalas en Organización.');
        }

        $nombre = trim((string) $this->request->getPost('nombre'));
        $desc   = trim((string) $this->request->getPost('descripcion')) ?: null;
        if ($nombre === '') {
            return redirect()->to('/formularios/' . $id)->with('error', 'Nombre obligatorio.');
        }

        model(EmpresaFormPlantillaModel::class)->update($id, [
            'nombre'      => $nombre,
            'descripcion' => $desc,
        ]);

        return redirect()->to('/formularios/' . $id)->with('success', 'Plantilla actualizada.');
    }

    public function toggle(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return redirect()->to('/formularios/' . $id)
                ->with('error', 'El on/off de plantillas genéricas lo gestiona el Master en Organización.');
        }

        model(EmpresaFormPlantillaModel::class)->update($id, [
            'activo' => (int) $plantilla['activo'] === 1 ? 0 : 1,
        ]);

        return redirect()->to('/formularios/' . $id)->with('success', 'Estado de plantilla actualizado.');
    }

    public function storeCampo(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return redirect()->to('/formularios/' . $id)
                ->with('error', 'No se pueden agregar campos a plantillas genéricas.');
        }

        helper('id');
        $etiqueta    = trim((string) $this->request->getPost('etiqueta'));
        $tipo        = (string) $this->request->getPost('tipo');
        $obligatorio = $this->request->getPost('obligatorio') ? 1 : 0;
        $claveIn     = trim((string) $this->request->getPost('clave'));
        $seccion     = trim((string) $this->request->getPost('seccion'));
        if (mb_strlen($seccion) > 255) {
            $seccion = mb_substr($seccion, 0, 255);
        }

        if ($etiqueta === '' || ! in_array($tipo, EmpresaFormService::TIPOS, true)) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Etiqueta y tipo válidos son obligatorios.');
        }

        $clave  = $claveIn !== '' ? EmpresaFormService::slugifyClave($claveIn) : EmpresaFormService::slugifyClave($etiqueta);
        $exists = model(EmpresaFormCampoModel::class)
            ->where('plantilla_id', $id)
            ->where('clave', $clave)
            ->first();
        if ($exists !== null) {
            $clave = substr($clave, 0, 50) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        $maxOrden = model(EmpresaFormCampoModel::class)
            ->where('plantilla_id', $id)
            ->selectMax('orden')
            ->first();
        $orden = (int) ($maxOrden['orden'] ?? 0) + 1;

        model(EmpresaFormCampoModel::class)->insert([
            'id'           => new_id(),
            'plantilla_id' => $id,
            'clave'        => $clave,
            'etiqueta'     => $etiqueta,
            'tipo'         => $tipo,
            'obligatorio'  => $obligatorio,
            'orden'        => $orden,
            'seccion'      => $seccion !== '' ? $seccion : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/formularios/' . $id)->with('success', 'Campo agregado.');
    }

    public function deleteCampo(string $id, string $campoId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }
        if (RecursoSistema::isGenerica($plantilla)) {
            return redirect()->to('/formularios/' . $id)
                ->with('error', 'No se pueden eliminar campos de plantillas genéricas.');
        }

        $campo = model(EmpresaFormCampoModel::class)->find($campoId);
        if (! is_array($campo) || (string) $campo['plantilla_id'] !== $id) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Campo no encontrado.');
        }

        model(EmpresaFormCampoModel::class)->delete($campoId);

        return redirect()->to('/formularios/' . $id)->with('success', 'Campo eliminado.');
    }

    public function fillForm(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || ! EmpresaFormService::plantillaUsable($plantilla, (string) $memb['empresa_id'])) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = EmpresaFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Agrega al menos un campo antes de rellenar.');
        }

        return view('formularios/fill', [
            'title'     => 'Formulario',
            'memb'      => $memb,
            'plantilla' => $plantilla,
            'campos'    => $campos,
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function fillSubmit(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || ! EmpresaFormService::plantillaUsable($plantilla, (string) $memb['empresa_id'])) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = EmpresaFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Sin campos.');
        }

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

        try {
            $result = (new EmpresaFormService())->completeFill(
                $plantilla,
                $campos,
                $titulo,
                EmpresaFormService::ORIGEN_INTERNO,
                (string) $memb['usuario_id'],
                (string) $memb['usuario_id'],
                null,
                $post,
                $files,
                (string) $memb['empresa_id']
            );
        } catch (RuntimeException $e) {
            return redirect()->to('/formularios/' . $id . '/rellenar')->with('error', $e->getMessage());
        }

        return redirect()->to('/documentos/' . $result['documento_id'])
            ->with('success', 'Formulario completado. PDF guardado en Documentos.');
    }

    public function share(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || ! EmpresaFormService::plantillaUsable($plantilla, (string) $memb['empresa_id'])) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = EmpresaFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Agrega campos antes de compartir.');
        }

        helper('id');
        $maxUsos = (int) $this->request->getPost('max_usos');
        if ($maxUsos < 1) {
            $maxUsos = 1;
        }
        if ($maxUsos > 50) {
            $maxUsos = 50;
        }

        $horas  = (int) $this->request->getPost('horas_vigencia');
        $expira = null;
        if ($horas > 0) {
            $expira = date('Y-m-d H:i:s', time() + ($horas * 3600));
        }

        // Evitar colisión de token con shares de socios (misma ruta pública /f/{token}).
        do {
            $token = bin2hex(random_bytes(16));
            $clashSocio   = model(\App\Models\SocioFormShareModel::class)->where('token', $token)->first();
            $clashEmpresa = model(EmpresaFormShareModel::class)->where('token', $token)->first();
        } while ($clashSocio !== null || $clashEmpresa !== null);

        $password = EmpresaFormService::mintPassword();
        $shareId  = new_id();

        model(EmpresaFormShareModel::class)->insert([
            'id'            => $shareId,
            'empresa_id'    => $memb['empresa_id'],
            'plantilla_id'  => $id,
            'token'         => $token,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'expira_at'     => $expira,
            'max_usos'      => $maxUsos,
            'usos'          => 0,
            'revoked_at'    => null,
            'created_by_id' => $memb['usuario_id'],
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        session()->setFlashdata('share_creds', [
            'url'      => site_url('f/' . $token),
            'password' => $password,
            'expira'   => $expira,
            'max_usos' => $maxUsos,
        ]);

        return redirect()->to('/formularios/' . $id)
            ->with('success', 'Enlace público generado. Copia la contraseña ahora: solo se muestra una vez.');
    }

    public function revokeShare(string $id, string $shareId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = EmpresaFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $share = model(EmpresaFormShareModel::class)->find($shareId);
        if (! is_array($share) || (string) $share['plantilla_id'] !== $id) {
            return redirect()->to('/formularios/' . $id)->with('error', 'Enlace no encontrado.');
        }

        model(EmpresaFormShareModel::class)->update($shareId, [
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/formularios/' . $id)->with('success', 'Enlace revocado.');
    }

    public function showInstancia(string $instanciaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $inst = model(EmpresaFormInstanciaModel::class)->find($instanciaId);
        if (! is_array($inst) || (string) $inst['empresa_id'] !== $memb['empresa_id']) {
            return redirect()->to('/formularios')->with('error', 'Instancia no encontrada.');
        }

        $plantilla  = EmpresaFormService::plantillaOfEmpresa((string) $inst['plantilla_id'], $memb['empresa_id']);
        $campos     = EmpresaFormService::camposOrdenados((string) $inst['plantilla_id']);
        $respuestas = json_decode((string) ($inst['respuestas_json'] ?? '{}'), true);
        if (! is_array($respuestas)) {
            $respuestas = [];
        }

        return view('formularios/instancia', [
            'title'      => 'Formulario',
            'memb'       => $memb,
            'instancia'  => $inst,
            'plantilla'  => $plantilla,
            'campos'     => $campos,
            'respuestas' => $respuestas,
        ]);
    }
}
