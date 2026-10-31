<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\FlujosAcl;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DepartamentoModel;
use App\Models\DocumentoAutorizacionModel;
use App\Models\DocumentoModel;
use App\Models\SucursalModel;

class Fiscalizacion extends BaseController
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

    private function audit(array $memb, string $solicitudId, string $accion, ?string $detalle = null): void
    {
        helper('id');
        db_connect()->table('fiscalizacion_auditoria')->insert([
            'id'           => new_id(),
            'empresa_id'   => $memb['empresa_id'],
            'solicitud_id' => $solicitudId,
            'actor_id'     => $memb['usuario_id'],
            'accion'       => $accion,
            'detalle'      => $detalle,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function index()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canRequestFiscalizacion($memb) && ! FlujosAcl::canReviewFiscalizacion($memb)) {
            return redirect()->to('/flujos')->with('error', 'Sin acceso a fiscalización.');
        }

        $rows = db_connect()->table('fiscalizacion_solicitudes f')
            ->select('f.*, u.nombre AS solicitante_nombre')
            ->join('usuarios u', 'u.id = f.solicitado_por_id')
            ->where('f.empresa_id', $memb['empresa_id'])
            ->orderBy('f.created_at', 'DESC')
            ->get()->getResultArray();

        $visible = array_values(array_filter(
            $rows,
            static fn (array $r): bool => FlujosAcl::canViewFiscalizacion($memb, $r)
        ));

        return view('flujos/fiscalizacion_list', [
            'title'      => 'Flujos',
            'memb'       => $memb,
            'items'      => $visible,
            'canRequest' => FlujosAcl::canRequestFiscalizacion($memb),
            'canReview'  => FlujosAcl::canReviewFiscalizacion($memb),
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function createForm()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canRequestFiscalizacion($memb)) {
            return redirect()->to('/flujos/fiscalizacion')->with('error', 'Sin permiso.');
        }

        $docs = $this->visibleDocs($memb);
        $deptos = model(DepartamentoModel::class)->where('empresa_id', $memb['empresa_id'])->orderBy('nombre')->findAll();
        $sucursales = model(SucursalModel::class)->where('empresa_id', $memb['empresa_id'])->orderBy('nombre')->findAll();
        if ($memb['rol'] === Roles::JEFE_SUCURSAL) {
            $sucursales = array_values(array_filter(
                $sucursales,
                static fn ($s) => $s['id'] === $memb['sucursal_id']
            ));
        }

        return view('flujos/fiscalizacion_form', [
            'title'      => 'Flujos',
            'memb'       => $memb,
            'docs'       => $docs,
            'deptos'     => $deptos,
            'sucursales' => $sucursales,
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function store()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canRequestFiscalizacion($memb)) {
            return redirect()->to('/flujos/fiscalizacion')->with('error', 'Sin permiso.');
        }

        helper('id');
        $titulo = trim((string) $this->request->getPost('titulo'));
        $tipo   = (string) $this->request->getPost('tipo');
        $motivo = trim((string) $this->request->getPost('motivo')) ?: null;
        $depto  = trim((string) $this->request->getPost('departamento_id')) ?: null;
        $suc    = trim((string) $this->request->getPost('sucursal_id')) ?: null;
        $docIds = (array) $this->request->getPost('documento_id');
        $docIds = array_values(array_filter(array_map('strval', $docIds)));
        $submit = (string) $this->request->getPost('submit_now') === '1';

        if ($titulo === '' || ! in_array($tipo, ['LIQUIDACION', 'CONTRATO'], true) || $docIds === []) {
            return redirect()->to('/flujos/fiscalizacion/nueva')->with('error', 'Datos incompletos.');
        }

        if ($memb['rol'] === Roles::JEFE_SUCURSAL) {
            $suc = $memb['sucursal_id'];
        }

        $id    = new_id();
        $estado = $submit ? 'PENDIENTE' : 'BORRADOR';
        $now   = date('Y-m-d H:i:s');

        db_connect()->table('fiscalizacion_solicitudes')->insert([
            'id'                => $id,
            'empresa_id'        => $memb['empresa_id'],
            'departamento_id'   => $depto,
            'sucursal_id'       => $suc,
            'tipo'              => $tipo,
            'titulo'            => $titulo,
            'motivo'            => $motivo,
            'estado'            => $estado,
            'solicitado_por_id' => $memb['usuario_id'],
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        foreach ($docIds as $did) {
            db_connect()->table('fiscalizacion_documentos')->insert([
                'id'           => new_id(),
                'solicitud_id' => $id,
                'documento_id' => $did,
            ]);
        }

        $this->audit($memb, $id, 'CREATED', "{$tipo} · {$titulo}");
        if ($submit) {
            $this->audit($memb, $id, 'SUBMITTED');
        }

        return redirect()->to('/flujos/fiscalizacion/' . $id)->with('success', 'Solicitud creada.');
    }

    public function show(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $sol = db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->get()->getRowArray();
        if ($sol === null || ! FlujosAcl::canViewFiscalizacion($memb, $sol)) {
            return redirect()->to('/flujos/fiscalizacion')->with('error', 'Solicitud no encontrada.');
        }

        $docs = db_connect()->table('fiscalizacion_documentos fd')
            ->select('d.*')
            ->join('documentos d', 'd.id = fd.documento_id')
            ->where('fd.solicitud_id', $id)
            ->get()->getResultArray();

        $audit = db_connect()->table('fiscalizacion_auditoria a')
            ->select('a.*, u.nombre AS actor_nombre')
            ->join('usuarios u', 'u.id = a.actor_id')
            ->where('a.solicitud_id', $id)
            ->orderBy('a.created_at', 'ASC')
            ->get()->getResultArray();

        return view('flujos/fiscalizacion_show', [
            'title'     => 'Flujos',
            'memb'      => $memb,
            'sol'       => $sol,
            'docs'      => $docs,
            'audit'     => $audit,
            'canReview' => FlujosAcl::canReviewFiscalizacion($memb),
            'canSend'   => Roles::isMaster($memb['rol']),
            'canSubmit' => FlujosAcl::canRequestFiscalizacion($memb),
            'success'   => session()->getFlashdata('success'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    public function submit(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        $sol = db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->get()->getRowArray();
        if ($sol === null || ! FlujosAcl::canViewFiscalizacion($memb, $sol) || ! FlujosAcl::canRequestFiscalizacion($memb)) {
            return redirect()->to('/flujos/fiscalizacion')->with('error', 'Sin permiso.');
        }
        if (! in_array($sol['estado'], ['BORRADOR', 'RECHAZADA'], true)) {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with('error', 'Estado no permite enviar a revisión.');
        }

        db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->update([
            'estado'              => 'PENDIENTE',
            'revisado_por_id'     => null,
            'revisado_at'         => null,
            'comentario_revision' => null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        $this->audit($memb, $id, 'SUBMITTED');

        return redirect()->to('/flujos/fiscalizacion/' . $id)->with('success', 'Enviada a revisión Master.');
    }

    public function review(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! FlujosAcl::canReviewFiscalizacion($memb)) {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with('error', 'Solo Master aprueba o rechaza.');
        }

        $sol = db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->get()->getRowArray();
        if ($sol === null || $sol['empresa_id'] !== $memb['empresa_id'] || $sol['estado'] !== 'PENDIENTE') {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with('error', 'Solicitud no pendiente.');
        }

        $decision   = (string) $this->request->getPost('decision');
        $comentario = trim((string) $this->request->getPost('comentario')) ?: null;
        if (! in_array($decision, ['APPROVE', 'DENY'], true)) {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with('error', 'Decisión inválida.');
        }

        $approved = $decision === 'APPROVE';
        db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->update([
            'estado'              => $approved ? 'APROBADA' : 'RECHAZADA',
            'revisado_por_id'     => $memb['usuario_id'],
            'revisado_at'         => date('Y-m-d H:i:s'),
            'comentario_revision' => $comentario,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
        $this->audit($memb, $id, $approved ? 'APPROVED' : 'DENIED', $comentario);

        return redirect()->to('/flujos/fiscalizacion/' . $id)->with(
            'success',
            $approved ? 'Solicitud aprobada.' : 'Solicitud rechazada.'
        );
    }

    public function send(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }
        if (! Roles::isMaster($memb['rol'])) {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with('error', 'Solo Master registra el envío.');
        }

        $sol = db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->get()->getRowArray();
        if ($sol === null || $sol['empresa_id'] !== $memb['empresa_id']) {
            return redirect()->to('/flujos/fiscalizacion')->with('error', 'No encontrada.');
        }
        if ($sol['estado'] !== 'APROBADA') {
            return redirect()->to('/flujos/fiscalizacion/' . $id)->with(
                'error',
                'Se requiere autorización Master antes de exportar/enviar.'
            );
        }

        db_connect()->table('fiscalizacion_solicitudes')->where('id', $id)->update([
            'estado'     => 'ENVIADA',
            'enviado_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit($memb, $id, 'SENT', 'Exportación/envío autorizado registrado');

        return redirect()->to('/flujos/fiscalizacion/' . $id)->with('success', 'Envío/exportación registrado.');
    }

    /** @return list<array<string, mixed>> */
    private function visibleDocs(array $memb): array
    {
        $docs = model(DocumentoModel::class)->where('empresa_id', $memb['empresa_id'])->findAll();
        $authRows = model(DocumentoAutorizacionModel::class)->where('empresa_id', $memb['empresa_id'])->findAll();
        $authByDoc = [];
        foreach ($authRows as $a) {
            $authByDoc[$a['documento_id']][] = $a['usuario_id'];
        }
        $out = [];
        foreach ($docs as $doc) {
            if (DocumentAcl::canView($memb, [
                'empresa_id'             => $doc['empresa_id'],
                'departamento_id'        => $doc['departamento_id'],
                'sucursal_id'            => $doc['sucursal_id'],
                'autorizado_usuario_ids' => $authByDoc[$doc['id']] ?? [],
            ])) {
                $out[] = $doc;
            }
        }

        return $out;
    }
}
