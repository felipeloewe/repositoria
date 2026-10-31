<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Stub inbound webhook from imap-mail (Fase 3).
 * Optional shared secret: REPOSITORIA_WEBHOOK_SECRET.
 */
class Webhook extends BaseController
{
    public function imapMail()
    {
        $secret = env('REPOSITORIA_WEBHOOK_SECRET');
        if (is_string($secret) && $secret !== '') {
            $hdr = $this->request->getHeaderLine('X-Repositoria-Secret')
                ?: $this->request->getHeaderLine('Authorization');
            if (! hash_equals($secret, $hdr) && ! hash_equals('Bearer ' . $secret, $hdr)) {
                return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'unauthorized']);
            }
        }

        $payload = $this->request->getJSON(true);
        log_message('info', 'imap-mail webhook: {p}', [
            'p' => is_array($payload) ? json_encode($payload) : (string) $this->request->getBody(),
        ]);

        return $this->response->setJSON([
            'ok'      => true,
            'received' => true,
            'note'    => 'Stub — Repositoria acknowledges imap-mail events',
        ]);
    }
}
