<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\EquityAcl;
use App\Libraries\EquityUf;
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
            // Antecedentes laborales reutilizan la ficha de Antecedentes Personales (mismo RUT/persona).
            if ($rawTipo === EquityAcl::TIPO_GERENTE) {
                $q->where('tipo', EquityAcl::TIPO_SOCIO);
            } else {
                $q->where('tipo', $filterTipo);
            }
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

        $equityNav = '';
        if ($rawTipo !== '' && in_array($rawTipo, EquityAcl::TIPOS, true)) {
            $equityNav = $rawTipo;
        }

        return view('equity/index', [
            'title'     => 'Equity',
            'memb'      => $memb,
            'personas'  => $personas,
            'canManage' => EquityAcl::canManage($memb),
            'tipos'     => EquityAcl::TIPOS,
            'equityNav' => $equityNav,
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

        $tipoPref = EquityAcl::normalizeTipo((string) $this->request->getGet('tipo'));
        $vistaLaboral = $tipoPref === EquityAcl::TIPO_GERENTE;

        return view('equity/form_persona', [
            'title'        => 'Equity',
            'memb'         => $memb,
            'persona'      => ['tipo' => $tipoPref],
            'tipos'        => EquityAcl::TIPOS,
            'equityNav'    => $tipoPref,
            'error'        => session()->getFlashdata('error'),
            'buscarRutUrl' => site_url('equity/buscar-rut'),
            'vistaLaboral' => $vistaLaboral,
        ]);
    }

    /** JSON: recupera Antecedentes Personales por RUT (para laborales). */
    public function buscarPorRut()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Sin sesión']);
        }

        $rutNorm = EquityAcl::normalizeRut((string) $this->request->getGet('rut'));
        if ($rutNorm === '') {
            return $this->response->setJSON(['ok' => false, 'error' => 'Ingresa un RUT.']);
        }

        $personas = model(EquityPersonaModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('tipo', EquityAcl::TIPO_SOCIO)
            ->where('activo', 1)
            ->findAll();

        $match = null;
        foreach ($personas as $p) {
            if (EquityAcl::normalizeRut($p['rut'] ?? null) === $rutNorm) {
                $match = $p;
                break;
            }
        }

        if ($match === null) {
            return $this->response->setJSON([
                'ok'    => false,
                'error' => 'No hay Antecedentes Personales con ese RUT. Créalos primero.',
            ]);
        }

        return $this->response->setJSON([
            'ok'      => true,
            'persona' => [
                'id'               => $match['id'],
                'rut'              => $match['rut'],
                'nombre'           => $match['nombre'],
                'apellido'         => $match['apellido'],
                'apellido_materno' => $match['apellido_materno'] ?? null,
                'email'            => $match['email'] ?? null,
                'tipo_actividad'   => $match['tipo_actividad'] ?? null,
                'tipo_contrato'    => $match['tipo_contrato'] ?? null,
                'tipo_renta'       => $match['tipo_renta'] ?? null,
                'fecha_ingreso_mes'=> $match['fecha_ingreso_mes'] ?? null,
                'fecha_ingreso_anio'=> $match['fecha_ingreso_anio'] ?? null,
                'cargo'            => $match['cargo'] ?? null,
                'antiguedad_cargo_meses' => $match['antiguedad_cargo_meses'] ?? null,
                'antiguedad_cargo_anos'  => $match['antiguedad_cargo_anos'] ?? null,
                'rut_empleador'    => $match['rut_empleador'] ?? null,
                'nombre_empleador' => $match['nombre_empleador'] ?? null,
                'giro_empleador'   => $match['giro_empleador'] ?? null,
                'renta_liquida_persona' => $match['renta_liquida_persona'] ?? null,
                'dir_com_calle'    => $match['dir_com_calle'] ?? null,
                'dir_com_numero'   => $match['dir_com_numero'] ?? null,
                'dir_com_otros'    => $match['dir_com_otros'] ?? null,
                'dir_com_comuna'   => $match['dir_com_comuna'] ?? null,
                'dir_com_ciudad'   => $match['dir_com_ciudad'] ?? null,
                'dir_com_region'   => $match['dir_com_region'] ?? null,
                'tel_comercial'    => $match['tel_comercial'] ?? null,
                'tel_anexo'        => $match['tel_anexo'] ?? null,
                'segunda_actividad'=> $match['segunda_actividad'] ?? null,
                'segunda_actividad_mes'  => $match['segunda_actividad_mes'] ?? null,
                'segunda_actividad_anio' => $match['segunda_actividad_anio'] ?? null,
                'renta_liquida_2'  => $match['renta_liquida_2'] ?? null,
                'empleo_ant_cargo' => $match['empleo_ant_cargo'] ?? null,
                'empleo_ant_empleador' => $match['empleo_ant_empleador'] ?? null,
                'empleo_ant_ingreso_mes' => $match['empleo_ant_ingreso_mes'] ?? null,
                'empleo_ant_ingreso_anio'=> $match['empleo_ant_ingreso_anio'] ?? null,
                'empleo_ant_salida_mes'  => $match['empleo_ant_salida_mes'] ?? null,
                'empleo_ant_salida_anio' => $match['empleo_ant_salida_anio'] ?? null,
                'empleo_ant_renta' => $match['empleo_ant_renta'] ?? null,
            ],
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

        // Laborales: actualizar la ficha de Antecedentes Personales (mismo RUT), no crear otra.
        if ($data['tipo'] === EquityAcl::TIPO_GERENTE) {
            $personaId = trim((string) $this->request->getPost('persona_id_laboral'));
            $existente = null;
            if ($personaId !== '') {
                $existente = $this->findPersona($memb['empresa_id'], $personaId);
            }
            if ($existente === null) {
                $rutNorm = EquityAcl::normalizeRut($data['rut'] ?? null);
                if ($rutNorm === '') {
                    return redirect()->to('/equity/nueva?tipo=gerente')->with('error', 'Ingresa el RUT y recupera Antecedentes Personales.');
                }
                foreach (model(EquityPersonaModel::class)
                    ->where('empresa_id', $memb['empresa_id'])
                    ->where('tipo', EquityAcl::TIPO_SOCIO)
                    ->findAll() as $p) {
                    if (EquityAcl::normalizeRut($p['rut'] ?? null) === $rutNorm) {
                        $existente = $p;
                        break;
                    }
                }
            }
            if ($existente === null || ($existente['tipo'] ?? '') !== EquityAcl::TIPO_SOCIO) {
                return redirect()->to('/equity/nueva?tipo=gerente')
                    ->with('error', 'No se encontró Antecedentes Personales con ese RUT.');
            }

            $laborKeys = $this->laborFieldKeys();
            $patch = [];
            foreach ($laborKeys as $k) {
                if (array_key_exists($k, $data)) {
                    $patch[$k] = $data[$k];
                }
            }
            // Identidad se mantiene la de personales
            $patch['nombre']           = $existente['nombre'];
            $patch['apellido']         = $existente['apellido'] ?? null;
            $patch['apellido_materno'] = $existente['apellido_materno'] ?? null;
            $patch['rut']              = $existente['rut'] ?? null;

            model(EquityPersonaModel::class)->update($existente['id'], $patch);

            return redirect()->to('/equity/' . $existente['id'])
                ->with('success', 'Antecedentes laborales guardados en la ficha personal.');
        }

        $id = new_id();
        $row = array_merge($data, [
            'id'            => $id,
            'empresa_id'    => $memb['empresa_id'],
            'usuario_id'    => null,
            'activo'        => 1,
            'creado_por_id' => $memb['usuario_id'],
        ]);
        model(EquityPersonaModel::class)->insert($row);

        return redirect()->to('/equity/' . $id)->with('success', 'Integrante creado.');
    }

    /** @return list<string> */
    private function laborFieldKeys(): array
    {
        return [
            'tipo_actividad', 'tipo_contrato', 'tipo_renta',
            'fecha_ingreso_mes', 'fecha_ingreso_anio', 'cargo',
            'antiguedad_cargo_meses', 'antiguedad_cargo_anos',
            'rut_empleador', 'nombre_empleador', 'giro_empleador', 'renta_liquida_persona',
            'dir_com_calle', 'dir_com_numero', 'dir_com_otros',
            'dir_com_comuna', 'dir_com_ciudad', 'dir_com_region',
            'tel_comercial', 'tel_anexo',
            'segunda_actividad', 'segunda_actividad_mes', 'segunda_actividad_anio', 'renta_liquida_2',
            'empleo_ant_cargo', 'empleo_ant_empleador',
            'empleo_ant_ingreso_mes', 'empleo_ant_ingreso_anio',
            'empleo_ant_salida_mes', 'empleo_ant_salida_anio', 'empleo_ant_renta',
            'email', 'notas',
        ];
    }

    /** Segmentos de menú patrimonial que no son ids de persona. */
    private const RESERVED_SEGMENTS = [
        'activos', 'pasivos', 'ingresos', 'resumen', 'respaldos', 'grupo',
        'configuracion', 'situacion-financiera', 'reporte', 'nueva', 'buscar-rut',
    ];

    public function show(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        if (in_array($id, self::RESERVED_SEGMENTS, true)) {
            $fallback = match ($id) {
                'activos' => '/equity/activos/bienes-raices',
                'pasivos' => '/equity/pasivos/inmuebles',
                'ingresos' => '/equity/ingresos',
                'resumen' => '/equity/resumen',
                'respaldos' => '/equity/respaldos',
                'grupo' => '/equity/grupo',
                'configuracion' => '/equity/configuracion',
                'situacion-financiera' => '/equity/situacion-financiera',
                'nueva' => '/equity/nueva',
                default => '/equity',
            };

            return redirect()->to($fallback);
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
            'ufVigente'   => EquityUf::vigente($memb['empresa_id']),
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

        $vista = trim((string) $this->request->getGet('vista'));
        if ($vista === 'laboral') {
            $persona['tipo'] = EquityAcl::TIPO_GERENTE; // solo para mostrar bloque laboral
        }

        return view('equity/form_persona', [
            'title'        => 'Equity',
            'memb'         => $memb,
            'persona'      => $persona,
            'tipos'        => EquityAcl::TIPOS,
            'equityNav'    => $vista === 'laboral' ? EquityAcl::TIPO_GERENTE : ($persona['tipo'] ?? ''),
            'error'        => session()->getFlashdata('error'),
            'buscarRutUrl' => site_url('equity/buscar-rut'),
            'vistaLaboral' => $vista === 'laboral',
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
        $vistaLaboral = (string) $this->request->getPost('vista_laboral') === '1'
            || $data['tipo'] === EquityAcl::TIPO_GERENTE;

        if ($vistaLaboral && ($persona['tipo'] ?? '') === EquityAcl::TIPO_SOCIO) {
            $patch = [];
            foreach ($this->laborFieldKeys() as $k) {
                if (array_key_exists($k, $data)) {
                    $patch[$k] = $data[$k];
                }
            }
            // Identidad siempre desde Antecedentes Personales
            $patch['nombre']           = $persona['nombre'];
            $patch['apellido']         = $persona['apellido'] ?? null;
            $patch['apellido_materno'] = $persona['apellido_materno'] ?? null;
            $patch['rut']              = $persona['rut'] ?? null;
            model(EquityPersonaModel::class)->update($id, $patch);

            return redirect()->to('/equity/' . $id)->with('success', 'Antecedentes laborales actualizados.');
        }

        if ($data['nombre'] === '') {
            return redirect()->to('/equity/' . $id . '/editar')->with('error', 'El nombre es obligatorio.');
        }

        $activo = (int) $this->request->getPost('activo') === 1 ? 1 : 0;
        $row = $data;
        $row['activo'] = $activo;
        // No cambiar a tipo gerente: laborales viven en la ficha personal
        if (($row['tipo'] ?? '') === EquityAcl::TIPO_GERENTE) {
            $row['tipo'] = EquityAcl::TIPO_SOCIO;
        }
        model(EquityPersonaModel::class)->update($id, $row);

        return redirect()->to('/equity/' . $id)->with('success', 'Integrante actualizado.');
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
     * @return array<string, mixed>
     */
    private function readPersonaFromRequest(): array
    {
        $trimOrNull = static function (?string $v): ?string {
            $v = trim((string) $v);

            return $v === '' ? null : $v;
        };
        $intOrNull = static function ($raw): ?int {
            $v = trim((string) $raw);
            if ($v === '' || ! is_numeric($v)) {
                return null;
            }

            return (int) $v;
        };
        $money = static function ($raw): ?string {
            $v = trim((string) $raw);
            if ($v === '') {
                return null;
            }
            if (str_contains($v, ',') && str_contains($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } elseif (str_contains($v, ',')) {
                $v = str_replace(',', '.', $v);
            }

            return is_numeric($v) ? number_format((float) $v, 2, '.', '') : null;
        };

        $fecha = $trimOrNull((string) $this->request->getPost('fecha_nacimiento'));

        return [
            'tipo'                       => EquityAcl::normalizeTipo((string) $this->request->getPost('tipo')),
            'nombre'                     => trim((string) $this->request->getPost('nombre')),
            'apellido'                   => $trimOrNull((string) $this->request->getPost('apellido')),
            'apellido_materno'           => $trimOrNull((string) $this->request->getPost('apellido_materno')),
            'rut'                        => $trimOrNull((string) $this->request->getPost('rut')),
            'email'                      => $trimOrNull((string) $this->request->getPost('email')),
            'cargo'                      => $trimOrNull((string) $this->request->getPost('cargo')),
            'notas'                      => $trimOrNull((string) $this->request->getPost('notas')),
            'fecha_nacimiento'           => $fecha,
            'sexo'                       => $intOrNull($this->request->getPost('sexo')),
            'nacionalidad'               => $intOrNull($this->request->getPost('nacionalidad')),
            'nacionalidad_detalle'       => $trimOrNull((string) $this->request->getPost('nacionalidad_detalle')),
            'grupo_familiar_n'           => $intOrNull($this->request->getPost('grupo_familiar_n')),
            'estado_civil'               => $intOrNull($this->request->getPost('estado_civil')),
            'regimen_patrimonial'        => $trimOrNull((string) $this->request->getPost('regimen_patrimonial')),
            'lugar_habita'               => $intOrNull($this->request->getPost('lugar_habita')),
            'antiguedad_domicilio_meses' => $intOrNull($this->request->getPost('antiguedad_domicilio_meses')),
            'antiguedad_domicilio_anos'  => $intOrNull($this->request->getPost('antiguedad_domicilio_anos')),
            'nivel_estudios'             => $intOrNull($this->request->getPost('nivel_estudios')),
            'profesion'                  => $trimOrNull((string) $this->request->getPost('profesion')),
            'universidad_instituto'      => $trimOrNull((string) $this->request->getPost('universidad_instituto')),
            'dir_calle'                  => $trimOrNull((string) $this->request->getPost('dir_calle')),
            'dir_numero'                 => $trimOrNull((string) $this->request->getPost('dir_numero')),
            'dir_otros'                  => $trimOrNull((string) $this->request->getPost('dir_otros')),
            'dir_comuna'                 => $trimOrNull((string) $this->request->getPost('dir_comuna')),
            'dir_ciudad'                 => $trimOrNull((string) $this->request->getPost('dir_ciudad')),
            'dir_region'                 => $trimOrNull((string) $this->request->getPost('dir_region')),
            'telefono_particular'        => $trimOrNull((string) $this->request->getPost('telefono_particular')),
            'telefono_celular'           => $trimOrNull((string) $this->request->getPost('telefono_celular')),
            'tipo_actividad'             => $intOrNull($this->request->getPost('tipo_actividad')),
            'tipo_contrato'              => $intOrNull($this->request->getPost('tipo_contrato')),
            'tipo_renta'                 => $intOrNull($this->request->getPost('tipo_renta')),
            'fecha_ingreso_mes'          => $intOrNull($this->request->getPost('fecha_ingreso_mes')),
            'fecha_ingreso_anio'         => $intOrNull($this->request->getPost('fecha_ingreso_anio')),
            'antiguedad_cargo_meses'     => $intOrNull($this->request->getPost('antiguedad_cargo_meses')),
            'antiguedad_cargo_anos'      => $intOrNull($this->request->getPost('antiguedad_cargo_anos')),
            'rut_empleador'              => $trimOrNull((string) $this->request->getPost('rut_empleador')),
            'nombre_empleador'           => $trimOrNull((string) $this->request->getPost('nombre_empleador')),
            'giro_empleador'             => $trimOrNull((string) $this->request->getPost('giro_empleador')),
            'renta_liquida_persona'      => $money($this->request->getPost('renta_liquida_persona')),
            'dir_com_calle'              => $trimOrNull((string) $this->request->getPost('dir_com_calle')),
            'dir_com_numero'             => $trimOrNull((string) $this->request->getPost('dir_com_numero')),
            'dir_com_otros'              => $trimOrNull((string) $this->request->getPost('dir_com_otros')),
            'dir_com_comuna'             => $trimOrNull((string) $this->request->getPost('dir_com_comuna')),
            'dir_com_ciudad'             => $trimOrNull((string) $this->request->getPost('dir_com_ciudad')),
            'dir_com_region'             => $trimOrNull((string) $this->request->getPost('dir_com_region')),
            'tel_comercial'              => $trimOrNull((string) $this->request->getPost('tel_comercial')),
            'tel_anexo'                  => $trimOrNull((string) $this->request->getPost('tel_anexo')),
            'segunda_actividad'          => $trimOrNull((string) $this->request->getPost('segunda_actividad')),
            'segunda_actividad_mes'      => $intOrNull($this->request->getPost('segunda_actividad_mes')),
            'segunda_actividad_anio'     => $intOrNull($this->request->getPost('segunda_actividad_anio')),
            'renta_liquida_2'            => $money($this->request->getPost('renta_liquida_2')),
            'empleo_ant_cargo'           => $trimOrNull((string) $this->request->getPost('empleo_ant_cargo')),
            'empleo_ant_empleador'       => $trimOrNull((string) $this->request->getPost('empleo_ant_empleador')),
            'empleo_ant_ingreso_mes'     => $intOrNull($this->request->getPost('empleo_ant_ingreso_mes')),
            'empleo_ant_ingreso_anio'    => $intOrNull($this->request->getPost('empleo_ant_ingreso_anio')),
            'empleo_ant_salida_mes'      => $intOrNull($this->request->getPost('empleo_ant_salida_mes')),
            'empleo_ant_salida_anio'     => $intOrNull($this->request->getPost('empleo_ant_salida_anio')),
            'empleo_ant_renta'           => $money($this->request->getPost('empleo_ant_renta')),
            'complementa_renta'          => $intOrNull($this->request->getPost('complementa_renta')),
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

        $ingresoFijo            = $money($this->request->getPost('ingreso_fijo'));
        $ingresoHonorarios      = $money($this->request->getPost('ingreso_honorarios'));
        $ingresoArriendos       = $money($this->request->getPost('ingreso_arriendos'));
        $ingresoVariable        = $money($this->request->getPost('ingreso_variable'));
        $ingresoComisiones      = $money($this->request->getPost('ingreso_comisiones'));
        $ingresoPensiones       = $money($this->request->getPost('ingreso_pensiones'));
        $ingresoBonos           = $money($this->request->getPost('ingreso_bonos'));
        $ingresoOtros           = $money($this->request->getPost('ingreso_otros'));
        $ingresoMutuos          = $money($this->request->getPost('ingreso_mutuos'));
        $ingresoDividendos      = $money($this->request->getPost('ingreso_dividendos'));
        $ingresoDonaciones      = $money($this->request->getPost('ingreso_donaciones'));
        $ingresoIndemnizaciones = $money($this->request->getPost('ingreso_indemnizaciones'));
        $rentaLiquida           = $money($this->request->getPost('renta_liquida'));
        $activos                = $money($this->request->getPost('activos_total'));
        $pasivos                = $money($this->request->getPost('pasivos_total'));

        $parts = array_filter([
            $ingresoFijo, $ingresoVariable,
            $ingresoHonorarios, $ingresoComisiones, $ingresoBonos,
            $ingresoArriendos, $ingresoMutuos, $ingresoDividendos,
            $ingresoPensiones, $ingresoDonaciones, $ingresoIndemnizaciones,
            $ingresoOtros,
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
            'fecha_corte'              => $fecha !== '' ? $fecha : null,
            'moneda'                   => substr($moneda, 0, 8),
            'renta_liquida'            => $rentaLiquida,
            'ingreso_fijo'             => $ingresoFijo,
            'ingreso_honorarios'       => $ingresoHonorarios,
            'ingreso_arriendos'        => $ingresoArriendos,
            'ingreso_variable'         => $ingresoVariable,
            'ingreso_comisiones'       => $ingresoComisiones,
            'ingreso_pensiones'        => $ingresoPensiones,
            'ingreso_bonos'            => $ingresoBonos,
            'ingreso_otros'            => $ingresoOtros,
            'ingreso_mutuos'           => $ingresoMutuos,
            'ingreso_dividendos'       => $ingresoDividendos,
            'ingreso_donaciones'       => $ingresoDonaciones,
            'ingreso_indemnizaciones'  => $ingresoIndemnizaciones,
            'ingreso_mensual_total'    => $totalIngreso,
            'activos_total'            => $activos,
            'pasivos_total'            => $pasivos,
            'patrimonio_neto'          => $patrimonio,
            'notas'                    => $notas !== '' ? $notas : null,
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
