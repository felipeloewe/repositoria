<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\EquityAcl;
use App\Libraries\EquityCierre as EquityCierreLib;
use App\Libraries\EquityUf;
use App\Libraries\MembershipAuth;
use App\Models\EquityPeriodoCierreModel;

/**
 * Cierre mensual Equity: V.B. del mes/año, historial de reportes y desarrollo de créditos.
 */
class EquityCierre extends BaseController
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

    private function sessionPersonaId(): string
    {
        return (string) ((new MembershipAuth())->equityPersonaId() ?? '');
    }

    /**
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function personaContexto(array $memb): array
    {
        $personaId = $this->sessionPersonaId();
        $auth      = new MembershipAuth();
        $uid       = (string) ($auth->usuarioId() ?? '');
        $personas  = $uid !== ''
            ? (new \App\Libraries\EquityOrg())->personasDeclarables($uid, $memb['empresa_id'])
            : [];
        if ($personaId === '' && count($personas) === 1) {
            $personaId = (string) $personas[0]['id'];
            $auth->setEquityPersonaId($personaId);
        }

        return [$personaId, $personas];
    }

    public function index()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);

        $anio = (int) ($this->request->getGet('anio') ?: date('Y'));
        $mes  = (int) ($this->request->getGet('mes') ?: date('n'));
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) date('Y');
        }
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date('n');
        }

        $periodoActual = EquityCierreLib::periodoDe($empresaId, $personaId, $anio, $mes);
        $periodos      = EquityCierreLib::listarPeriodos($empresaId, $personaId);
        $fechaCierre   = EquityUf::cierreDeMes($anio, $mes);
        $ufRow         = EquityUf::vigente($empresaId, $fechaCierre);
        $tablasOk      = db_connect()->tableExists('equity_periodos_cierre')
            && db_connect()->tableExists('equity_reportes_historial');

        return view('equity/cierre', [
            'title'         => 'Cierre mensual',
            'memb'          => $memb,
            'equityNav'     => 'cierre',
            'personas'      => $personas,
            'personaId'     => $personaId,
            'anio'          => $anio,
            'mes'           => $mes,
            'periodoActual' => $periodoActual,
            'periodos'      => $periodos,
            'fechaCierre'   => $fechaCierre,
            'ufRow'         => $ufRow,
            'tablasOk'      => $tablasOk,
            'canDeclare'    => EquityAcl::canDeclare($memb),
            'success'       => session()->getFlashdata('success'),
            'error'         => session()->getFlashdata('error'),
        ]);
    }

    public function cerrar()
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! EquityAcl::canDeclare($memb)) {
            return redirect()->to('/equity/cierre')->with('error', 'Sin permiso para cerrar el periodo.');
        }

        $empresaId = $memb['empresa_id'];
        [$personaId] = $this->personaContexto($memb);
        $anio = (int) $this->request->getPost('anio');
        $mes  = (int) $this->request->getPost('mes');
        $vbNota = trim((string) $this->request->getPost('vb_nota'));
        $notas  = trim((string) $this->request->getPost('notas'));

        $result = EquityCierreLib::cerrarConVb(
            $empresaId,
            $personaId,
            $anio,
            $mes,
            (string) ($memb['usuario_id'] ?? ''),
            $vbNota !== '' ? $vbNota : null,
            $notas !== '' ? $notas : null
        );

        $qs = '?anio=' . $anio . '&mes=' . $mes;
        if (! ($result['ok'] ?? false)) {
            return redirect()->to('/equity/cierre' . $qs)->with('error', $result['error'] ?? 'No se pudo cerrar.');
        }

        $periodoId = (string) (($result['periodo']['id'] ?? '') ?: '');
        $label = EquityCierreLib::labelMes($mes) . ' ' . $anio;
        $msg = 'Periodo ' . $label . ' cerrado con V.B. Se generaron '
            . (int) ($result['reportes'] ?? 0) . ' reportes en Historial.';

        if ($periodoId !== '') {
            return redirect()->to('/equity/cierre/periodo/' . $periodoId)->with('success', $msg);
        }

        return redirect()->to('/equity/cierre' . $qs)->with('success', $msg);
    }

    public function periodo(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        [$personaId, $personas] = $this->personaContexto($memb);

        $periodo = model(EquityPeriodoCierreModel::class)->find($id);
        if (! is_array($periodo) || (string) ($periodo['empresa_id'] ?? '') !== $empresaId) {
            return redirect()->to('/equity/cierre')->with('error', 'Periodo no encontrado.');
        }
        $pPersona = (string) ($periodo['persona_id'] ?? '');
        if ($personaId !== '' && $pPersona !== '' && $pPersona !== $personaId) {
            return redirect()->to('/equity/cierre')->with('error', 'Ese cierre pertenece a otra persona.');
        }

        $reportes   = EquityCierreLib::reportesDePeriodo($id);
        $desarrollo = EquityCierreLib::desarrolloDePeriodo($id);

        return view('equity/cierre_periodo', [
            'title'      => 'Historial · ' . EquityCierreLib::labelMes((int) $periodo['mes']) . ' ' . $periodo['anio'],
            'memb'       => $memb,
            'equityNav'  => 'cierre',
            'personas'   => $personas,
            'personaId'  => $personaId,
            'periodo'    => $periodo,
            'reportes'   => $reportes,
            'desarrollo' => $desarrollo,
            'tipos'      => EquityCierreLib::TIPOS_REPORTE,
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function reporte(string $id)
    {
        $memb = $this->requireAccess();
        if (! is_array($memb)) {
            return $memb;
        }

        $empresaId = $memb['empresa_id'];
        $row = EquityCierreLib::reporte($id);
        if (! is_array($row) || (string) ($row['empresa_id'] ?? '') !== $empresaId) {
            return redirect()->to('/equity/cierre')->with('error', 'Reporte histórico no encontrado.');
        }

        $periodo = model(EquityPeriodoCierreModel::class)->find((string) ($row['periodo_id'] ?? ''));
        $payload = [];
        if (! empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return view('equity/cierre_reporte', [
            'title'     => (string) ($row['titulo'] ?? 'Reporte histórico'),
            'memb'      => $memb,
            'equityNav' => 'cierre',
            'reporte'   => $row,
            'periodo'   => is_array($periodo) ? $periodo : null,
            'payload'   => $payload,
            'tipos'     => EquityCierreLib::TIPOS_REPORTE,
        ]);
    }
}
