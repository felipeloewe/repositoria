<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ImapMailClient;
use App\Libraries\MailDocumentIngest;

/**
 * Inbound webhook from imap-mail (Fase 3).
 * Shared secret: REPOSITORIA_WEBHOOK_SECRET or IMAP_MAIL_WEBHOOK_SECRET.
 */
class Webhook extends BaseController
{
    public function imapMail()
    {
        $secret = ImapMailClient::webhookSecret();
        if ($secret !== null) {
            $hdr = $this->request->getHeaderLine('X-Repositoria-Secret')
                ?: $this->request->getHeaderLine('Authorization');
            if (! hash_equals($secret, $hdr) && ! hash_equals('Bearer ' . $secret, $hdr)) {
                return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'unauthorized']);
            }
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = [];
        }

        log_message('info', 'imap-mail webhook: {p}', [
            'p' => json_encode($payload),
        ]);

        $event     = (string) ($payload['event'] ?? '');
        $usuarioId = (string) ($payload['usuarioId'] ?? '');
        $threadId  = (string) ($payload['threadId'] ?? '');
        $files     = $payload['files'] ?? [];
        $suggested = isset($payload['suggestedSeccionId'])
            ? (string) $payload['suggestedSeccionId']
            : null;

        $ingested = [];
        $skipped  = [];

        if ($usuarioId === '' || ! is_array($files) || $files === []) {
            return $this->response->setJSON([
                'ok'       => true,
                'received' => true,
                'event'    => $event,
                'ingested' => [],
                'skipped'  => [['reason' => 'no usuarioId or files']],
            ]);
        }

        $ingest = new MailDocumentIngest();
        $target = $ingest->resolveTarget($usuarioId, $suggested);
        if ($target === null) {
            return $this->response->setJSON([
                'ok'       => true,
                'received' => true,
                'event'    => $event,
                'ingested' => [],
                'skipped'  => [['reason' => 'no seccion destino (suggestedSeccionId o preferencia Correo)']],
            ]);
        }

        foreach ($files as $fileMeta) {
            if (! is_array($fileMeta)) {
                $skipped[] = ['reason' => 'invalid file meta'];
                continue;
            }

            $kindRaw = strtolower((string) ($fileMeta['kind'] ?? ''));
            $kind    = str_contains($kindRaw, 'cert')
                ? MailDocumentIngest::KIND_CERTIFICATE
                : MailDocumentIngest::KIND_ATTACHMENT;

            $downloadPath = (string) ($fileMeta['downloadPath'] ?? '');
            $filename     = (string) ($fileMeta['filename'] ?? '');
            $mime         = (string) ($fileMeta['mimeType'] ?? 'application/octet-stream');
            $attachmentId = isset($fileMeta['attachmentId'])
                ? (string) $fileMeta['attachmentId']
                : $this->guessAttachmentId($downloadPath);

            if ($downloadPath === '') {
                $skipped[] = ['reason' => 'missing downloadPath', 'filename' => $filename];
                continue;
            }

            $effectiveThread = $threadId !== '' ? $threadId : $this->guessThreadId($downloadPath);
            if ($effectiveThread === '') {
                $skipped[] = ['reason' => 'missing threadId', 'filename' => $filename];
                continue;
            }

            try {
                $downloaded = ImapMailClient::downloadByPath(
                    $downloadPath,
                    $filename !== '' ? $filename : null
                );

                $result = $ingest->saveToSection(
                    $target['memb'],
                    $target['seccion_id'],
                    $effectiveThread,
                    $kind,
                    [
                        'bytes'    => $downloaded['bytes'],
                        'filename' => $downloaded['filename'],
                        'mime'     => $mime !== '' ? $mime : $downloaded['mimeType'],
                        'titulo'   => $kind === MailDocumentIngest::KIND_CERTIFICATE
                            ? 'Certificado de hilo'
                            : null,
                    ],
                    $kind === MailDocumentIngest::KIND_ATTACHMENT ? $attachmentId : null
                );

                $ingested[] = [
                    'documento_id' => $result['documento_id'],
                    'created'      => $result['created'],
                    'source_key'   => $result['source_key'],
                    'kind'         => $kind,
                ];
            } catch (\Throwable $e) {
                log_message('error', 'imap-mail webhook ingest: {m}', ['m' => $e->getMessage()]);
                $skipped[] = [
                    'reason'   => $e->getMessage(),
                    'filename' => $filename,
                    'path'     => $downloadPath,
                ];
            }
        }

        return $this->response->setJSON([
            'ok'       => true,
            'received' => true,
            'event'    => $event,
            'ingested' => $ingested,
            'skipped'  => $skipped,
        ]);
    }

    private function guessAttachmentId(string $path): ?string
    {
        if (preg_match('#/attachments/([^/]+)/export#', $path, $m)) {
            return $m[1];
        }

        return null;
    }

    private function guessThreadId(string $path): string
    {
        if (preg_match('#/threads/([^/]+)/#', $path, $m)) {
            return $m[1];
        }

        return '';
    }
}
