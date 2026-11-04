<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Libraries\RecursoAgrupacionCatalogo;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoEstadoHistorialModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use RuntimeException;

/**
 * Administración → Recursos: tabs Servicios | Contratos.
 */
class Recursos extends BaseController
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireMaster()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! Roles::canManageOrg($memb['rol'])) {
            return redirect()->to('/dashboard')->with('error', 'Solo Administrador Master gestiona Recursos.');
        }

        return $memb;
    }

    /** Tab Servicios (catálogo + checkboxes). */
    public function index()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        RecursoAgrupacionCatalogo::ensureForEmpresa($empresaId);

        return view('recursos/index', [
            'title'        => 'Recursos',
            'memb'         => $memb,
            'agrupaciones' => RecursoAgrupacionCatalogo::treeForEmpresa($empresaId),
            'success'      => session()->getFlashdata('success'),
            'error'        => session()->getFlashdata('error'),
        ]);
    }

    public function toggleAgrupacion()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $agrupacionId = trim((string) $this->request->getPost('agrupacion_id'));
        $raw          = $this->request->getPost('marcado');
        $marcado      = $raw === '1' || $raw === 1 || $raw === true;

        if ($agrupacionId === '' || ! RecursoAgrupacionCatalogo::setAgrupacionMarcada((string) $memb['empresa_id'], $agrupacionId, (bool) $marcado)) {
            return redirect()->to('/recursos')->with('error', 'Agrupación no válida.');
        }

        return redirect()->to('/recursos')->with(
            'success',
            $marcado ? 'Agrupación marcada para esta empresa.' : 'Agrupación desmarcada.'
        );
    }

    public function toggleEntidad()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $entidadId = trim((string) $this->request->getPost('entidad_id'));
        $raw       = $this->request->getPost('marcado');
        $marcado   = $raw === '1' || $raw === 1 || $raw === true;

        if ($entidadId === '' || ! RecursoAgrupacionCatalogo::setEntidadMarcada((string) $memb['empresa_id'], $entidadId, (bool) $marcado)) {
            return redirect()->to('/recursos')->with('error', 'Entidad no válida.');
        }

        return redirect()->to('/recursos')->with(
            'success',
            $marcado ? 'Entidad marcada (contratada / vigente).' : 'Entidad desmarcada.'
        );
    }

    /** Tab Contratos: entidades marcadas + asignar/subir. */
    public function contratos()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = (string) $memb['empresa_id'];
        RecursoAgrupacionCatalogo::ensureForEmpresa($empresaId);

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $allContratos = model(DocumentoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('ambito', DocumentAcl::AMBITO_CONTRATO)
            ->orderBy('titulo', 'ASC')
            ->findAll();

        $entidades = RecursoAgrupacionCatalogo::listEntidadesMarcadasConContratos($empresaId);

        return view('recursos/contratos', [
            'title'        => 'Recursos · Contratos',
            'memb'         => $memb,
            'entidades'    => $entidades,
            'tiposActivos' => $tipos,
            'contratosPool'=> $allContratos,
            'canUpload'    => DocumentAcl::canUpload($memb, ['ambito' => DocumentAcl::AMBITO_CONTRATO]) && $tipos !== [],
            'success'      => session()->getFlashdata('success'),
            'error'        => session()->getFlashdata('error'),
            'focus'        => trim((string) $this->request->getGet('entidad')),
        ]);
    }

    public function assignContrato()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId   = (string) $memb['empresa_id'];
        $entidadId   = trim((string) $this->request->getPost('entidad_id'));
        $documentoId = trim((string) $this->request->getPost('documento_id'));

        if ($entidadId === '' || $documentoId === '') {
            return redirect()->to('/recursos/contratos')->with('error', 'Selecciona entidad y contrato.');
        }
        if (! RecursoAgrupacionCatalogo::linkContrato($empresaId, $entidadId, $documentoId)) {
            return redirect()->to('/recursos/contratos?entidad=' . rawurlencode($entidadId))
                ->with('error', 'No se pudo asignar (entidad no marcada o contrato inválido).');
        }

        return redirect()->to('/recursos/contratos?entidad=' . rawurlencode($entidadId))
            ->with('success', 'Contrato asignado a la entidad.');
    }

    public function unassignContrato()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId   = (string) $memb['empresa_id'];
        $entidadId   = trim((string) $this->request->getPost('entidad_id'));
        $documentoId = trim((string) $this->request->getPost('documento_id'));

        RecursoAgrupacionCatalogo::unlinkContrato($empresaId, $entidadId, $documentoId);

        return redirect()->to('/recursos/contratos?entidad=' . rawurlencode($entidadId))
            ->with('success', 'Relación con el contrato eliminada (el archivo sigue en Contratos).');
    }

    public function uploadContrato()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $empresaId = (string) $memb['empresa_id'];
        $entidadId = trim((string) $this->request->getPost('entidad_id'));
        $titulo    = trim((string) $this->request->getPost('titulo'));
        $tipoId    = (string) $this->request->getPost('tipo_id');
        $file      = $this->request->getFile('archivo');
        $back      = '/recursos/contratos?entidad=' . rawurlencode($entidadId);

        if ($entidadId === '' || ! RecursoAgrupacionCatalogo::entidadMarcadaPertenece($empresaId, $entidadId)) {
            return redirect()->to('/recursos/contratos')->with('error', 'Entidad no marcada o inválida.');
        }
        if ($titulo === '' || $tipoId === '') {
            return redirect()->to($back)->with('error', 'Título y tipo son obligatorios.');
        }
        if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return redirect()->to($back)->with('error', 'Selecciona un archivo válido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return redirect()->to($back)->with('error', 'El archivo supera el límite de 15 MB.');
        }
        if (! DocumentAcl::canUpload($memb, ['ambito' => DocumentAcl::AMBITO_CONTRATO])) {
            return redirect()->to($back)->with('error', 'Sin permiso para subir contratos.');
        }

        $tipo = model(DocumentoTipoModel::class)
            ->where('id', $tipoId)
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->first();
        if ($tipo === null) {
            return redirect()->to($back)->with('error', 'Tipo de documento no válido.');
        }

        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            return redirect()->to($back)->with('error', 'La empresa necesita al menos un departamento.');
        }

        try {
            (new PlanEntitlement())->assertCanUpload($empresaId, (int) $file->getSize());
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $entNombre = '';
        $ents = RecursoAgrupacionCatalogo::listEntidadesMarcadasConContratos($empresaId);
        foreach ($ents as $row) {
            if ($row['entidad_id'] === $entidadId) {
                $entNombre = $row['entidad_nombre'];
                break;
            }
        }

        $docId  = new_id();
        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to($back)->with('error', 'No se pudo leer el archivo.');
        }

        $docs = model(DocumentoModel::class);
        $docs->insert([
            'id'                    => $docId,
            'empresa_id'            => $empresaId,
            'departamento_id'       => $depto['id'],
            'sucursal_id'           => null,
            'seccion_id'            => null,
            'ambito'                => DocumentAcl::AMBITO_CONTRATO,
            'tipo_id'               => $tipo['id'],
            'estado'                => DocumentLifecycle::EN_ELABORACION,
            'version_actual'        => 1,
            'cadena_id'             => $docId,
            'cabecera_partes'       => $entNombre !== '' ? $entNombre : null,
            'cabecera_asunto'       => null,
            'cabecera_inicio'       => null,
            'cabecera_termino'      => null,
            'cabecera_autorizantes' => null,
            'fecha_termino'         => null,
            'titulo'                => $titulo,
            'nombre_archivo'        => $file->getClientName(),
            'ruta_relativa'         => 'pending',
            'mime'                  => $file->getMimeType() ?: 'application/octet-stream',
            'tamano_bytes'          => $file->getSize(),
            'subido_por_id'         => $memb['usuario_id'],
        ]);

        try {
            $saved = DocumentStorage::save(
                $empresaId,
                $docId,
                $file->getClientName(),
                $binary,
                DocumentAcl::AMBITO_CONTRATO
            );
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            log_message('error', 'Recursos contrato upload failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to($back)->with('error', 'No se pudo guardar el archivo.');
        }

        $doc = $docs->find($docId);
        if ($doc !== null) {
            $this->registrarVersionInicial($memb, $doc);
            if (DocumentLifecycle::isOficial($tipo)) {
                model(DocumentoEstadoHistorialModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $empresaId,
                    'documento_id' => $docId,
                    'estado_desde' => null,
                    'estado_hasta' => DocumentLifecycle::EN_ELABORACION,
                    'actor_id'     => $memb['usuario_id'],
                    'nota'         => 'Alta desde Recursos → Contratos',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            } else {
                model(DocumentoCambioModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $empresaId,
                    'documento_id' => $docId,
                    'actor_id'     => $memb['usuario_id'],
                    'resumen'      => 'Contrato creado (Recursos)',
                    'detalle'      => 'Archivo inicial: ' . $file->getClientName(),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        RecursoAgrupacionCatalogo::linkContrato($empresaId, $entidadId, $docId);

        return redirect()->to($back)->with('success', 'Contrato subido y vinculado. También aparece en el menú Contratos.');
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
            'notas'          => 'Versión inicial (recursos)',
            'creado_por_id'  => $memb['usuario_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
