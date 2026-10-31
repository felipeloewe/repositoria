<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentStorage;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoAutorizacionModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoSeccionModel;
use App\Models\SucursalModel;
use App\Models\UsuarioModel;

/**
 * Fase 2 — repositorio documental por empresa, ligado a Departamentos.
 */
class Documentos extends BaseController
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireMemb()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $filterDepto    = trim((string) $this->request->getGet('departamento_id'));
        $filterAmbito   = trim((string) $this->request->getGet('ambito')); // all|depto|sucursal
        $filterSeccion  = trim((string) $this->request->getGet('seccion_id'));

        $deptos = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $sucursales = model(SucursalModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $secciones = model(DocumentoSeccionModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $docs = model(DocumentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $authRows = model(DocumentoAutorizacionModel::class)
            ->where('empresa_id', $empresaId)
            ->findAll();

        $authByDoc = [];
        foreach ($authRows as $a) {
            $authByDoc[$a['documento_id']][] = $a['usuario_id'];
        }

        $deptoMap = [];
        foreach ($deptos as $d) {
            $deptoMap[$d['id']] = $d['nombre'];
        }
        $sucMap = [];
        foreach ($sucursales as $s) {
            $sucMap[$s['id']] = $s['nombre'];
        }
        $secMap = [];
        foreach ($secciones as $s) {
            $secMap[$s['id']] = $s;
        }

        $visible = [];
        foreach ($docs as $doc) {
            $aclDoc = [
                'empresa_id'              => $doc['empresa_id'],
                'departamento_id'         => $doc['departamento_id'],
                'sucursal_id'             => $doc['sucursal_id'],
                'autorizado_usuario_ids'  => $authByDoc[$doc['id']] ?? [],
            ];
            if (! DocumentAcl::canView($memb, $aclDoc)) {
                continue;
            }
            if ($filterDepto !== '' && $doc['departamento_id'] !== $filterDepto) {
                continue;
            }
            if ($filterSeccion !== '' && ($doc['seccion_id'] ?? '') !== $filterSeccion) {
                continue;
            }
            if ($filterAmbito === 'depto' && ($doc['sucursal_id'] !== null && $doc['sucursal_id'] !== '')) {
                continue;
            }
            if ($filterAmbito === 'sucursal' && ($doc['sucursal_id'] === null || $doc['sucursal_id'] === '')) {
                continue;
            }

            $doc['autorizado_usuario_ids'] = $aclDoc['autorizado_usuario_ids'];
            $doc['can_delete']             = DocumentAcl::canDelete($memb, $aclDoc);
            $doc['depto_nombre']           = $deptoMap[$doc['departamento_id']] ?? '—';
            $doc['sucursal_nombre']        = $doc['sucursal_id'] ? ($sucMap[$doc['sucursal_id']] ?? '—') : null;
            $doc['seccion_nombre']         = $secMap[$doc['seccion_id'] ?? '']['nombre'] ?? '—';
            $visible[]                     = $doc;
        }

        $uploadableSections = [];
        foreach ($secciones as $sec) {
            if (DocumentAcl::canUpload($memb, [
                'sucursal_id'     => $sec['sucursal_id'],
                'departamento_id' => $sec['departamento_id'],
            ])) {
                $label = ($deptoMap[$sec['departamento_id']] ?? '?')
                    . ' · ' . $sec['nombre']
                    . ' · ' . ($sec['sucursal_id'] ? ($sucMap[$sec['sucursal_id']] ?? 'Sucursal') : 'Nivel departamento');
                $uploadableSections[] = ['id' => $sec['id'], 'label' => $label];
            }
        }

        $externals = [];
        if (DocumentAcl::canAuthorize($memb)) {
            $externals = $this->dbExternals($empresaId);
        }

        return view('documentos/index', [
            'title'              => 'Documentos',
            'memb'               => $memb,
            'docs'               => $visible,
            'deptos'             => $deptos,
            'sucursales'         => $sucursales,
            'secciones'          => $secciones,
            'sucMap'             => $sucMap,
            'deptoMap'           => $deptoMap,
            'uploadableSections' => $uploadableSections,
            'canUpload'          => Roles::canUploadDocs($memb['rol']) && $uploadableSections !== [],
            'canAuthorize'       => DocumentAcl::canAuthorize($memb),
            'canManageSections'  => Roles::isMaster($memb['rol']),
            'externals'          => $externals,
            'filters'            => [
                'departamento_id' => $filterDepto,
                'ambito'          => $filterAmbito,
                'seccion_id'      => $filterSeccion,
            ],
            'success'            => session()->getFlashdata('success'),
            'error'              => session()->getFlashdata('error'),
        ]);
    }

    public function storeSeccion()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! Roles::isMaster($memb['rol'])) {
            return redirect()->to('/documentos')->with('error', 'Solo Master crea secciones.');
        }

        helper('id');
        $nombre = trim((string) $this->request->getPost('nombre'));
        $codigo = strtoupper(trim((string) $this->request->getPost('codigo')));
        $depto  = (string) $this->request->getPost('departamento_id');
        $suc    = trim((string) $this->request->getPost('sucursal_id')) ?: null;

        $deptoRow = model(DepartamentoModel::class)
            ->where('id', $depto)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();

        if ($nombre === '' || $codigo === '' || $deptoRow === null) {
            return redirect()->to('/documentos')->with('error', 'Sección inválida.');
        }

        if ($suc !== null) {
            $sucRow = model(SucursalModel::class)
                ->where('id', $suc)
                ->where('empresa_id', $memb['empresa_id'])
                ->where('departamento_id', $depto)
                ->first();
            if ($sucRow === null) {
                return redirect()->to('/documentos')->with('error', 'Sucursal no pertenece al departamento.');
            }
        }

        model(DocumentoSeccionModel::class)->insert([
            'id'              => new_id(),
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $depto,
            'sucursal_id'     => $suc,
            'nombre'          => $nombre,
            'codigo'          => $codigo,
        ]);

        return redirect()->to('/documentos')->with('success', 'Sección creada.');
    }

    public function upload()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $titulo    = trim((string) $this->request->getPost('titulo'));
        $seccionId = (string) $this->request->getPost('seccion_id');
        $file      = $this->request->getFile('archivo');

        if ($titulo === '' || $seccionId === '') {
            return redirect()->to('/documentos')->with('error', 'Título y sección son obligatorios.');
        }
        if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return redirect()->to('/documentos')->with('error', 'Selecciona un archivo válido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return redirect()->to('/documentos')->with('error', 'El archivo supera el límite de 15 MB.');
        }

        $seccion = model(DocumentoSeccionModel::class)
            ->where('id', $seccionId)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($seccion === null) {
            return redirect()->to('/documentos')->with('error', 'Sección no válida.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $seccion['sucursal_id'],
            'departamento_id' => $seccion['departamento_id'],
        ])) {
            return redirect()->to('/documentos')->with('error', 'Sin permiso para subir en esta sección.');
        }

        $docId   = new_id();
        $binary  = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to('/documentos')->with('error', 'No se pudo leer el archivo.');
        }

        $docs = model(DocumentoModel::class);
        $docs->insert([
            'id'              => $docId,
            'empresa_id'      => $memb['empresa_id'],
            'departamento_id' => $seccion['departamento_id'],
            'sucursal_id'     => $seccion['sucursal_id'],
            'seccion_id'      => $seccion['id'],
            'titulo'          => $titulo,
            'nombre_archivo'  => $file->getClientName(),
            'ruta_relativa'   => 'pending',
            'mime'            => $file->getMimeType() ?: 'application/octet-stream',
            'tamano_bytes'    => $file->getSize(),
            'subido_por_id'   => $memb['usuario_id'],
        ]);

        try {
            $saved = DocumentStorage::save(
                $memb['empresa_id'],
                $docId,
                $file->getClientName(),
                $binary
            );
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            log_message('error', 'Document upload failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/documentos')->with('error', 'No se pudo guardar el archivo.');
        }

        return redirect()->to('/documentos')->with('success', 'Documento subido.');
    }

    public function download(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $doc = model(DocumentoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($doc === null) {
            return redirect()->to('/documentos')->with('error', 'Documento no encontrado.');
        }

        $authIds = array_column(
            model(DocumentoAutorizacionModel::class)
                ->where('documento_id', $id)
                ->findAll(),
            'usuario_id'
        );

        $aclDoc = [
            'empresa_id'             => $doc['empresa_id'],
            'departamento_id'        => $doc['departamento_id'],
            'sucursal_id'            => $doc['sucursal_id'],
            'autorizado_usuario_ids' => $authIds,
        ];

        if (! DocumentAcl::canView($memb, $aclDoc)) {
            return redirect()->to('/documentos')->with('error', 'Sin acceso a este documento.');
        }

        try {
            $data = DocumentStorage::read((string) $doc['ruta_relativa']);
        } catch (\Throwable) {
            return redirect()->to('/documentos')->with('error', 'Archivo no disponible en disco.');
        }

        return $this->response
            ->setHeader('Content-Type', (string) ($doc['mime'] ?: 'application/octet-stream'))
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', (string) $doc['nombre_archivo']) . '"'
            )
            ->setBody($data);
    }

    public function delete(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $doc = model(DocumentoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($doc === null) {
            return redirect()->to('/documentos')->with('error', 'Documento no encontrado.');
        }

        $authIds = array_column(
            model(DocumentoAutorizacionModel::class)
                ->where('documento_id', $id)
                ->findAll(),
            'usuario_id'
        );

        $aclDoc = [
            'empresa_id'             => $doc['empresa_id'],
            'departamento_id'        => $doc['departamento_id'],
            'sucursal_id'            => $doc['sucursal_id'],
            'autorizado_usuario_ids' => $authIds,
        ];

        if (! DocumentAcl::canDelete($memb, $aclDoc)) {
            return redirect()->to('/documentos')->with('error', 'Sin permiso para eliminar.');
        }

        model(DocumentoAutorizacionModel::class)->where('documento_id', $id)->delete();
        model(DocumentoModel::class)->delete($id);
        DocumentStorage::delete((string) $doc['ruta_relativa']);

        return redirect()->to('/documentos')->with('success', 'Documento eliminado.');
    }

    public function authorize()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! DocumentAcl::canAuthorize($memb)) {
            return redirect()->to('/documentos')->with('error', 'Solo Master autoriza externos.');
        }

        helper('id');
        $documentoId = (string) $this->request->getPost('documento_id');
        $usuarioId   = (string) $this->request->getPost('usuario_id');

        $doc = model(DocumentoModel::class)
            ->where('id', $documentoId)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($doc === null || $usuarioId === '') {
            return redirect()->to('/documentos')->with('error', 'Autorización inválida.');
        }

        $exists = model(DocumentoAutorizacionModel::class)
            ->where('documento_id', $documentoId)
            ->where('usuario_id', $usuarioId)
            ->first();

        if ($exists === null) {
            model(DocumentoAutorizacionModel::class)->insert([
                'id'           => new_id(),
                'empresa_id'   => $memb['empresa_id'],
                'documento_id' => $documentoId,
                'usuario_id'   => $usuarioId,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return redirect()->to('/documentos')->with('success', 'Autorización registrada.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dbExternals(string $empresaId): array
    {
        return db_connect()->table('membresias m')
            ->select('u.id, u.nombre, u.email, m.rol')
            ->join('usuarios u', 'u.id = m.usuario_id')
            ->where('m.empresa_id', $empresaId)
            ->where('m.activa', 1)
            ->whereIn('m.rol', [Roles::CONTADOR, Roles::ABOGADO, Roles::PREVENCIONISTA])
            ->orderBy('u.nombre', 'ASC')
            ->get()
            ->getResultArray();
    }
}
