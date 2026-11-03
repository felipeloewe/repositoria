<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\SocioFormService;
use App\Models\SocioFormCampoModel;
use App\Models\SocioFormInstanciaModel;
use App\Models\SocioFormPlantillaModel;
use App\Models\SocioFormShareModel;
use RuntimeException;

/**
 * Formularios bajo Menú Socios — plantillas, relleno interno y mint de shares públicos.
 */
class SociosFormularios extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireSocio()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! DocumentAcl::isSocio($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Menú Socios solo para membresías con atributo Socio.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantillas = model(SocioFormPlantillaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $instancias = model(SocioFormInstanciaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('created_at', 'DESC')
            ->findAll(40);

        $plantillaMap = [];
        foreach ($plantillas as $p) {
            $plantillaMap[$p['id']] = $p;
        }

        return view('socios/formularios_index', [
            'title'       => 'Formularios socios',
            'memb'        => $memb,
            'plantillas'  => $plantillas,
            'instancias'  => $instancias,
            'plantillaMap'=> $plantillaMap,
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
            'shareCreds'  => session()->getFlashdata('share_creds'),
        ]);
    }

    public function createForm()
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        return view('socios/formularios_form', [
            'title'     => 'Formularios socios',
            'memb'      => $memb,
            'plantilla' => null,
            'campos'    => [],
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $desc   = trim((string) $this->request->getPost('descripcion')) ?: null;
        if ($nombre === '') {
            return redirect()->to('/socios/formularios/nueva')->with('error', 'Nombre obligatorio.');
        }

        $id = new_id();
        model(SocioFormPlantillaModel::class)->insert([
            'id'            => $id,
            'empresa_id'    => $memb['empresa_id'],
            'nombre'        => $nombre,
            'descripcion'   => $desc,
            'activo'        => 1,
            'creado_por_id' => $memb['usuario_id'],
        ]);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Plantilla creada. Agrega campos.');
    }

    public function show(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $campos = SocioFormService::camposOrdenados($id);
        $shares = model(SocioFormShareModel::class)
            ->where('plantilla_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll(20);
        $instancias = model(SocioFormInstanciaModel::class)
            ->where('plantilla_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll(20);

        return view('socios/formularios_show', [
            'title'      => 'Formularios socios',
            'memb'       => $memb,
            'plantilla'  => $plantilla,
            'campos'     => $campos,
            'shares'     => $shares,
            'instancias' => $instancias,
            'tiposCampo' => SocioFormService::TIPOS,
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
            'shareCreds' => session()->getFlashdata('share_creds'),
        ]);
    }

    public function update(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $nombre = trim((string) $this->request->getPost('nombre'));
        $desc   = trim((string) $this->request->getPost('descripcion')) ?: null;
        if ($nombre === '') {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Nombre obligatorio.');
        }

        model(SocioFormPlantillaModel::class)->update($id, [
            'nombre'      => $nombre,
            'descripcion' => $desc,
        ]);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Plantilla actualizada.');
    }

    public function toggle(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        model(SocioFormPlantillaModel::class)->update($id, [
            'activo' => (int) $plantilla['activo'] === 1 ? 0 : 1,
        ]);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Estado de plantilla actualizado.');
    }

    public function storeCampo(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        helper('id');
        $etiqueta    = trim((string) $this->request->getPost('etiqueta'));
        $tipo        = (string) $this->request->getPost('tipo');
        $obligatorio = $this->request->getPost('obligatorio') ? 1 : 0;
        $claveIn     = trim((string) $this->request->getPost('clave'));

        if ($etiqueta === '' || ! in_array($tipo, SocioFormService::TIPOS, true)) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Etiqueta y tipo válidos son obligatorios.');
        }

        $clave = $claveIn !== '' ? SocioFormService::slugifyClave($claveIn) : SocioFormService::slugifyClave($etiqueta);
        $exists = model(SocioFormCampoModel::class)
            ->where('plantilla_id', $id)
            ->where('clave', $clave)
            ->first();
        if ($exists !== null) {
            $clave = substr($clave, 0, 50) . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        $maxOrden = model(SocioFormCampoModel::class)
            ->where('plantilla_id', $id)
            ->selectMax('orden')
            ->first();
        $orden = (int) ($maxOrden['orden'] ?? 0) + 1;

        model(SocioFormCampoModel::class)->insert([
            'id'           => new_id(),
            'plantilla_id' => $id,
            'clave'        => $clave,
            'etiqueta'     => $etiqueta,
            'tipo'         => $tipo,
            'obligatorio'  => $obligatorio,
            'orden'        => $orden,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Campo agregado.');
    }

    public function deleteCampo(string $id, string $campoId)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $campo = model(SocioFormCampoModel::class)->find($campoId);
        if (! is_array($campo) || (string) $campo['plantilla_id'] !== $id) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Campo no encontrado.');
        }

        model(SocioFormCampoModel::class)->delete($campoId);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Campo eliminado.');
    }

    public function fillForm(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || (int) $plantilla['activo'] !== 1) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = SocioFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Agrega al menos un campo antes de rellenar.');
        }

        return view('socios/formularios_fill', [
            'title'     => 'Formularios socios',
            'memb'      => $memb,
            'plantilla' => $plantilla,
            'campos'    => $campos,
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function fillSubmit(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || (int) $plantilla['activo'] !== 1) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = SocioFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Sin campos.');
        }

        $titulo = trim((string) $this->request->getPost('titulo'));
        if ($titulo === '') {
            $titulo = (string) $plantilla['nombre'] . ' — ' . date('Y-m-d');
        }

        $post  = [];
        $files = [];
        foreach ($campos as $c) {
            $clave       = (string) $c['clave'];
            $post[$clave] = $this->request->getPost($clave);
            if ((string) $c['tipo'] === 'file') {
                $files[$clave] = $this->request->getFile($clave);
            }
        }

        try {
            $result = (new SocioFormService())->completeFill(
                $plantilla,
                $campos,
                $titulo,
                SocioFormService::ORIGEN_SOCIO,
                (string) $memb['usuario_id'],
                (string) $memb['usuario_id'],
                null,
                $post,
                $files
            );
        } catch (RuntimeException $e) {
            return redirect()->to('/socios/formularios/' . $id . '/rellenar')->with('error', $e->getMessage());
        }

        return redirect()->to('/documentos/' . $result['documento_id'])
            ->with('success', 'Formulario completado. PDF guardado en documentos de socios.');
    }

    public function share(string $id)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null || (int) $plantilla['activo'] !== 1) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no disponible.');
        }

        $campos = SocioFormService::camposOrdenados($id);
        if ($campos === []) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Agrega campos antes de compartir.');
        }

        helper('id');
        $maxUsos = (int) $this->request->getPost('max_usos');
        if ($maxUsos < 1) {
            $maxUsos = 1;
        }
        if ($maxUsos > 50) {
            $maxUsos = 50;
        }

        $horas = (int) $this->request->getPost('horas_vigencia');
        $expira = null;
        if ($horas > 0) {
            $expira = date('Y-m-d H:i:s', time() + ($horas * 3600));
        }

        $token    = bin2hex(random_bytes(16));
        $password = self::mintPassword();
        $shareId  = new_id();

        model(SocioFormShareModel::class)->insert([
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

        $url = site_url('f/' . $token);
        session()->setFlashdata('share_creds', [
            'url'      => $url,
            'password' => $password,
            'expira'   => $expira,
            'max_usos' => $maxUsos,
        ]);

        return redirect()->to('/socios/formularios/' . $id)
            ->with('success', 'Enlace público generado. Copia la contraseña ahora: solo se muestra una vez.');
    }

    public function revokeShare(string $id, string $shareId)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $plantilla = SocioFormService::plantillaOfEmpresa($id, $memb['empresa_id']);
        if ($plantilla === null) {
            return redirect()->to('/socios/formularios')->with('error', 'Plantilla no encontrada.');
        }

        $share = model(SocioFormShareModel::class)->find($shareId);
        if (! is_array($share) || (string) $share['plantilla_id'] !== $id) {
            return redirect()->to('/socios/formularios/' . $id)->with('error', 'Enlace no encontrado.');
        }

        model(SocioFormShareModel::class)->update($shareId, [
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/socios/formularios/' . $id)->with('success', 'Enlace revocado.');
    }

    public function showInstancia(string $instanciaId)
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        $inst = model(SocioFormInstanciaModel::class)->find($instanciaId);
        if (! is_array($inst) || (string) $inst['empresa_id'] !== $memb['empresa_id']) {
            return redirect()->to('/socios/formularios')->with('error', 'Instancia no encontrada.');
        }

        $plantilla = SocioFormService::plantillaOfEmpresa((string) $inst['plantilla_id'], $memb['empresa_id']);
        $campos    = SocioFormService::camposOrdenados((string) $inst['plantilla_id']);
        $respuestas = json_decode((string) ($inst['respuestas_json'] ?? '{}'), true);
        if (! is_array($respuestas)) {
            $respuestas = [];
        }

        return view('socios/formularios_instancia', [
            'title'      => 'Formularios socios',
            'memb'       => $memb,
            'instancia'  => $inst,
            'plantilla'  => $plantilla,
            'campos'     => $campos,
            'respuestas' => $respuestas,
        ]);
    }

    private static function mintPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out      = '';
        $len      = strlen($alphabet);
        for ($i = 0; $i < 10; $i++) {
            $out .= $alphabet[random_int(0, $len - 1)];
        }

        return $out;
    }
}
