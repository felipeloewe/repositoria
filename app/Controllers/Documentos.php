<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoAutorizacionModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoEstadoHistorialModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoSeccionModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use App\Models\SucursalModel;
use RuntimeException;

/**
 * Fase 2 — repositorio documental + ciclo de vida / tipos.
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

    /**
     * @return array{memb: array<string, mixed>, doc: array<string, mixed>, authIds: list<string>, aclDoc: array<string, mixed>}|null|\CodeIgniter\HTTP\RedirectResponse
     */
    private function loadDocForAcl(array $memb, string $id, bool $needView = true)
    {
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

        if ($needView && ! DocumentAcl::canView($memb, $aclDoc)) {
            return redirect()->to('/documentos')->with('error', 'Sin acceso a este documento.');
        }

        return [
            'memb'    => $memb,
            'doc'     => $doc,
            'authIds' => $authIds,
            'aclDoc'  => $aclDoc,
        ];
    }

    private function tipoOf(?string $tipoId): ?array
    {
        if ($tipoId === null || $tipoId === '') {
            return null;
        }

        return model(DocumentoTipoModel::class)->find($tipoId);
    }

    private function registrarEstado(array $memb, string $docId, ?string $desde, string $hasta, ?string $nota = null): void
    {
        helper('id');
        model(DocumentoEstadoHistorialModel::class)->insert([
            'id'           => new_id(),
            'empresa_id'   => $memb['empresa_id'],
            'documento_id' => $docId,
            'estado_desde' => $desde,
            'estado_hasta' => $hasta,
            'actor_id'     => $memb['usuario_id'],
            'nota'         => $nota,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function registrarVersionInicial(array $memb, array $doc): void
    {
        helper('id');
        $exists = model(DocumentoVersionModel::class)
            ->where('documento_id', $doc['id'])
            ->where('version_num', 1)
            ->first();
        if ($exists !== null) {
            return;
        }

        model(DocumentoVersionModel::class)->insert([
            'id'             => new_id(),
            'empresa_id'     => $memb['empresa_id'],
            'documento_id'   => $doc['id'],
            'version_num'    => 1,
            'nombre_archivo' => $doc['nombre_archivo'],
            'ruta_relativa'  => $doc['ruta_relativa'],
            'mime'           => $doc['mime'],
            'tamano_bytes'   => $doc['tamano_bytes'],
            'notas'          => 'Versión inicial',
            'creado_por_id'  => $memb['usuario_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function index()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId     = $memb['empresa_id'];
        $filterDepto   = trim((string) $this->request->getGet('departamento_id'));
        $filterAmbito  = trim((string) $this->request->getGet('ambito'));
        $filterSeccion = trim((string) $this->request->getGet('seccion_id'));
        $filterTipo    = trim((string) $this->request->getGet('tipo_id'));
        $filterEstado  = trim((string) $this->request->getGet('estado'));

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

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $tiposActivos = array_values(array_filter(
            $tipos,
            static fn (array $t): bool => (int) $t['activo'] === 1
        ));

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
        $tipoMap = [];
        foreach ($tipos as $t) {
            $tipoMap[$t['id']] = $t;
        }

        $visible = [];
        foreach ($docs as $doc) {
            $aclDoc = [
                'empresa_id'             => $doc['empresa_id'],
                'departamento_id'        => $doc['departamento_id'],
                'sucursal_id'            => $doc['sucursal_id'],
                'autorizado_usuario_ids' => $authByDoc[$doc['id']] ?? [],
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
            if ($filterTipo !== '' && ($doc['tipo_id'] ?? '') !== $filterTipo) {
                continue;
            }
            if ($filterEstado !== '' && ($doc['estado'] ?? '') !== $filterEstado) {
                continue;
            }
            if ($filterAmbito === 'depto' && ($doc['sucursal_id'] !== null && $doc['sucursal_id'] !== '')) {
                continue;
            }
            if ($filterAmbito === 'sucursal' && ($doc['sucursal_id'] === null || $doc['sucursal_id'] === '')) {
                continue;
            }

            $tipo = $tipoMap[$doc['tipo_id'] ?? ''] ?? null;
            $doc['autorizado_usuario_ids'] = $aclDoc['autorizado_usuario_ids'];
            $doc['can_delete']             = DocumentAcl::canDelete($memb, $aclDoc);
            $doc['depto_nombre']           = $deptoMap[$doc['departamento_id']] ?? '—';
            $doc['sucursal_nombre']        = $doc['sucursal_id'] ? ($sucMap[$doc['sucursal_id']] ?? '—') : null;
            $doc['seccion_nombre']         = $secMap[$doc['seccion_id'] ?? '']['nombre'] ?? '—';
            $doc['tipo']                   = $tipo;
            $doc['por_vencer']             = DocumentLifecycle::estaPorVencer($doc);
            $doc['vencido']                = DocumentLifecycle::estaVencido($doc);
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
            'tipos'              => $tipos,
            'tiposActivos'       => $tiposActivos,
            'sucMap'             => $sucMap,
            'deptoMap'           => $deptoMap,
            'uploadableSections' => $uploadableSections,
            'canUpload'          => Roles::canUploadDocs($memb['rol']) && $uploadableSections !== [] && $tiposActivos !== [],
            'canAuthorize'       => DocumentAcl::canAuthorize($memb),
            'canManageSections'  => Roles::isMaster($memb['rol']),
            'canManageTipos'     => Roles::isMaster($memb['rol']),
            'externals'          => $externals,
            'estados'            => DocumentLifecycle::ESTADOS,
            'filters'            => [
                'departamento_id' => $filterDepto,
                'ambito'          => $filterAmbito,
                'seccion_id'      => $filterSeccion,
                'tipo_id'         => $filterTipo,
                'estado'          => $filterEstado,
            ],
            'success'            => session()->getFlashdata('success'),
            'error'              => session()->getFlashdata('error'),
        ]);
    }

    public function show(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        $oficial = DocumentLifecycle::isOficial($tipo);

        $historial = [];
        $versiones = [];
        $cambios   = [];

        if ($oficial) {
            $historial = model(DocumentoEstadoHistorialModel::class)
                ->where('documento_id', $id)
                ->orderBy('created_at', 'DESC')
                ->findAll();
            $versiones = model(DocumentoVersionModel::class)
                ->where('documento_id', $id)
                ->orderBy('version_num', 'DESC')
                ->findAll();
        } else {
            $cambios = model(DocumentoCambioModel::class)
                ->where('documento_id', $id)
                ->orderBy('created_at', 'DESC')
                ->findAll();
        }

        $actorIds = [];
        foreach (array_merge($historial, $versiones, $cambios) as $row) {
            if (! empty($row['actor_id'])) {
                $actorIds[] = $row['actor_id'];
            }
            if (! empty($row['creado_por_id'])) {
                $actorIds[] = $row['creado_por_id'];
            }
        }
        $actorMap = [];
        if ($actorIds !== []) {
            $users = db_connect()->table('usuarios')
                ->select('id, nombre')
                ->whereIn('id', array_unique($actorIds))
                ->get()->getResultArray();
            foreach ($users as $u) {
                $actorMap[$u['id']] = $u['nombre'];
            }
        }

        $deptos = model(DepartamentoModel::class)->find($doc['departamento_id']);
        $sec    = $doc['seccion_id']
            ? model(DocumentoSeccionModel::class)->find($doc['seccion_id'])
            : null;

        $siguientes = [];
        if ($oficial) {
            foreach (DocumentLifecycle::siguientes((string) ($doc['estado'] ?? DocumentLifecycle::BORRADOR)) as $e) {
                if (DocumentLifecycle::puedeActorTransicionar($memb, $e)) {
                    $siguientes[] = $e;
                }
            }
        }

        $canMutate = DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ]);

        return view('documentos/show', [
            'title'       => 'Documentos',
            'memb'        => $memb,
            'doc'         => $doc,
            'tipo'        => $tipo,
            'oficial'     => $oficial,
            'historial'   => $historial,
            'versiones'   => $versiones,
            'cambios'     => $cambios,
            'actorMap'    => $actorMap,
            'deptoNombre' => $deptos['nombre'] ?? '—',
            'seccionNombre' => $sec['nombre'] ?? '—',
            'siguientes'  => $siguientes,
            'canMutate'   => $canMutate,
            'canDelete'   => DocumentAcl::canDelete($memb, $loaded['aclDoc']),
            'isMaster'    => Roles::isMaster($memb['rol']),
            'porVencer'   => DocumentLifecycle::estaPorVencer($doc),
            'vencido'     => DocumentLifecycle::estaVencido($doc),
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
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
        $tipoId    = (string) $this->request->getPost('tipo_id');
        $file      = $this->request->getFile('archivo');

        if ($titulo === '' || $seccionId === '' || $tipoId === '') {
            return redirect()->to('/documentos')->with('error', 'Título, tipo y sección son obligatorios.');
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

        $tipo = model(DocumentoTipoModel::class)
            ->where('id', $tipoId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('activo', 1)
            ->first();
        if ($tipo === null) {
            return redirect()->to('/documentos')->with('error', 'Tipo de documento no válido.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $seccion['sucursal_id'],
            'departamento_id' => $seccion['departamento_id'],
        ])) {
            return redirect()->to('/documentos')->with('error', 'Sin permiso para subir en esta sección.');
        }

        try {
            (new PlanEntitlement())->assertCanUpload($memb['empresa_id'], (int) $file->getSize());
        } catch (RuntimeException $e) {
            return redirect()->to('/documentos')->with('error', $e->getMessage());
        }

        $docId  = new_id();
        $binary = file_get_contents($file->getTempName());
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
            'tipo_id'         => $tipo['id'],
            'estado'          => DocumentLifecycle::BORRADOR,
            'version_actual'  => 1,
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

        $doc = $docs->find($docId);
        if ($doc !== null) {
            $this->registrarEstado($memb, $docId, null, DocumentLifecycle::BORRADOR, 'Alta del documento');
            if (DocumentLifecycle::isOficial($tipo)) {
                $this->registrarVersionInicial($memb, $doc);
            } else {
                helper('id');
                model(DocumentoCambioModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $memb['empresa_id'],
                    'documento_id' => $docId,
                    'actor_id'     => $memb['usuario_id'],
                    'resumen'      => 'Documento creado',
                    'detalle'      => 'Archivo inicial: ' . $file->getClientName(),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return redirect()->to('/documentos/' . $docId)->with('success', 'Documento subido.');
    }

    public function transition(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        if (! DocumentLifecycle::isOficial($tipo)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'El ciclo de vida aplica solo a tipos oficiales.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ])) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Sin permiso para cambiar el estado.');
        }

        $hasta = strtoupper(trim((string) $this->request->getPost('estado')));
        $nota  = trim((string) $this->request->getPost('nota')) ?: null;
        $desde = (string) ($doc['estado'] ?? DocumentLifecycle::BORRADOR);

        if (! DocumentLifecycle::puedeTransicionar($desde, $hasta)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Transición no permitida.');
        }
        if (! DocumentLifecycle::puedeActorTransicionar($memb, $hasta)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Solo Master puede publicar o archivar.');
        }

        model(DocumentoModel::class)->update($id, [
            'estado'      => $hasta,
            'notas_ciclo' => $nota,
        ]);
        $this->registrarEstado($memb, $id, $desde, $hasta, $nota);

        return redirect()->to('/documentos/' . $id)->with(
            'success',
            'Estado actualizado a «' . DocumentLifecycle::labelEstado($hasta) . '».'
        );
    }

    public function newVersion(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        if (! DocumentLifecycle::isOficial($tipo)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'El versionamiento aplica solo a tipos oficiales.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ])) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Sin permiso para subir versión.');
        }

        $estado = (string) ($doc['estado'] ?? '');
        if (! in_array($estado, [DocumentLifecycle::APROBADO, DocumentLifecycle::VIGENTE], true)) {
            return redirect()->to('/documentos/' . $id)->with(
                'error',
                'Solo se puede versionar en Aprobado o Vigente.'
            );
        }

        $file  = $this->request->getFile('archivo');
        $notas = trim((string) $this->request->getPost('notas')) ?: null;

        if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Selecciona un archivo válido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return redirect()->to('/documentos/' . $id)->with('error', 'El archivo supera el límite de 15 MB.');
        }

        try {
            (new PlanEntitlement())->assertCanUpload($memb['empresa_id'], (int) $file->getSize());
        } catch (RuntimeException $e) {
            return redirect()->to('/documentos/' . $id)->with('error', $e->getMessage());
        }

        // Asegurar que la versión actual esté en el historial
        $this->registrarVersionInicial($memb, $doc);
        $currentNum = (int) ($doc['version_actual'] ?? 1);
        $hasCurrent = model(DocumentoVersionModel::class)
            ->where('documento_id', $id)
            ->where('version_num', $currentNum)
            ->first();
        if ($hasCurrent === null) {
            helper('id');
            model(DocumentoVersionModel::class)->insert([
                'id'             => new_id(),
                'empresa_id'     => $memb['empresa_id'],
                'documento_id'   => $id,
                'version_num'    => $currentNum,
                'nombre_archivo' => $doc['nombre_archivo'],
                'ruta_relativa'  => $doc['ruta_relativa'],
                'mime'           => $doc['mime'],
                'tamano_bytes'   => $doc['tamano_bytes'],
                'notas'          => 'Snapshot previo a nueva versión',
                'creado_por_id'  => $memb['usuario_id'],
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to('/documentos/' . $id)->with('error', 'No se pudo leer el archivo.');
        }

        $newNum = $currentNum + 1;
        helper('id');
        $versionId = new_id();

        try {
            // Guardar bajo subcarpeta de versión para no pisar el blob anterior
            $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $file->getClientName()) ?: 'archivo.bin';
            $rel  = $memb['empresa_id'] . '/' . $id . '/v' . $newNum . '/' . $safe;
            $full = DocumentStorage::absolutePath($rel);
            if (file_put_contents($full, $binary) === false) {
                throw new RuntimeException('No se pudo escribir la nueva versión.');
            }

            model(DocumentoVersionModel::class)->insert([
                'id'             => $versionId,
                'empresa_id'     => $memb['empresa_id'],
                'documento_id'   => $id,
                'version_num'    => $newNum,
                'nombre_archivo' => $file->getClientName(),
                'ruta_relativa'  => $rel,
                'mime'           => $file->getMimeType() ?: 'application/octet-stream',
                'tamano_bytes'   => strlen($binary),
                'notas'          => $notas,
                'creado_por_id'  => $memb['usuario_id'],
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            model(DocumentoModel::class)->update($id, [
                'version_actual' => $newNum,
                'nombre_archivo' => $file->getClientName(),
                'ruta_relativa'  => $rel,
                'mime'           => $file->getMimeType() ?: 'application/octet-stream',
                'tamano_bytes'   => strlen($binary),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'New version failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/documentos/' . $id)->with('error', 'No se pudo guardar la nueva versión.');
        }

        return redirect()->to('/documentos/' . $id)->with('success', 'Versión v' . $newNum . ' publicada.');
    }

    public function downloadVersion(string $id, string $versionId)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $ver = model(DocumentoVersionModel::class)
            ->where('id', $versionId)
            ->where('documento_id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($ver === null) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Versión no encontrada.');
        }

        try {
            $data = DocumentStorage::read((string) $ver['ruta_relativa']);
        } catch (\Throwable) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Archivo de versión no disponible.');
        }

        return $this->response
            ->setHeader('Content-Type', (string) ($ver['mime'] ?: 'application/octet-stream'))
            ->setHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', (string) $ver['nombre_archivo']) . '"'
            )
            ->setBody($data);
    }

    public function setVencimiento(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        if (! DocumentLifecycle::isOficial($tipo)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Vencimiento solo para tipos oficiales.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ])) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Sin permiso.');
        }

        $vence = trim((string) $this->request->getPost('vence_at')) ?: null;
        $dias  = trim((string) $this->request->getPost('alerta_dias'));
        $alerta = $dias === '' ? null : max(0, (int) $dias);

        model(DocumentoModel::class)->update($id, [
            'vence_at'    => $vence,
            'alerta_dias' => $alerta,
        ]);

        return redirect()->to('/documentos/' . $id)->with('success', 'Vencimiento y alerta actualizados.');
    }

    public function setTermino(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        if (! DocumentLifecycle::isBorradorTipo($tipo)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Fecha de término solo para tipos borrador.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ])) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Sin permiso.');
        }

        $termino = trim((string) $this->request->getPost('fecha_termino')) ?: null;
        model(DocumentoModel::class)->update($id, ['fecha_termino' => $termino]);

        return redirect()->to('/documentos/' . $id)->with('success', 'Fecha de término actualizada.');
    }

    public function addCambio(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc  = $loaded['doc'];
        $tipo = $this->tipoOf($doc['tipo_id'] ?? null);
        if (! DocumentLifecycle::isBorradorTipo($tipo)) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Control de cambios solo para tipos borrador.');
        }

        if (! DocumentAcl::canUpload($memb, [
            'sucursal_id'     => $doc['sucursal_id'],
            'departamento_id' => $doc['departamento_id'],
        ])) {
            return redirect()->to('/documentos/' . $id)->with('error', 'Sin permiso.');
        }

        $resumen = trim((string) $this->request->getPost('resumen'));
        $detalle = trim((string) $this->request->getPost('detalle')) ?: null;
        if ($resumen === '') {
            return redirect()->to('/documentos/' . $id)->with('error', 'El resumen del cambio es obligatorio.');
        }

        helper('id');
        model(DocumentoCambioModel::class)->insert([
            'id'           => new_id(),
            'empresa_id'   => $memb['empresa_id'],
            'documento_id' => $id,
            'actor_id'     => $memb['usuario_id'],
            'resumen'      => $resumen,
            'detalle'      => $detalle,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/documentos/' . $id)->with('success', 'Cambio registrado.');
    }

    public function download(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $loaded = $this->loadDocForAcl($memb, $id);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc = $loaded['doc'];

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

        $loaded = $this->loadDocForAcl($memb, $id, false);
        if (! is_array($loaded) || ! isset($loaded['doc'])) {
            return $loaded;
        }

        $doc = $loaded['doc'];
        if (! DocumentAcl::canDelete($memb, $loaded['aclDoc'])) {
            return redirect()->to('/documentos')->with('error', 'Sin permiso para eliminar.');
        }

        // Borrar blobs de versiones
        $versiones = model(DocumentoVersionModel::class)->where('documento_id', $id)->findAll();
        foreach ($versiones as $v) {
            DocumentStorage::delete((string) $v['ruta_relativa']);
        }

        model(DocumentoAutorizacionModel::class)->where('documento_id', $id)->delete();
        model(DocumentoVersionModel::class)->where('documento_id', $id)->delete();
        model(DocumentoEstadoHistorialModel::class)->where('documento_id', $id)->delete();
        model(DocumentoCambioModel::class)->where('documento_id', $id)->delete();
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
