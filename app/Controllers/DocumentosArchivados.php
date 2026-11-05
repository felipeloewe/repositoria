<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\DocumentArchiveService;
use App\Libraries\DocumentLifecycle;
use App\Libraries\DocumentStorage;
use App\Libraries\MembershipAuth;
use App\Libraries\Roles;
use App\Models\DocumentoArchivoRespaldoModel;
use App\Models\DocumentoModel;
use App\Models\DocumentoTipoModel;
use RuntimeException;

/**
 * Listado de archivados + panel de respaldo ZIP mensual (Master).
 */
class DocumentosArchivados extends BaseController
{
    /** @return array<string, mixed>|\CodeIgniter\HTTP\RedirectResponse */
    private function requireMaster()
    {
        $memb = (new MembershipAuth())->membresia();
        if ($memb === null) {
            return redirect()->to('/login');
        }
        if (! Roles::isMaster($memb['rol'])) {
            return redirect()->to('/documentos')->with('error', 'Solo Master gestiona archivados y respaldos.');
        }

        return $memb;
    }

    public function index()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $docs = model(DocumentoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->where('estado', DocumentLifecycle::ARCHIVADO)
            ->orderBy('archivado_at', 'DESC')
            ->findAll();

        // Solo general (+ socio si es_socio). Contratos tienen menú propio (v1 sin archivados dedicados).
        $docs = array_values(array_filter(
            $docs,
            static function (array $d) use ($memb): bool {
                $ambito = DocumentAcl::normalizeAmbito($d['ambito'] ?? null);
                if ($ambito === DocumentAcl::AMBITO_GENERAL) {
                    return true;
                }
                if ($ambito === DocumentAcl::AMBITO_SOCIO) {
                    return DocumentAcl::isSocio($memb);
                }

                return false;
            }
        ));

        $tipos = model(DocumentoTipoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->findAll();
        $tipoMap = [];
        foreach ($tipos as $t) {
            $tipoMap[$t['id']] = $t;
        }

        foreach ($docs as &$d) {
            $d['tipo'] = $tipoMap[$d['tipo_id'] ?? ''] ?? null;
            $d['en_disco'] = empty($d['blob_eliminado_at']);
        }
        unset($d);

        return view('documentos/archivados', [
            'title'   => 'Archivados',
            'memb'    => $memb,
            'docs'    => $docs,
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
        ]);
    }

    public function respaldo()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $pendientes = db_connect()->query(
            "SELECT DATE_FORMAT(archivado_at, '%Y-%m') AS periodo,
                    COUNT(*) AS docs,
                    COALESCE(SUM(tamano_bytes), 0) AS bytes
             FROM documentos
             WHERE empresa_id = ?
               AND estado = ?
               AND blob_eliminado_at IS NULL
               AND archivado_at IS NOT NULL
               AND (ambito = 'general' OR ambito IS NULL OR ambito = '')
             GROUP BY DATE_FORMAT(archivado_at, '%Y-%m')
             ORDER BY periodo DESC",
            [$memb['empresa_id'], DocumentLifecycle::ARCHIVADO]
        )->getResultArray();

        $hist = model(DocumentoArchivoRespaldoModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return view('documentos/respaldo_archivados', [
            'title'      => 'Respaldo archivados',
            'memb'       => $memb,
            'pendientes' => $pendientes,
            'historial'  => $hist,
            'success'    => session()->getFlashdata('success'),
            'error'      => session()->getFlashdata('error'),
        ]);
    }

    public function generarRespaldo()
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $periodo = trim((string) $this->request->getPost('periodo'));
        try {
            $result = (new DocumentArchiveService())->generarRespaldoMensual($memb, $periodo);
        } catch (RuntimeException $e) {
            return redirect()->to('/documentos/archivados/respaldo')->with('error', $e->getMessage());
        }

        return redirect()->to('/documentos/archivados/respaldo')->with(
            'success',
            'Respaldo ' . $periodo . ' generado (' . $result['docs'] . ' docs). Descárgalo y guárdalo en tu respaldo local; los archivos en servidor de ese mes fueron eliminados.'
        );
    }

    public function downloadRespaldo(string $id)
    {
        $memb = $this->requireMaster();
        if (! is_array($memb)) {
            return $memb;
        }

        $row = model(DocumentoArchivoRespaldoModel::class)
            ->where('id', $id)
            ->where('empresa_id', $memb['empresa_id'])
            ->first();
        if ($row === null) {
            return redirect()->to('/documentos/archivados/respaldo')->with('error', 'Respaldo no encontrado.');
        }

        try {
            $data = DocumentStorage::read((string) $row['ruta_zip']);
        } catch (\Throwable) {
            return redirect()->to('/documentos/archivados/respaldo')->with('error', 'ZIP no disponible en disco.');
        }

        // Marca descarga: habilita validación de Baja.
        model(DocumentoArchivoRespaldoModel::class)->update($id, [
            'descargado_at'    => date('Y-m-d H:i:s'),
            'descargado_por_id' => $memb['usuario_id'],
        ]);

        $name = 'archivados-' . $row['periodo'] . '.zip';

        return $this->response
            ->setHeader('Content-Type', 'application/zip')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"')
            ->setBody($data);
    }
}
