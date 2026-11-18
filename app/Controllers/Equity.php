<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\EquityAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\PlanEntitlement;
use App\Models\DepartamentoModel;
use App\Models\DocumentoCambioModel;
use App\Models\DocumentoEstadoHistorialModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use App\Models\DocumentoVersionModel;
use App\Models\EquityFichaModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityRespaldoModel;
use RuntimeException;

/**
 * Equity (equity.booklet.cl): información financiera de socios/gerentes + respaldos.
 */
class Equity extends BaseController
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireAccess()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EquityAcl::canAccess($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin acceso al módulo Equity.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $filterTipo   = EquityAcl::normalizeTipo((string) $this->request->getGet('tipo'));
        $rawTipo      = trim((string) $this->request->getGet('tipo'));
        $filterActivo = trim((string) $this->request->getGet('activo'));

        $q = model(EquityPersonaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC');

        if ($rawTipo !== '' && in_array($rawTipo, EquityAcl::TIPOS, true)) {
            $q->where('tipo', $filterTipo);
        }
        if ($filterActivo === '1' || $filterActivo === '0') {
            $q->where('activo', (int) $filterActivo);
        }

        $personas = $q->findAll();

        $fichaCounts = [];
        $respaldoCounts = [];
        if ($personas !== []) {
            $ids = array_column($personas, 'id');
            $fichas = model(EquityFichaModel::class)
                ->where('empresa_id', $memb['empresa_id'])
                ->whereIn('persona_id', $ids)
                ->findAll();
            foreach ($fichas as $f) {
                $pid = (string) $f['persona_id'];
                $fichaCounts[$pid] = ($fichaCounts[$pid] ?? 0) + 1;
            }
            $respaldos = model(EquityRespaldoModel::class)
                ->where('empresa_id', $memb['empresa_id'])
                ->whereIn('persona_id', $ids)
                ->findAll();
            foreach ($respaldos as $r) {
                $pid = (string) $r['persona_id'];
                $respaldoCounts[$pid] = ($respaldoCounts[$pid] ?? 0) + 1;
            }
        }

        foreach ($personas as &$p) {
            $p['fichas_count']    = $fichaCounts[$p['id']] ?? 0;
            $p['respaldos_count'] = $respaldoCounts[$p['id']] ?? 0;
        }
        unset($p);

        return view('equity/index', [
            'title'     => 'Equity',
            'memb'      => $memb,
            'personas'  => $personas,
            'canManage' => EquityAcl::canManage($memb),
            'tipos'     => EquityAcl::TIPOS,
            'filters'   => [
                'tipo'   => $rawTipo,
                'activo' => $filterActivo,
            ],
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function createForm()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity')->with('error', 'Sin permiso para crear personas Equity.');
        }

        return view('equity/form_persona', [
            'title'   => 'Equity',
            'memb'    => $memb,
            'persona' => null,
            'tipos'   => EquityAcl::TIPOS,
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity')->with('error', 'Sin permiso para crear personas Equity.');
        }

        helper('id');
        $data = $this->readPersonaFromRequest();
        if ($data['nombre'] === '') {
            return redirect()->to('/equity/nueva')->with('error', 'El nombre es obligatorio.');
        }

        $id = new_id();
        model(EquityPersonaModel::class)->insert([
            'id'            => $id,
            'empresa_id'    => $memb['empresa_id'],
            'tipo'          => $data['tipo'],
            'nombre'        => $data['nombre'],
            'apellido'      => $data['apellido'],
            'rut'           => $data['rut'],
            'email'         => $data['email'],
            'cargo'         => $data['cargo'],
            'usuario_id'    => null,
            'activo'        => 1,
            'notas'         => $data['notas'],
            'creado_por_id' => $memb['usuario_id'],
        ]);

        return redirect()->to('/equity/' . $id)->with('success', 'Persona Equity creada.');
    }

    public function show(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $persona = $this->findPersona($memb['empresa_id'], $id);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        $fichas = model(EquityFichaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $id)
            ->orderBy('fecha_corte', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $respaldos = model(EquityRespaldoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('persona_id', $id)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $docIds = array_values(array_filter(array_column($respaldos, 'documento_id')));
        $docsById = [];
        if ($docIds !== []) {
            $docs = model(DocumentoModel::class)->whereIn('id', $docIds)->findAll();
            foreach ($docs as $d) {
                $docsById[$d['id']] = $d;
            }
        }
        foreach ($respaldos as &$r) {
            $r['documento'] = $docsById[$r['documento_id']] ?? null;
            $r['can_delete'] = EquityAcl::canDeleteRespaldo($memb)
                && $r['documento'] !== null
                && DocumentAcl::canDelete($memb, [
                    'empresa_id'             => $memb['empresa_id'],
                    'departamento_id'        => $r['documento']['departamento_id'] ?? null,
                    'sucursal_id'            => $r['documento']['sucursal_id'] ?? null,
                    'ambito'                 => DocumentAcl::AMBITO_EQUITY,
                    'autorizado_usuario_ids' => [],
                ]);
        }
        unset($r);

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('activo', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();

        return view('equity/show', [
            'title'       => 'Equity',
            'memb'        => $memb,
            'persona'     => $persona,
            'fichas'      => $fichas,
            'respaldos'   => $respaldos,
            'tipos'       => $tipos,
            'canManage'   => EquityAcl::canManage($memb),
            'canUpload'   => EquityAcl::canUpload($memb) && $tipos !== [],
            'tiposRespaldo' => $this->tiposRespaldo(),
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function editForm(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $id)->with('error', 'Sin permiso para editar.');
        }

        $persona = $this->findPersona($memb['empresa_id'], $id);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        return view('equity/form_persona', [
            'title'   => 'Equity',
            'memb'    => $memb,
            'persona' => $persona,
            'tipos'   => EquityAcl::TIPOS,
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function update(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $id)->with('error', 'Sin permiso para editar.');
        }

        $persona = $this->findPersona($memb['empresa_id'], $id);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        $data = $this->readPersonaFromRequest();
        if ($data['nombre'] === '') {
            return redirect()->to('/equity/' . $id . '/editar')->with('error', 'El nombre es obligatorio.');
        }

        $activo = (int) $this->request->getPost('activo') === 1 ? 1 : 0;

        model(EquityPersonaModel::class)->update($id, [
            'tipo'     => $data['tipo'],
            'nombre'   => $data['nombre'],
            'apellido' => $data['apellido'],
            'rut'      => $data['rut'],
            'email'    => $data['email'],
            'cargo'    => $data['cargo'],
            'activo'   => $activo,
            'notas'    => $data['notas'],
        ]);

        return redirect()->to('/equity/' . $id)->with('success', 'Persona actualizada.');
    }

    public function fichaForm(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Sin permiso para fichas.');
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        return view('equity/ficha_form', [
            'title'   => 'Equity',
            'memb'    => $memb,
            'persona' => $persona,
            'ficha'   => null,
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function storeFicha(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Sin permiso para fichas.');
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        helper('id');
        $row = $this->readFichaFromRequest();
        $row['id']            = new_id();
        $row['empresa_id']    = $memb['empresa_id'];
        $row['persona_id']    = $personaId;
        $row['creado_por_id'] = $memb['usuario_id'];

        model(EquityFichaModel::class)->insert($row);

        return redirect()->to('/equity/' . $personaId)->with('success', 'Ficha financiera registrada.');
    }

    public function editFichaForm(string $personaId, string $fichaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Sin permiso para fichas.');
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        $ficha   = $this->findFicha($memb['empresa_id'], $personaId, $fichaId);
        if ($persona === null || $ficha === null) {
            return redirect()->to('/equity')->with('error', 'Ficha no encontrada.');
        }

        return view('equity/ficha_form', [
            'title'   => 'Equity',
            'memb'    => $memb,
            'persona' => $persona,
            'ficha'   => $ficha,
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function updateFicha(string $personaId, string $fichaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Sin permiso para fichas.');
        }

        $ficha = $this->findFicha($memb['empresa_id'], $personaId, $fichaId);
        if ($ficha === null) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Ficha no encontrada.');
        }

        $row = $this->readFichaFromRequest();
        model(EquityFichaModel::class)->update($fichaId, $row);

        return redirect()->to('/equity/' . $personaId)->with('success', 'Ficha financiera actualizada.');
    }

    public function uploadRespaldo(string $personaId)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $persona = $this->findPersona($memb['empresa_id'], $personaId);
        if ($persona === null) {
            return redirect()->to('/equity')->with('error', 'Persona no encontrada.');
        }

        helper('id');
        $titulo       = trim((string) $this->request->getPost('titulo'));
        $tipoId       = (string) $this->request->getPost('tipo_id');
        $tipoRespaldo = trim((string) $this->request->getPost('tipo_respaldo'));
        $descripcion  = trim((string) $this->request->getPost('descripcion'));
        $fichaId      = trim((string) $this->request->getPost('ficha_id'));
        $file         = $this->request->getFile('archivo');

        if ($titulo === '' || $tipoId === '') {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Título y tipo de documento son obligatorios.');
        }
        if ($file === null || ! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Selecciona un archivo válido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'El archivo supera el límite de 15 MB.');
        }
        if (! EquityAcl::canUpload($memb)) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Sin permiso para subir respaldos.');
        }

        $tipo = model(DocumentoTipoModel::class)
            ->where('id', $tipoId)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('activo', 1)
            ->first();
        if ($tipo === null) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'Tipo de documento no válido.');
        }

        $depto = model(DepartamentoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('nombre', 'ASC')
            ->first();
        if ($depto === null) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'La empresa necesita al menos un departamento.');
        }

        $fichaLink = null;
        if ($fichaId !== '') {
            $fichaLink = $this->findFicha($memb['empresa_id'], $personaId, $fichaId);
            if ($fichaLink === null) {
                return redirect()->to('/equity/' . $personaId)->with('error', 'Ficha asociada no válida.');
            }
        }

        try {
            (new PlanEntitlement())->assertCanUpload($memb['empresa_id'], (int) $file->getSize());
        } catch (RuntimeException $e) {
            return redirect()->to('/equity/' . $personaId)->with('error', $e->getMessage());
        }

        $docId  = new_id();
        $binary = file_get_contents($file->getTempName());
        if ($binary === false) {
            return redirect()->to('/equity/' . $personaId)->with('error', 'No se pudo leer el archivo.');
        }

        $docs = model(DocumentoModel::class);
        $docs->insert([
            'id'                    => $docId,
            'empresa_id'            => $memb['empresa_id'],
            'departamento_id'       => $depto['id'],
            'sucursal_id'           => null,
            'seccion_id'            => null,
            'ambito'                => DocumentAcl::AMBITO_EQUITY,
            'tipo_id'               => $tipo['id'],
            'estado'                => DocumentLifecycle::EN_ELABORACION,
            'version_actual'        => 1,
            'cadena_id'             => $docId,
            'cabecera_partes'       => trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')),
            'cabecera_asunto'       => $titulo,
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
                $memb['empresa_id'],
                $docId,
                $file->getClientName(),
                $binary,
                DocumentAcl::AMBITO_EQUITY
            );
            $docs->update($docId, [
                'ruta_relativa' => $saved['ruta_relativa'],
                'tamano_bytes'  => $saved['tamano_bytes'],
            ]);
        } catch (\Throwable $e) {
            $docs->delete($docId);
            log_message('error', 'Equity respaldo upload failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/equity/' . $personaId)->with('error', 'No se pudo guardar el archivo.');
        }

        $doc = $docs->find($docId);
        if ($doc !== null) {
            $this->registrarVersionInicial($memb, $doc);
            if (DocumentLifecycle::isOficial($tipo)) {
                model(DocumentoEstadoHistorialModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $memb['empresa_id'],
                    'documento_id' => $docId,
                    'estado_desde' => null,
                    'estado_hasta' => DocumentLifecycle::EN_ELABORACION,
                    'actor_id'     => $memb['usuario_id'],
                    'nota'         => 'Alta respaldo Equity',
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            } else {
                model(DocumentoCambioModel::class)->insert([
                    'id'           => new_id(),
                    'empresa_id'   => $memb['empresa_id'],
                    'documento_id' => $docId,
                    'actor_id'     => $memb['usuario_id'],
                    'resumen'      => 'Respaldo Equity creado',
                    'detalle'      => 'Archivo inicial: ' . $file->getClientName(),
                    'created_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        model(EquityRespaldoModel::class)->insert([
            'id'            => new_id(),
            'empresa_id'    => $memb['empresa_id'],
            'persona_id'    => $personaId,
            'ficha_id'      => $fichaLink['id'] ?? null,
            'documento_id'  => $docId,
            'tipo_respaldo' => $tipoRespaldo !== '' ? $tipoRespaldo : null,
            'descripcion'   => $descripcion !== '' ? $descripcion : null,
            'creado_por_id' => $memb['usuario_id'],
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/equity/' . $personaId)->with('success', 'Respaldo subido.');
    }

    /**
     * @return array{tipo: string, nombre: string, apellido: ?string, rut: ?string, email: ?string, cargo: ?string, notas: ?string}
     */
    private function readPersonaFromRequest(): array
    {
        $trimOrNull = static function (string $v): ?string {
            $v = trim($v);

            return $v === '' ? null : $v;
        };

        return [
            'tipo'     => EquityAcl::normalizeTipo((string) $this->request->getPost('tipo')),
            'nombre'   => trim((string) $this->request->getPost('nombre')),
            'apellido' => $trimOrNull((string) $this->request->getPost('apellido')),
            'rut'      => $trimOrNull((string) $this->request->getPost('rut')),
            'email'    => $trimOrNull((string) $this->request->getPost('email')),
            'cargo'    => $trimOrNull((string) $this->request->getPost('cargo')),
            'notas'    => $trimOrNull((string) $this->request->getPost('notas')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readFichaFromRequest(): array
    {
        $money = static function ($raw): ?string {
            $v = trim((string) $raw);
            if ($v === '') {
                return null;
            }
            // CL: 1.234.567,89 · US/simple: 1234567.89
            if (str_contains($v, ',') && str_contains($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (str_contains($v, ',')) {
                $v = str_replace(',', '.', $v);
            }
            if (! is_numeric($v)) {
                return null;
            }

            return number_format((float) $v, 2, '.', '');
        };

        $fecha = trim((string) $this->request->getPost('fecha_corte'));
        $notas = trim((string) $this->request->getPost('notas'));
        $moneda = strtoupper(trim((string) $this->request->getPost('moneda'))) ?: 'CLP';

        $ingresoFijo       = $money($this->request->getPost('ingreso_fijo'));
        $ingresoHonorarios = $money($this->request->getPost('ingreso_honorarios'));
        $ingresoArriendos  = $money($this->request->getPost('ingreso_arriendos'));
        $ingresoVariable   = $money($this->request->getPost('ingreso_variable'));
        $ingresoComisiones = $money($this->request->getPost('ingreso_comisiones'));
        $ingresoPensiones  = $money($this->request->getPost('ingreso_pensiones'));
        $ingresoBonos      = $money($this->request->getPost('ingreso_bonos'));
        $ingresoOtros      = $money($this->request->getPost('ingreso_otros'));
        $rentaLiquida      = $money($this->request->getPost('renta_liquida'));
        $activos           = $money($this->request->getPost('activos_total'));
        $pasivos           = $money($this->request->getPost('pasivos_total'));

        $parts = array_filter([
            $ingresoFijo, $ingresoHonorarios, $ingresoArriendos, $ingresoVariable,
            $ingresoComisiones, $ingresoPensiones, $ingresoBonos, $ingresoOtros,
        ], static fn ($v) => $v !== null);
        $totalIngreso = $money($this->request->getPost('ingreso_mensual_total'));
        if ($totalIngreso === null && $parts !== []) {
            $sum = 0.0;
            foreach ($parts as $p) {
                $sum += (float) $p;
            }
            $totalIngreso = number_format($sum, 2, '.', '');
        }

        $patrimonio = $money($this->request->getPost('patrimonio_neto'));
        if ($patrimonio === null && ($activos !== null || $pasivos !== null)) {
            $patrimonio = number_format(((float) ($activos ?? 0)) - ((float) ($pasivos ?? 0)), 2, '.', '');
        }

        return [
            'fecha_corte'           => $fecha !== '' ? $fecha : null,
            'moneda'                => substr($moneda, 0, 8),
            'renta_liquida'         => $rentaLiquida,
            'ingreso_fijo'          => $ingresoFijo,
            'ingreso_honorarios'    => $ingresoHonorarios,
            'ingreso_arriendos'     => $ingresoArriendos,
            'ingreso_variable'      => $ingresoVariable,
            'ingreso_comisiones'    => $ingresoComisiones,
            'ingreso_pensiones'     => $ingresoPensiones,
            'ingreso_bonos'         => $ingresoBonos,
            'ingreso_otros'         => $ingresoOtros,
            'ingreso_mensual_total' => $totalIngreso,
            'activos_total'         => $activos,
            'pasivos_total'         => $pasivos,
            'patrimonio_neto'       => $patrimonio,
            'notas'                 => $notas !== '' ? $notas : null,
        ];
    }

    /** @return list<string> */
    private function tiposRespaldo(): array
    {
        return [
            'Liquidación de sueldo',
            'Declaración de renta / DAI',
            'Cartola bancaria',
            'Certificado de ingresos',
            'Escritura / poder',
            'Otro',
        ];
    }

    private function findPersona(string $empresaId, string $id): ?array
    {
        $row = model(EquityPersonaModel::class)
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        return is_array($row) ? $row : null;
    }

    private function findFicha(string $empresaId, string $personaId, string $fichaId): ?array
    {
        $row = model(EquityFichaModel::class)
            ->where('id', $fichaId)
            ->where('empresa_id', $empresaId)
            ->where('persona_id', $personaId)
            ->first();

        return is_array($row) ? $row : null;
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
            'notas'          => 'Versión inicial (equity)',
            'creado_por_id'  => $memb['usuario_id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
