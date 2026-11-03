<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentAcl;
use App\Libraries\ImapMailClient;
use App\Libraries\MailDocumentIngest;
use App\Libraries\MembershipAuth;
use App\Models\CorreoPreferenciaModel;
use App\Models\DocumentoSeccionModel;

/** Fase 3 — UI Correo + vínculo a repositorio. */
class Correo extends BaseController
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

    /** @return list<array{id: string, label: string}> */
    private function uploadableSections(array $memb): array
    {
        $secciones = model(DocumentoSeccionModel::class)
            ->where('empresa_id', $memb['empresa_id'])
            ->orderBy('nombre', 'ASC')
            ->findAll();

        $deptos = model(\App\Models\DepartamentoModel::class)
            ->where('empresa_id', $memb['empresa_id'])->findAll();
        $deptoMap = [];
        foreach ($deptos as $d) {
            $deptoMap[$d['id']] = $d['nombre'];
        }
        $sucs = model(\App\Models\SucursalModel::class)
            ->where('empresa_id', $memb['empresa_id'])->findAll();
        $sucMap = [];
        foreach ($sucs as $s) {
            $sucMap[$s['id']] = $s['nombre'];
        }

        $out = [];
        foreach ($secciones as $sec) {
            if (! DocumentAcl::canUpload($memb, [
                'sucursal_id'     => $sec['sucursal_id'],
                'departamento_id' => $sec['departamento_id'],
            ])) {
                continue;
            }
            $out[] = [
                'id'    => $sec['id'],
                'label' => ($deptoMap[$sec['departamento_id']] ?? '?')
                    . ' · ' . $sec['nombre']
                    . ' · ' . ($sec['sucursal_id'] ? ($sucMap[$sec['sucursal_id']] ?? 'Sucursal') : 'Nivel departamento'),
            ];
        }

        return $out;
    }

    public function index()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $health    = ImapMailClient::health();
        $threads   = ImapMailClient::listThreads($memb['usuario_id']);
        $accounts  = ImapMailClient::listAccounts($memb['usuario_id']);

        return view('correo/index', [
            'title'    => 'Correo',
            'memb'     => $memb,
            'health'   => $health,
            'mode'     => $threads['mode'],
            'threads'  => $threads['data'],
            'accounts' => $accounts['data'],
            'success'  => session()->getFlashdata('success'),
            'error'    => session()->getFlashdata('error'),
        ]);
    }

    public function configuracion()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $health   = ImapMailClient::health();
        $accounts = ImapMailClient::listAccounts($memb['usuario_id']);
        $sections = $this->uploadableSections($memb);
        $pref     = model(CorreoPreferenciaModel::class)
            ->findFor($memb['usuario_id'], $memb['empresa_id']);

        return view('correo/configuracion', [
            'title'           => 'Correo',
            'memb'            => $memb,
            'health'          => $health,
            'mode'            => $accounts['mode'],
            'accounts'        => $accounts['data'],
            'uploadableSections' => $sections,
            'preferencia'     => $pref,
            'gmailOAuthUrl'   => ImapMailClient::gmailOAuthStartUrl($memb['usuario_id']),
            'success'         => session()->getFlashdata('success'),
            'error'           => session()->getFlashdata('error'),
        ]);
    }

    public function linkMailbox()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $provider = (string) $this->request->getPost('provider') === 'GMAIL' ? 'GMAIL' : 'IMAP';
        $host     = trim((string) $this->request->getPost('imap_host')) ?: null;

        if ($email === '') {
            return redirect()->to('/correo/configuracion')->with('error', 'Correo requerido.');
        }
        if ($provider === 'IMAP' && $host === null && ImapMailClient::baseUrl() !== null) {
            return redirect()->to('/correo/configuracion')->with('error', 'Host IMAP requerido.');
        }

        try {
            $result = ImapMailClient::createAccount([
                'usuarioId'    => $memb['usuario_id'],
                'email'        => $email,
                'displayName'  => trim((string) $this->request->getPost('display_name')) ?: null,
                'provider'     => $provider,
                'imapHost'     => $host,
                'imapPort'     => (int) ($this->request->getPost('imap_port') ?: 993),
                'imapUser'     => trim((string) $this->request->getPost('imap_user')) ?: $email,
                'imapPassword' => trim((string) $this->request->getPost('imap_password')) ?: null,
            ]);

            return redirect()->to('/correo/configuracion')->with(
                'success',
                'Casilla vinculada (' . $result['mode'] . '): ' . ($result['data']['email'] ?? $email)
            );
        } catch (\Throwable $e) {
            return redirect()->to('/correo/configuracion')->with('error', $e->getMessage());
        }
    }

    public function savePreferencia()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $seccionId = (string) $this->request->getPost('seccion_id');
        if ($seccionId === '') {
            return redirect()->to('/correo/configuracion')->with('error', 'Sección requerida.');
        }

        $allowed = array_column($this->uploadableSections($memb), 'id');
        if (! in_array($seccionId, $allowed, true)) {
            return redirect()->to('/correo/configuracion')->with('error', 'Sección no permitida.');
        }

        model(CorreoPreferenciaModel::class)->upsertSeccion(
            $memb['usuario_id'],
            $memb['empresa_id'],
            $seccionId
        );

        return redirect()->to('/correo/configuracion')->with(
            'success',
            'Sección destino guardada para adjuntos y certificados automáticos.'
        );
    }

    public function syncAccount()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $accountId = (string) $this->request->getPost('account_id');
        if ($accountId === '') {
            return redirect()->to('/correo/configuracion')->with('error', 'Casilla requerida.');
        }

        $accounts = ImapMailClient::listAccounts($memb['usuario_id']);
        $owned    = false;
        foreach ($accounts['data'] as $a) {
            if (($a['id'] ?? null) === $accountId
                && (($a['usuarioId'] ?? null) === $memb['usuario_id'] || ! isset($a['usuarioId']))
            ) {
                $owned = true;
                break;
            }
        }
        if (! $owned && $accounts['mode'] === 'live') {
            return redirect()->to('/correo/configuracion')->with('error', 'Casilla no pertenece a tu usuario.');
        }

        try {
            $result = ImapMailClient::syncAccount($accountId);

            return redirect()->to('/correo')->with(
                'success',
                'Sincronización solicitada (' . $result['mode'] . ').'
            );
        } catch (\Throwable $e) {
            return redirect()->to('/correo/configuracion')->with('error', $e->getMessage());
        }
    }

    public function thread(string $id)
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        try {
            $result = ImapMailClient::getThread($id, $memb['usuario_id']);
        } catch (\Throwable $e) {
            return redirect()->to('/correo')->with('error', $e->getMessage());
        }

        $thread = $result['data'];
        if (($thread['account']['usuarioId'] ?? null) !== $memb['usuario_id']) {
            return redirect()->to('/correo')->with('error', 'Este hilo no pertenece a tu casilla.');
        }

        return view('correo/thread', [
            'title'              => 'Correo',
            'memb'               => $memb,
            'thread'             => $thread,
            'mode'               => $result['mode'],
            'uploadableSections' => $this->uploadableSections($memb),
            'success'            => session()->getFlashdata('success'),
            'error'              => session()->getFlashdata('error'),
        ]);
    }

    public function saveAttachment()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $attachmentId = (string) $this->request->getPost('attachment_id');
        $threadId     = (string) $this->request->getPost('thread_id');
        $seccionId    = (string) $this->request->getPost('seccion_id');
        $tituloRaw    = trim((string) $this->request->getPost('titulo'));

        if ($attachmentId === '' || $seccionId === '') {
            return redirect()->to('/correo/' . $threadId)->with('error', 'Adjunto y sección obligatorios.');
        }

        try {
            $this->assertThreadOwner($memb, $threadId);
            $file = ImapMailClient::downloadAttachment($attachmentId);
            $result = (new MailDocumentIngest())->saveToSection(
                $memb,
                $seccionId,
                $threadId,
                MailDocumentIngest::KIND_ATTACHMENT,
                [
                    'bytes'    => $file['bytes'],
                    'filename' => $file['filename'],
                    'mime'     => $file['mimeType'],
                    'titulo'   => null,
                ],
                $attachmentId,
                $tituloRaw !== '' ? $tituloRaw : null
            );

            $msg = $result['created']
                ? 'Adjunto guardado en repositorio (doc ' . $result['documento_id'] . ').'
                : 'Adjunto ya estaba en el repositorio (doc ' . $result['documento_id'] . ').';

            return redirect()->to('/correo/' . $threadId)->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->to('/correo/' . $threadId)->with('error', $e->getMessage());
        }
    }

    public function saveCertificate()
    {
        $memb = $this->requireMemb();
        if (! is_array($memb)) {
            return $memb;
        }

        $threadId  = (string) $this->request->getPost('thread_id');
        $seccionId = (string) $this->request->getPost('seccion_id');
        $tituloRaw = trim((string) $this->request->getPost('titulo'));

        if ($threadId === '' || $seccionId === '') {
            return redirect()->to('/correo')->with('error', 'Hilo y sección obligatorios.');
        }

        try {
            $this->assertThreadOwner($memb, $threadId);
            $file = ImapMailClient::downloadCertificate($threadId);
            $result = (new MailDocumentIngest())->saveToSection(
                $memb,
                $seccionId,
                $threadId,
                MailDocumentIngest::KIND_CERTIFICATE,
                [
                    'bytes'    => $file['bytes'],
                    'filename' => $file['filename'],
                    'mime'     => 'application/pdf',
                    'titulo'   => 'Certificado de hilo',
                ],
                null,
                $tituloRaw !== '' ? $tituloRaw : ('Certificado de hilo · ' . $threadId)
            );

            $msg = $result['created']
                ? 'Certificado PDF guardado en repositorio (doc ' . $result['documento_id'] . ').'
                : 'Certificado ya estaba en el repositorio (doc ' . $result['documento_id'] . ').';

            return redirect()->to('/correo/' . $threadId)->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->to('/correo/' . $threadId)->with('error', $e->getMessage());
        }
    }

    /** @param array<string, mixed> $memb */
    private function assertThreadOwner(array $memb, string $threadId): void
    {
        if ($threadId === '') {
            return;
        }
        $thread = ImapMailClient::getThread($threadId, $memb['usuario_id']);
        if (($thread['data']['account']['usuarioId'] ?? null) !== $memb['usuario_id']) {
            throw new \RuntimeException('Este hilo no pertenece a tu casilla.');
        }
    }
}
