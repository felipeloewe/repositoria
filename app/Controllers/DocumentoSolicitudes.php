<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentoSolicitudService;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DocumentoModel;
use App\Models\DocumentoSolicitudModel;
use App\Models\DocumentoTipoModel;
use RuntimeException;

/**
 * Colas Archivado / En Baja + alta de solicitudes desde docs.
 */
class DocumentoSolicitudes extends BaseController
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

    public function archivado()
    {
        return $this->cola(DocumentoSolicitudService::TIPO_ARCHIVO, 'Archivado');
    }

    public function enBaja()
    {
        return $this->cola(DocumentoSolicitudService::TIPO_BAJA, 'En Baja');
    }

    private function cola(string $tipo, string $title)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $canValidate = $tipo === DocumentoSolicitudService::TIPO_ARCHIVO
            ? DocumentoSolicitudService::canValidateArchivo($memb)
            : DocumentoSolicitudService::canValidateBaja($memb);

        if (! $canValidate && ! Roles::isMaster($memb['rol']) && $memb['rol'] !== Roles::JEFE_SUCURSAL) {
            // Solicitantes pueden ver sus pendientes; validadores ven toda la cola.
        }

        $db = db_connect();
        $q  = $db->table('documento_solicitudes s')
            ->select('s.*, d.titulo AS doc_titulo, d.ambito AS doc_ambito, d.estado AS doc_estado, u.nombre AS solicitante_nombre, uv.nombre AS validador_nombre')
            ->join('documentos d', 'd.id = s.documento_id', 'left')
            ->join('usuarios u', 'u.id = s.solicitante_usuario_id', 'left')
            ->join('usuarios uv', 'uv.id = s.validador_usuario_id', 'left')
            ->where('s.empresa_id', $memb['empresa_id'])
            ->where('s.tipo', $tipo)
            ->orderBy('s.created_at', 'DESC');

        if (! $canValidate) {
            $q->where('s.solicitante_usuario_id', $memb['usuario_id']);
        }

        $rows = $q->get()->getResultArray();

        $svc = new DocumentoSolicitudService();
        $zip = $svc->ultimoRespaldoDescargado((string) $memb['empresa_id']);

        return view('documentos/solicitudes_cola', [
            'title'       => $title,
            'memb'        => $memb,
            'tab'         => $tipo === DocumentoSolicitudService::TIPO_ARCHIVO ? 'archivado' : 'baja',
            'tipo'        => $tipo,
            'rows'        => $rows,
            'canValidate' => $canValidate,
            'zipOk'       => $zip !== null,
            'zip'         => $zip,
            'success'     => session()->getFlashdata('success'),
            'error'       => session()->getFlashdata('error'),
        ]);
    }

    public function solicitar(string $id)
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
        if (! DocumentAcl::canView($memb, $doc)) {
            return redirect()->to('/documentos')->with('error', 'Sin permiso.');
        }

        $tipo = strtoupper(trim((string) $this->request->getPost('tipo')));
        $nota = trim((string) $this->request->getPost('nota'));
        $back = DocumentAcl::listPathForAmbito($doc['ambito'] ?? null);

        try {
            (new DocumentoSolicitudService())->solicitar($memb, $doc, $tipo, $nota !== '' ? $nota : null);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $dest = $tipo === DocumentoSolicitudService::TIPO_BAJA
            ? '/documentos/en-baja'
            : '/documentos/archivado';

        return redirect()->to($dest)->with('success', 'Solicitud de ' . DocumentoSolicitudService::labelTipo($tipo) . ' enviada.');
    }

    public function resolver(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $sol = model(DocumentoSolicitudModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($sol === null) {
            return redirect()->to('/documentos/archivado')->with('error', 'Solicitud no encontrada.');
        }

        $accion = trim((string) $this->request->getPost('accion'));
        $nota   = trim((string) $this->request->getPost('nota'));
        $aprobar = $accion === 'aprobar';

        $dest = ((string) $sol['tipo'] === DocumentoSolicitudService::TIPO_BAJA)
            ? '/documentos/en-baja'
            : '/documentos/archivado';

        try {
            (new DocumentoSolicitudService())->resolver($memb, $sol, $aprobar, $nota !== '' ? $nota : null);
        } catch (RuntimeException $e) {
            return redirect()->to($dest)->with('error', $e->getMessage());
        }

        return redirect()->to($dest)->with(
            'success',
            $aprobar ? 'Solicitud aprobada.' : 'Solicitud rechazada.'
        );
    }
}
