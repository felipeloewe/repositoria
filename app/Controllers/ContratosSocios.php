<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Models\DepartamentoModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoEstadoHistorialModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use RuntimeException;

/**
 * Socios → Contratos — primera instancia: clon de Documentos de socios.
 * Pool aislado ambito=socio_contrato. Detalle reusa /documentos/{id}.
 */
class ContratosSocios extends BaseController
{
    private const MAX_BYTES = 15 * 1024 * 1024;

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

        $empresaId    = $memb['empresa_id'];
        $filterTipo   = trim((string) $this->request->getGet('tipo_id'));
        $filterEstado = trim((string) $this->request->getGet('estado'));

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('nombre', 'ASC')
            ->findAll();
        $tiposActivos = array_values(array_filter(
            $tipos,
            static fn (array $t): bool => (int) $t['activo'] === 1
        ));
        $tipoMap = [];
        foreach ($tipos as $t) {
            $tipoMap[$t['id']] = $t;
        }

        $docs = model(DocumentoModel::class)
            ->where('empresa_id', $empresaId)
            ->where('ambito', DocumentAcl::AMBITO_SOCIO_CONTRATO)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $visible = [];
        foreach ($docs as $doc) {
            $aclDoc = [
                'empresa_id'             => $doc['empresa_id'],
                'departamento_id'        => $doc['departamento_id'],
                'sucursal_id'            => $doc['sucursal_id'],
                'ambito'                 => DocumentAcl::AMBITO_SOCIO_CONTRATO,
                'autorizado_usuario_ids' => [],
            ];
            if (! DocumentAcl::canView($memb, $aclDoc)) {
                continue;
            }
            if ($filterTipo !== '' && ($doc['tipo_id'] ?? '') !== $filterTipo) {
                continue;
            }
            if ($filterEstado !== '' && ($doc['estado'] ?? '') !== $filterEstado) {
                continue;
            }
            if ($filterEstado === '' && DocumentLifecycle::normalizarEstado($doc['estado'] ?? null) === DocumentLifecycle::ARCHIVADO) {
                continue;
            }

            $tipo = $tipoMap[$doc['tipo_id'] ?? ''] ?? null;
            $doc['tipo']       = $tipo;
            $doc['can_delete'] = DocumentAcl::canDelete($memb, $aclDoc);
            $doc['por_vencer'] = DocumentLifecycle::estaPorVencer($doc);
            $doc['vencido']    = DocumentLifecycle::estaVencido($doc);
            $visible[]         = $doc;
        }

        return view('socios/contratos', [
            'title'        => 'Socios',
            'memb'         => $memb,
            'docs'         => $visible,
            'tipos'        => $tipos,
            'tiposActivos' => $tiposActivos,
            'canUpload'    => DocumentAcl::canUpload($memb, ['ambito' => DocumentAcl::AMBITO_SOCIO_CONTRATO]) && $tiposActivos !== [],
            'estados'      => DocumentLifecycle::ESTADOS,
            'filters'      => [
                'tipo_id' => $filterTipo,
                'estado'  => $filterEstado,
            ],
            'success'      => session()->getFlashdata('success'),
            'error'        => session()->getFlashdata('error'),
        ]);
    }

    public function upload()
    {
        $memb = $this->requireSocio();
        if (! is_array($memb)) {
            return $memb;
        }

        helper('id');
        $titulo   = trim((string) $this->request->getPost('titulo'));
        $tipoId   = (string) $this->request->getPost('tipo_id');
        $file     = $this->request->getFile('archivo');
        $cabecera = $this->readCabeceraFromRequest();

        if ($titulo === '' || $tipoId === '') {
            return redirect()->to('/socios/contratos')->with('error', 'Título y tipo son obligatorios.');
        }
        if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return redirect()->to('/socios/contratos')->with('error', 'Selecciona un archivo válido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return redirect()->to('/socios/contratos')->with('error', 'El archivo supera el límite de 15 MB.');
        }
        if (! DocumentAcl::canUpload($memb, ['ambito' => DocumentAcl::AMBITO_SOCIO_CONTRATO])) {
            return redirect()->to('/socios/contratos')->with('error', 'Sin permiso para subir contratos de socios.');
        }

        $tipo = model(DocumentoTipoModel::class)
            ->where('id', $tipoId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('activo', 1)
            ->first();
        if ($tipo === null) {
            return redirect()->to('/socios/contratos')->with('error', 'Tipo de documento no válido.');
        }

        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            return redirect()->to('/socios/contratos')->with('error', 'La empresa necesita al menos un departamento (Organización).');
        }

        try {
            (new PlanEntitlement())->assertCanUpload($memb['empresa_id'], (int) $file->getSize());
        } catch (RuntimeException $e) {
            return redirect()->to('/socios/contratos')->with('error', $e->getMessage());
        }

        $docId  = new_id();
        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to('/socios/contratos')->with('error', 'No se pudo leer el archivo.');
        }

        $docs = model(DocumentoModel::class);
        $docs->insert([
            'id'                    => $docId,
            'empresa_id'            => $memb['empresa_id'],
            'departamento_id'       => $depto['id'],
            'sucursal_id'           => null,
            'seccion_id'            => null,
            'ambito'                => DocumentAcl::AMBITO_SOCIO_CONTRATO,
            'tipo_id'               => $tipo['id'],
            'estado'                => DocumentLifecycle::EN_ELABORACION,
            'version_actual'        => 1,
            'cadena_id'             => $docId,
            'cabecera_partes'       => $cabecera['cabecera_partes'],
            'cabecera_asunto'       => $cabecera['cabecera_asunto'],
            'cabecera_inicio'       => $cabecera['cabecera_inicio'],
            'cabecera_termino'      => $cabecera['cabecera_termino'],
            'cabecera_autorizantes' => $cabecera['cabecera_autorizantes'],
            'fecha_termino'         => $cabecera['cabecera_termino'],
            'titulo'                => $titulo,
            'nombre_archivo'        => $file->getClientName(),
            'ruta_relativa'         => 'pending',
            'mime'                  => $file->getMimeType() ?: 'application/octet-stream',
            'tamano_bytes'          => $file->getSize(),
            'subido_por_id'         => $memb['usuario_id'],
        ]);

        try {
            $saved = DocumentStorage::save(
                $memb['empresa_id'],
                $docId,
                $file->getClientName(),
                $binary,
                DocumentAcl::AMBITO_SOCIO_CONTRATO
            );
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            log_message('error', 'Socio contrato upload failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/socios/contratos')->with('error', 'No se pudo guardar el archivo.');
        }

        $doc = $docs->find($docId);
        if ($doc !== null) {
            $this->registrarVersionInicial($memb, $doc);
            if (DocumentLifecycle::isOficial($tipo)) {
                $this->registrarEstado($memb, $docId, null, DocumentLifecycle::EN_ELABORACION, 'Alta contrato socio');
            } else {
                model(DocumentoCambioModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $memb['empresa_id'],
                    'documento_id' => $docId,
                    'actor_id'     => $memb['usuario_id'],
                    'resumen'      => 'Contrato socio creado',
                    'detalle'      => 'Archivo inicial: ' . $file->getClientName(),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return redirect()->to('/documentos/' . $docId)->with('success', 'Contrato de socios subido.');
    }

    /**
     * @return array{
     *   cabecera_partes: ?string,
     *   cabecera_asunto: ?string,
     *   cabecera_inicio: ?string,
     *   cabecera_termino: ?string,
     *   cabecera_autorizantes: ?string
     * }
     */
    private function readCabeceraFromRequest(): array
    {
        $trimOrNull = static function (string $v): ?string {
            $v = trim($v);

            return $v === '' ? null : $v;
        };

        return [
            'cabecera_partes'       => $trimOrNull((string) $this->request->getPost('cabecera_partes')),
            'cabecera_asunto'       => $trimOrNull((string) $this->request->getPost('cabecera_asunto')),
            'cabecera_inicio'       => $trimOrNull((string) $this->request->getPost('cabecera_inicio')),
            'cabecera_termino'      => $trimOrNull((string) $this->request->getPost('cabecera_termino')),
            'cabecera_autorizantes' => $trimOrNull((string) $this->request->getPost('cabecera_autorizantes')),
        ];
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
            'notas'          => 'Versión inicial (contratos socios)',
            'creado_por_id'  => $memb['usuario_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
