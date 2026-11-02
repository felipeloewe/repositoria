<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentLifecycle;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DocumentoTipoModel;

/**
 * Mantenedor de tipos de documento (borrador | oficial) — Master.
 */
class TiposDocumento extends BaseController
{
    /** @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse */
    private function requireMaster()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! Roles::isMaster($memb['rol'])) {
            return redirect()->to('/documentos')->with('error', 'Solo Master gestiona tipos de documento.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return view('documentos/tipos', [
            'title'   => 'Tipos de documento',
            'memb'    => $memb,
            'tipos'   => $tipos,
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $nombre     = trim((string) $this->request->getPost('nombre'));
        $codigo     = strtoupper(trim((string) $this->request->getPost('codigo')));
        $modalidad  = trim((string) $this->request->getPost('modalidad'));
        $descripcion = trim((string) $this->request->getPost('descripcion')) ?: null;

        if ($nombre === '' || $codigo === '') {
            return redirect()->to('/documentos/tipos')->with('error', 'Nombre y código son obligatorios.');
        }
        if (! in_array($modalidad, [DocumentLifecycle::MODALIDAD_BORRADOR, DocumentLifecycle::MODALIDAD_OFICIAL], true)) {
            return redirect()->to('/documentos/tipos')->with('error', 'Modalidad inválida.');
        }

        $exists = model(DocumentoTipoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('codigo', $codigo)
            ->first();
        if ($exists !== null) {
            return redirect()->to('/documentos/tipos')->with('error', 'Ya existe un tipo con ese código.');
        }

        model(DocumentoTipoModel::class)->insert([
            'id'          => new_id(),
            'empresa_id'  => $memb['empresa_id'],
            'nombre'      => $nombre,
            'codigo'      => $codigo,
            'modalidad'   => $modalidad,
            'descripcion' => $descripcion,
            'activo'      => 1,
        ]);

        return redirect()->to('/documentos/tipos')->with('success', 'Tipo de documento creado.');
    }

    public function toggle(string $id)
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $tipo = model(DocumentoTipoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($tipo === null) {
            return redirect()->to('/documentos/tipos')->with('error', 'Tipo no encontrado.');
        }

        $nuevo = ((int) $tipo['activo'] === 1) ? 0 : 1;
        model(DocumentoTipoModel::class)->update($id, ['activo' => $nuevo]);

        return redirect()->to('/documentos/tipos')->with(
            'success',
            $nuevo === 1 ? 'Tipo activado.' : 'Tipo desactivado.'
        );
    }
}
