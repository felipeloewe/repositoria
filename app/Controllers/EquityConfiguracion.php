<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityUf;
use App\Libraries\MembershipAuth;
use App\Models\EquityUfValorModel;

/**
 * Administración → Configuración: variables Equity (UF al cierre de mes).
 */
class EquityConfiguracion extends BaseController
{
    /**
     * @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse
     */
    private function requireManage()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! EquityAcl::canManage($memb)) {
            return redirect()->to('/dashboard')->with('error', 'Sin permiso para Configuración.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $this->ensureDefaultUf($memb['empresa_id'], $memb['usuario_id']);

        $valores = model(EquityUfValorModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('fecha_cierre', 'DESC')
            ->findAll();

        $vigente = EquityUf::vigente($memb['empresa_id']);
        $editUfId = trim((string) $this->request->getGet('edit_uf'));
        $editUf   = null;
        if ($editUfId !== '') {
            foreach ($valores as $v) {
                if (($v['id'] ?? '') === $editUfId) {
                    $editUf = $v;
                    break;
                }
            }
        }

        return view('equity/configuracion', [
            'title'     => 'Configuración',
            'memb'      => $memb,
            'equityNav' => 'configuracion',
            'valores'   => $valores,
            'vigente'   => $vigente,
            'editUf'    => $editUf,
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function storeUf()
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $anio = (int) $this->request->getPost('anio');
        $mes  = (int) $this->request->getPost('mes');
        if ($anio < 1990 || $anio > 2100 || $mes < 1 || $mes > 12) {
            return redirect()->to('/equity/configuracion')->with('error', 'Mes / año inválidos.');
        }

        $valor = EquityUf::parseDecimal((string) $this->request->getPost('valor_clp'), 2);
        if ($valor === null || $valor <= 0) {
            return redirect()->to('/equity/configuracion')->with('error', 'Ingresa el valor UF en $.');
        }

        $fecha = EquityUf::cierreDeMes($anio, $mes);
        $existente = model(EquityUfValorModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('fecha_cierre', $fecha)
            ->first();

        helper('id');
        if ($existente !== null) {
            model(EquityUfValorModel::class)->update($existente['id'], [
                'valor_clp' => number_format($valor, 2, '.', ''),
                'notas'     => trim((string) $this->request->getPost('notas')) ?: null,
            ]);

            return redirect()->to('/equity/configuracion')
                ->with('success', 'UF del ' . $fecha . ' actualizada.');
        }

        model(EquityUfValorModel::class)->insert([
            'id'            => new_id(),
            'empresa_id'    => $memb['empresa_id'],
            'fecha_cierre'  => $fecha,
            'valor_clp'     => number_format($valor, 2, '.', ''),
            'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
            'creado_por_id' => $memb['usuario_id'],
        ]);

        return redirect()->to('/equity/configuracion')
            ->with('success', 'UF del ' . $fecha . ' registrada.');
    }

    public function deleteUf(string $id)
    {
        $memb = $this->requireManage();
        if (! is_array($memb)) {
            return $memb;
        }

        $row = model(EquityUfValorModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($row === null) {
            return redirect()->to('/equity/configuracion')->with('error', 'Registro no encontrado.');
        }

        model(EquityUfValorModel::class)->delete($id);

        return redirect()->to('/equity/configuracion')->with('success', 'Valor UF eliminado.');
    }

    private function ensureDefaultUf(string $empresaId, string $usuarioId): void
    {
        if (! db_connect()->tableExists('equity_uf_valores')) {
            return;
        }
        $n = model(EquityUfValorModel::class)->where('empresa_id', $empresaId)->countAllResults();
        if ($n > 0) {
            return;
        }
        helper('id');
        model(EquityUfValorModel::class)->insert([
            'id'            => new_id(),
            'empresa_id'    => $empresaId,
            'fecha_cierre'  => EquityUf::DEFAULT_FECHA_CIERRE,
            'valor_clp'     => number_format(EquityUf::DEFAULT_VALOR_CLP, 2, '.', ''),
            'notas'         => 'Valor inicial (cierre 31.10.2026)',
            'creado_por_id' => $usuarioId,
        ]);
    }
}
