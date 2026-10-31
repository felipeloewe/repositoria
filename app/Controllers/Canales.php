<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentStorage;
use App\Libraries\FlujosAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DocumentoAutorizacionModel;
use App\Models\DocumentoModel;

class Canales extends BaseController
{
    /** @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse */
    private function requireMemb()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }

        return $memb;
    }

    private function audit(array $memb, string $paqueteId, string $accion, ?string $detalle = null): void
    {
        helper('id');
        db_connect()->table('canal_paquete_auditoria')->insert([
            'id'         => new_id(),
            'empresa_id' => $memb['empresa_id'],
            'paquete_id' => $paqueteId,
            'actor_id'   => $memb['usuario_id'],
            'accion'     => $accion,
            'detalle'    => $detalle,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function index()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $q = db_connect()->table('canal_paquetes p')
            ->select('p.*, u.nombre AS destinatario_nombre, u.email AS destinatario_email')
            ->join('usuarios u', 'u.id = p.destinatario_id')
            ->where('p.empresa_id', $memb['empresa_id']);

        if (Roles::isExternal($memb['rol'])) {
            $q->where('p.destinatario_id', $memb['usuario_id'])->where('p.estado', 'ACTIVO');
        } elseif (! FlujosAcl::canManageCanalPaquetes($memb)) {
            return redirect()->to('/flujos')->with('error', 'Sin acceso a canales.');
        }

        $items = $q->orderBy('p.created_at', 'DESC')->get()->getResultArray();

        return view('flujos/canales_list', [
            'title'      => 'Flujos',
            'memb'       => $memb,
            'items'      => $items,
            'canManage'  => FlujosAcl::canManageCanalPaquetes($memb),
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function createForm()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canManageCanalPaquetes($memb)) {
            return redirect()->to('/flujos/canales')->with('error', 'Solo Master gestiona canales.');
        }

        $externals = db_connect()->table('membresias m')
            ->select('u.id, u.nombre, u.email, m.rol')
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->where('m.empresa_id', $memb['empresa_id'])
            ->where('m.activa', 1)
            ->whereIn('m.rol', [Roles::CONTADOR, Roles::ABOGADO, Roles::PREVENCIONISTA])
            ->orderBy('u.nombre')
            ->get()->getResultArray();

        $docs = model(DocumentoModel::class)->where('empresa_id', $memb['empresa_id'])->orderBy('titulo')->findAll();

        return view('flujos/canales_form', [
            'title'     => 'Flujos',
            'memb'      => $memb,
            'externals' => $externals,
            'docs'      => $docs,
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canManageCanalPaquetes($memb)) {
            return redirect()->to('/flujos/canales')->with('error', 'Sin permiso.');
        }

        helper('id');
        $titulo = trim((string) $this->request->getPost('titulo'));
        $desc   = trim((string) $this->request->getPost('descripcion')) ?: null;
        $dest   = (string) $this->request->getPost('destinatario_id');
        $docIds = array_values(array_filter(array_map('strval', (array) $this->request->getPost('documento_id'))));

        if ($titulo === '' || $dest === '' || $docIds === []) {
            return redirect()->to('/flujos/canales/nuevo')->with('error', 'Datos incompletos.');
        }

        $membDest = db_connect()->table('membresias')
            ->where('empresa_id', $memb['empresa_id'])
            ->where('usuario_id', $dest)
            ->where('activa', 1)
            ->get()->getRowArray();
        if ($membDest === null || ! DocumentAcl::isExternal($membDest['rol'])) {
            return redirect()->to('/flujos/canales/nuevo')->with('error', 'Destinatario debe ser externo de esta empresa.');
        }

        $id  = new_id();
        $now = date('Y-m-d H:i:s');
        db_connect()->table('canal_paquetes')->insert([
            'id'              => $id,
            'empresa_id'      => $memb['empresa_id'],
            'titulo'          => $titulo,
            'descripcion'     => $desc,
            'destinatario_id' => $dest,
            'creado_por_id'   => $memb['usuario_id'],
            'estado'          => 'ACTIVO',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        foreach ($docIds as $did) {
            db_connect()->table('canal_paquete_documentos')->insert([
                'id'           => new_id(),
                'paquete_id'   => $id,
                'documento_id' => $did,
            ]);
        }
        $this->audit($memb, $id, 'CREATED', $titulo);

        return redirect()->to('/flujos/canales/' . $id)->with('success', 'Paquete creado.');
    }

    public function show(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $paq = db_connect()->table('canal_paquetes p')
            ->select('p.*, u.nombre AS destinatario_nombre, u.email AS destinatario_email')
            ->join('usuarios u', 'u.id = p.destinatario_id')
            ->where('p.id', $id)
            ->get()->getRowArray();

        if ($paq === null || ! FlujosAcl::canViewCanalPaquete($memb, $paq)) {
            return redirect()->to('/flujos/canales')->with('error', 'Paquete no disponible.');
        }

        $docs = db_connect()->table('canal_paquete_documentos cpd')
            ->select('d.*')
            ->join('documentos d', 'd.id = cpd.documento_id')
            ->where('cpd.paquete_id', $id)
            ->get()->getResultArray();

        $audit = db_connect()->table('canal_paquete_auditoria a')
            ->select('a.*, u.nombre AS actor_nombre')
            ->join('usuarios u', 'u.id = a.actor_id')
            ->where('a.paquete_id', $id)
            ->orderBy('a.created_at', 'ASC')
            ->get()->getResultArray();

        return view('flujos/canales_show', [
            'title'     => 'Flujos',
            'memb'      => $memb,
            'paq'       => $paq,
            'docs'      => $docs,
            'audit'     => $audit,
            'canManage' => FlujosAcl::canManageCanalPaquetes($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function revoke(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canManageCanalPaquetes($memb)) {
            return redirect()->to('/flujos/canales/' . $id)->with('error', 'Sin permiso.');
        }

        $paq = db_connect()->table('canal_paquetes')->where('id', $id)->get()->getRowArray();
        if ($paq === null || $paq['empresa_id'] !== $memb['empresa_id']) {
            return redirect()->to('/flujos/canales')->with('error', 'No encontrado.');
        }

        db_connect()->table('canal_paquetes')->where('id', $id)->update([
            'estado'     => 'REVOCADO',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit($memb, $id, 'REVOKED');

        return redirect()->to('/flujos/canales/' . $id)->with('success', 'Paquete revocado.');
    }

    public function download(string $id, string $documentoId)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $paq = db_connect()->table('canal_paquetes')->where('id', $id)->get()->getRowArray();
        if ($paq === null || ! FlujosAcl::canViewCanalPaquete($memb, $paq)) {
            return redirect()->to('/flujos/canales')->with('error', 'Sin acceso.');
        }

        $link = db_connect()->table('canal_paquete_documentos')
            ->where('paquete_id', $id)
            ->where('documento_id', $documentoId)
            ->get()->getRowArray();
        if ($link === null) {
            return redirect()->to('/flujos/canales/' . $id)->with('error', 'Documento no está en el paquete.');
        }

        $doc = model(DocumentoModel::class)->find($documentoId);
        if ($doc === null) {
            return redirect()->to('/flujos/canales/' . $id)->with('error', 'Documento no encontrado.');
        }

        try {
            $data = DocumentStorage::read((string) $doc['ruta_relativa']);
        } catch (\Throwable) {
            return redirect()->to('/flujos/canales/' . $id)->with('error', 'Archivo no disponible.');
        }

        $this->audit($memb, $id, 'DOWNLOAD', $doc['titulo'] ?? $documentoId);

        return $this->response
            ->setHeader('Content-Type', (string) ($doc['mime'] ?: 'application/octet-stream'))
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', (string) $doc['nombre_archivo']) . '"'
            )
            ->setBody($data);
    }
}
