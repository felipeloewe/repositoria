<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityCatalogo;
use App\Libraries\EquityUf;
use App\Libraries\MembershipAuth;
use App\Models\EquityBienRaizModel;
use App\Models\EquityFichaModel;
use App\Models\EquityOtroActivoModel;
use App\Models\EquityPasivoModel;
use App\Models\EquityPersonaModel;
use App\Models\EquityRespaldoModel;
use App\Models\EquitySociedadModel;
use App\Models\EquityVehiculoModel;
use App\Models\DocumentoModel;

/**
 * Menús patrimoniales Equity: resumen, activos, pasivos, ingresos, respaldos.
 */
class EquityPatrimonio extends BaseController
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
        if (! EquityAcl::canAccess($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin acceso a Equity.');
        }

        return $memb;
    }

    public function resumen()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $personas  = $this->personas($empresaId);

        $ufVigente = EquityUf::vigente($empresaId);
        $ufRate    = (float) ($ufVigente['valor_clp'] ?? EquityUf::DEFAULT_VALOR_CLP);

        $totalesUf = [
            'bienes_raices' => $this->sumUfCol($empresaId, 'equity_bienes_raices', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'vehiculos'     => $this->sumUfCol($empresaId, 'equity_vehiculos', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'sociedades'    => $this->sumUfCol($empresaId, 'equity_sociedades', 'valor_uf', 'valor_clp', $personaId, $ufRate),
            'otros_activos' => $this->sumUfCol($empresaId, 'equity_otros_activos', 'saldo_uf', 'saldo_clp', $personaId, $ufRate),
            'pasivos_inm'   => $this->sumPasivoCategoria($empresaId, 'inmueble', $personaId, $ufRate),
            'pasivos_circ'  => $this->sumPasivoCategoria($empresaId, 'circulante', $personaId, $ufRate),
            'pasivos_otro'  => $this->sumPasivoCategoria($empresaId, 'otro', $personaId, $ufRate),
        ];
        $totalesUf['activos']    = $totalesUf['bienes_raices'] + $totalesUf['vehiculos'] + $totalesUf['sociedades'] + $totalesUf['otros_activos'];
        $totalesUf['pasivos']    = $totalesUf['pasivos_inm'] + $totalesUf['pasivos_circ'] + $totalesUf['pasivos_otro'];
        $totalesUf['patrimonio'] = $totalesUf['activos'] - $totalesUf['pasivos'];

        $totales = [];
        foreach ($totalesUf as $k => $uf) {
            $totales[$k] = round($uf * $ufRate, 2);
        }

        return view('equity/resumen', [
            'title'     => 'Resumen',
            'memb'      => $memb,
            'equityNav' => 'resumen',
            'personas'  => $personas,
            'personaId' => $personaId,
            'totales'   => $totales,
            'totalesUf' => $totalesUf,
            'ufVigente' => $ufVigente,
            'menus'     => EquityCatalogo::menusInicio(),
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    public function activos(string $seccion)
    {
        return $this->seccionLista($this->claveActivo($seccion));
    }

    public function pasivos(string $seccion)
    {
        return $this->seccionLista($this->clavePasivo($seccion));
    }

    public function storeActivo(string $seccion)
    {
        return $this->storeSeccion($this->claveActivo($seccion));
    }

    public function storePasivo(string $seccion)
    {
        return $this->storeSeccion($this->clavePasivo($seccion));
    }

    private function claveActivo(string $seccion): string
    {
        return match ($seccion) {
            'bienes-raices' => 'bienes-raices',
            'vehiculos'     => 'vehiculos',
            'sociedades'    => 'sociedades',
            'otros'         => 'otros-activos',
            default         => '',
        };
    }

    private function clavePasivo(string $seccion): string
    {
        return match ($seccion) {
            'inmuebles'   => 'pasivos-inmuebles',
            'circulantes' => 'pasivos-circulantes',
            'otros'       => 'otros-pasivos',
            default       => '',
        };
    }

    public function ingresos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $q = model(EquityFichaModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha_corte', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $fichas = $q->findAll();
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($fichas as &$f) {
            $f['persona'] = $personaMap[$f['persona_id']] ?? null;
        }
        unset($f);

        return view('equity/ingresos', [
            'title'     => 'Ingresos',
            'memb'      => $memb,
            'equityNav' => 'ingresos',
            'fichas'    => $fichas,
            'personas'  => $personas,
            'personaId' => $personaId,
            'ufVigente' => EquityUf::vigente($empresaId),
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    public function respaldos()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $q = model(EquityRespaldoModel::class)
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        $respaldos = $q->findAll();
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        $docIds = array_values(array_filter(array_column($respaldos, 'documento_id')));
        $docsById = [];
        if ($docIds !== []) {
            foreach (model(DocumentoModel::class)->whereIn('id', $docIds)->findAll() as $d) {
                $docsById[$d['id']] = $d;
            }
        }
        foreach ($respaldos as &$r) {
            $r['persona']   = $personaMap[$r['persona_id']] ?? null;
            $r['documento'] = $docsById[$r['documento_id']] ?? null;
        }
        unset($r);

        return view('equity/respaldos', [
            'title'     => 'Respaldos',
            'memb'      => $memb,
            'equityNav' => 'respaldos',
            'respaldos' => $respaldos,
            'personas'  => $personas,
            'personaId' => $personaId,
            'canManage' => EquityAcl::canManage($memb),
        ]);
    }

    private function seccionLista(string $clave)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $empresaId = $memb['empresa_id'];
        $personaId = trim((string) $this->request->getGet('persona_id'));
        $rows = $this->loadRows($meta, $empresaId, $personaId);
        $personas = $this->personas($empresaId);
        $personaMap = [];
        foreach ($personas as $p) {
            $personaMap[$p['id']] = $p;
        }
        foreach ($rows as &$row) {
            $row['persona'] = $personaMap[$row['persona_id']] ?? null;
        }
        unset($row);

        return view('equity/seccion', [
            'title'     => $meta['label'],
            'memb'      => $memb,
            'equityNav' => $clave,
            'meta'      => $meta,
            'rows'      => $rows,
            'personas'  => $personas,
            'personaId' => $personaId,
            'ufVigente' => EquityUf::vigente($empresaId),
            'canManage' => EquityAcl::canManage($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    private function storeSeccion(string $clave)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin permiso para editar.');
        }

        $meta = EquityCatalogo::seccion($clave);
        if ($meta === null) {
            return redirect()->to('/dashboard')->with('error', 'Sección no válida.');
        }

        $personaId = trim((string) $this->request->getPost('persona_id'));
        $persona = model(EquityPersonaModel::class)
            ->where('id', $personaId)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($persona === null) {
            return redirect()->back()->with('error', 'Selecciona una persona válida.');
        }

        helper('id');
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

        $parValor = EquityUf::resolverPar(
            (string) $this->request->getPost('valor_uf'),
            (string) $this->request->getPost('valor_clp'),
            $memb['empresa_id']
        );
        $parSaldo = EquityUf::resolverPar(
            (string) $this->request->getPost('saldo_uf'),
            (string) $this->request->getPost('saldo_clp'),
            $memb['empresa_id']
        );

        $redirectPath = match ($clave) {
            'bienes-raices'       => '/equity/activos/bienes-raices',
            'vehiculos'           => '/equity/activos/vehiculos',
            'sociedades'          => '/equity/activos/sociedades',
            'otros-activos'       => '/equity/activos/otros',
            'pasivos-inmuebles'   => '/equity/pasivos/inmuebles',
            'pasivos-circulantes' => '/equity/pasivos/circulantes',
            'otros-pasivos'       => '/equity/pasivos/otros',
            default               => '/dashboard',
        };

        $id = new_id();

        $intOrNull = static function ($raw): ?int {
            $v = trim((string) $raw);
            if ($v === '' || ! is_numeric($v)) {
                return null;
            }

            return (int) $v;
        };

        if ($clave === 'bienes-raices') {
            $direccion = trim((string) $this->request->getPost('direccion'));
            $tipoInm   = $intOrNull($this->request->getPost('tipo_inmueble'));
            if ($direccion === '' || $tipoInm === null) {
                return redirect()->to($redirectPath)->with('error', 'Tipo y dirección son obligatorios.');
            }
            model(EquityBienRaizModel::class)->insert([
                'id'             => $id,
                'empresa_id'     => $memb['empresa_id'],
                'persona_id'     => $personaId,
                'tipo_inmueble'  => $tipoInm,
                'direccion'      => $direccion,
                'descripcion'    => $direccion,
                'rol'            => trim((string) $this->request->getPost('rol')) ?: null,
                'garantia'       => $intOrNull($this->request->getPost('garantia')),
                'asegurado'      => $intOrNull($this->request->getPost('asegurado')),
                'valor_clp'      => $parValor['clp'],
                'valor_uf'       => $parValor['uf'],
                'notas'          => trim((string) $this->request->getPost('notas')) ?: null,
                'creado_por_id'  => $memb['usuario_id'],
            ]);
        } elseif ($clave === 'vehiculos') {
            $tipoVeh = $intOrNull($this->request->getPost('tipo_vehiculo'));
            if ($tipoVeh === null) {
                return redirect()->to($redirectPath)->with('error', 'El tipo de vehículo es obligatorio.');
            }
            $tipoLabel = EquityCatalogo::opcionesTipoVehiculo()[$tipoVeh] ?? (string) $tipoVeh;
            model(EquityVehiculoModel::class)->insert([
                'id'            => $id,
                'empresa_id'    => $memb['empresa_id'],
                'persona_id'    => $personaId,
                'tipo_vehiculo' => $tipoVeh,
                'tipo'          => $tipoLabel,
                'marca'         => trim((string) $this->request->getPost('marca')) ?: null,
                'modelo'        => trim((string) $this->request->getPost('modelo')) ?: null,
                'anio'          => $intOrNull($this->request->getPost('anio')),
                'patente'       => trim((string) $this->request->getPost('patente')) ?: null,
                'garantia'      => $intOrNull($this->request->getPost('garantia')),
                'asegurado'     => $intOrNull($this->request->getPost('asegurado')),
                'valor_clp'     => $parValor['clp'],
                'valor_uf'      => $parValor['uf'],
                'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
                'creado_por_id' => $memb['usuario_id'],
            ]);
        } elseif ($clave === 'sociedades') {
            $nombre = trim((string) $this->request->getPost('nombre_sociedad'));
            if ($nombre === '') {
                return redirect()->to($redirectPath)->with('error', 'La razón social es obligatoria.');
            }
            model(EquitySociedadModel::class)->insert([
                'id'                 => $id,
                'empresa_id'         => $memb['empresa_id'],
                'persona_id'         => $personaId,
                'nombre_sociedad'    => $nombre,
                'rut_sociedad'       => trim((string) $this->request->getPost('rut_sociedad')) ?: null,
                'giro_rubro'         => trim((string) $this->request->getPost('giro_rubro')) ?: null,
                'porcentaje'         => $money($this->request->getPost('porcentaje')),
                'valor_clp'          => $parValor['clp'],
                'valor_uf'           => $parValor['uf'],
                'emp_relacionada'    => $intOrNull($this->request->getPost('emp_relacionada')),
                'cumple_articulo_84' => (int) $this->request->getPost('cumple_articulo_84') === 1 ? 1 : 0,
                'tipo'               => 'Directo',
                'notas'              => trim((string) $this->request->getPost('notas')) ?: null,
                'creado_por_id'      => $memb['usuario_id'],
            ]);
        } elseif ($clave === 'otros-activos') {
            $tipoAct = $intOrNull($this->request->getPost('tipo_activo'));
            if ($tipoAct === null) {
                return redirect()->to($redirectPath)->with('error', 'El tipo de activo es obligatorio.');
            }
            $tipoLabel = EquityCatalogo::opcionesTipoOtroActivo()[$tipoAct] ?? (string) $tipoAct;
            model(EquityOtroActivoModel::class)->insert([
                'id'            => $id,
                'empresa_id'    => $memb['empresa_id'],
                'persona_id'    => $personaId,
                'tipo_activo'   => $tipoAct,
                'tipo'          => $tipoLabel,
                'institucion'   => trim((string) $this->request->getPost('institucion')) ?: null,
                'saldo_clp'     => $parSaldo['clp'],
                'saldo_uf'      => $parSaldo['uf'],
                'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
                'creado_por_id' => $memb['usuario_id'],
            ]);
        } else {

            $desc = trim((string) $this->request->getPost('banco_descripcion'));
            if ($desc === '') {
                return redirect()->to($redirectPath)->with('error', 'La descripción / banco es obligatoria.');
            }
            model(EquityPasivoModel::class)->insert([
                'id'                => $id,
                'empresa_id'        => $memb['empresa_id'],
                'persona_id'        => $personaId,
                'categoria'         => $meta['categoria'] ?? 'otro',
                'banco_descripcion' => $desc,
                'saldo_clp'         => $parSaldo['clp'],
                'saldo_uf'          => $parSaldo['uf'],
                'cuotas_totales'    => ($v = trim((string) $this->request->getPost('cuotas_totales'))) !== '' ? (int) $v : null,
                'cuotas_pagadas'    => ($v = trim((string) $this->request->getPost('cuotas_pagadas'))) !== '' ? (int) $v : null,
                'cuota_mensual'     => $money($this->request->getPost('cuota_mensual')),
                'asociacion'        => trim((string) $this->request->getPost('asociacion')) ?: null,
                'tipo'              => trim((string) $this->request->getPost('tipo')) ?: null,
                'notas'             => trim((string) $this->request->getPost('notas')) ?: null,
                'creado_por_id'     => $memb['usuario_id'],
            ]);
        }

        return redirect()->to($redirectPath . ($personaId !== '' ? '?persona_id=' . rawurlencode($personaId) : ''))
            ->with('success', 'Registro agregado.');
    }

    /** @return list<array<string, mixed>> */
    private function personas(string $empresaId): array
    {
        return model(EquityPersonaModel::class)
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * @param array{table: string, categoria?: string} $meta
     * @return list<array<string, mixed>>
     */
    private function loadRows(array $meta, string $empresaId, string $personaId): array
    {
        $model = match ($meta['table']) {
            'equity_bienes_raices'  => model(EquityBienRaizModel::class),
            'equity_vehiculos'      => model(EquityVehiculoModel::class),
            'equity_sociedades'     => model(EquitySociedadModel::class),
            'equity_otros_activos'  => model(EquityOtroActivoModel::class),
            'equity_pasivos'        => model(EquityPasivoModel::class),
            default                 => null,
        };
        if ($model === null) {
            return [];
        }

        $q = $model->where('empresa_id', $empresaId)->orderBy('created_at', 'DESC');
        if ($personaId !== '') {
            $q->where('persona_id', $personaId);
        }
        if (($meta['table'] ?? '') === 'equity_pasivos' && isset($meta['categoria'])) {
            $q->where('categoria', $meta['categoria']);
        }

        return $q->findAll();
    }

    /**
     * Suma UF: usa columna UF si existe; si no, convierte CLP legacy con $ufRate.
     */
    private function sumUfCol(
        string $empresaId,
        string $table,
        string $colUf,
        string $colClp,
        string $personaId,
        float $ufRate
    ): float {
        if (! db_connect()->tableExists($table)) {
            return 0.0;
        }
        $db = db_connect();
        $hasUf = $db->fieldExists($colUf, $table);
        $builder = $db->table($table)->where('empresa_id', $empresaId);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($hasUf && $r[$colUf] !== null && $r[$colUf] !== '') {
                $sum += (float) $r[$colUf];
            } elseif ($r[$colClp] !== null && $r[$colClp] !== '' && $ufRate > 0) {
                $sum += (float) $r[$colClp] / $ufRate;
            }
        }

        return $sum;
    }

    private function sumPasivoCategoria(string $empresaId, string $categoria, string $personaId, float $ufRate): float
    {
        if (! db_connect()->tableExists('equity_pasivos')) {
            return 0.0;
        }
        $builder = db_connect()->table('equity_pasivos')
            ->where('empresa_id', $empresaId)
            ->where('categoria', $categoria);
        if ($personaId !== '') {
            $builder->where('persona_id', $personaId);
        }
        $rows = $builder->get()->getResultArray();
        $sum = 0.0;
        foreach ($rows as $r) {
            if ($r['saldo_uf'] !== null && $r['saldo_uf'] !== '') {
                $sum += (float) $r['saldo_uf'];
            } elseif ($r['saldo_clp'] !== null && $r['saldo_clp'] !== '' && $ufRate > 0) {
                $sum += (float) $r['saldo_clp'] / $ufRate;
            }
        }

        return $sum;
    }
}
