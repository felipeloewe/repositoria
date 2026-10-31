<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * HTTP client for parallel imap-mail service (Fase 3).
 * Env: IMAP_MAIL_BASE_URL or IMAP_MAIL_URL.
 * When unset or unreachable → in-process mock fixtures.
 */
final class ImapMailClient
{
    public const MOCK_ACCOUNT_ID = 'mac_demo_repositoria';
    public const MOCK_THREAD_ID  = 'thr_demo_liquidacion';
    public const MOCK_ATTACH_ID  = 'att_demo_liquidacion';

    public static function baseUrl(): ?string
    {
        $raw = env('IMAP_MAIL_BASE_URL') ?: env('IMAP_MAIL_URL') ?: '';
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        return rtrim($raw, '/');
    }

    public static function mode(): string
    {
        return self::baseUrl() ? 'live' : 'mock';
    }

    /**
     * @return array{ok: bool, mode: string, detail?: string}
     */
    public static function health(): array
    {
        $base = self::baseUrl();
        if ($base === null) {
            return [
                'ok'     => true,
                'mode'   => 'mock',
                'detail' => 'IMAP_MAIL_BASE_URL unset — using offline mock fixtures',
            ];
        }

        try {
            $res = self::httpGet($base . '/api/health', 2.5);
            if ($res['status'] < 200 || $res['status'] >= 300) {
                return ['ok' => false, 'mode' => 'live', 'detail' => 'health ' . $res['status']];
            }

            return ['ok' => true, 'mode' => 'live', 'detail' => $base];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mode' => 'live', 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return array{data: list<array<string, mixed>>, mode: string}
     */
    public static function listAccounts(string $usuarioId): array
    {
        return self::withFallback(
            static function () use ($usuarioId): array {
                $res  = self::mailFetch('/api/accounts?usuarioId=' . rawurlencode($usuarioId));
                $json = self::parseJson($res);

                return is_array($json) ? $json : [];
            },
            static fn (): array => [self::mockAccount($usuarioId)]
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{data: array<string, mixed>, mode: string}
     */
    public static function createAccount(array $input): array
    {
        if (self::baseUrl() === null) {
            $acc                 = self::mockAccount((string) $input['usuarioId']);
            $acc['email']        = (string) ($input['email'] ?? $acc['email']);
            $acc['displayName']  = $input['displayName'] ?? null;
            $acc['provider']     = ($input['provider'] ?? 'IMAP') === 'GMAIL' ? 'GMAIL' : 'IMAP';
            $acc['imapHost']     = $input['imapHost'] ?? null;
            $acc['imapPort']     = isset($input['imapPort']) ? (int) $input['imapPort'] : 993;

            return ['data' => $acc, 'mode' => 'mock'];
        }

        $res  = self::mailFetch('/api/accounts', 'POST', $input);
        $data = self::parseJson($res);

        return ['data' => is_array($data) ? $data : [], 'mode' => 'live'];
    }

    /**
     * @return array{data: list<array<string, mixed>>, mode: string}
     */
    public static function listThreads(string $usuarioId): array
    {
        return self::withFallback(
            static function () use ($usuarioId): array {
                $res  = self::mailFetch('/api/threads?usuarioId=' . rawurlencode($usuarioId));
                $json = self::parseJson($res);

                return is_array($json) ? $json : [];
            },
            static fn (): array => [self::mockThreadSummary($usuarioId)]
        );
    }

    /**
     * @return array{data: array<string, mixed>, mode: string}
     */
    public static function getThread(string $threadId, string $usuarioId): array
    {
        return self::withFallback(
            static function () use ($threadId): array {
                $res  = self::mailFetch('/api/threads/' . rawurlencode($threadId));
                $json = self::parseJson($res);

                return is_array($json) ? $json : [];
            },
            static function () use ($threadId, $usuarioId): array {
                if ($threadId !== self::MOCK_THREAD_ID) {
                    throw new \RuntimeException('Hilo no encontrado (mock)');
                }

                return self::mockThreadDetail($usuarioId);
            }
        );
    }

    /**
     * @return array{bytes: string, filename: string, mimeType: string, mode: string}
     */
    public static function downloadAttachment(string $attachmentId): array
    {
        if (self::baseUrl() === null) {
            if ($attachmentId !== self::MOCK_ATTACH_ID) {
                throw new \RuntimeException('Adjunto no encontrado (mock)');
            }

            return [
                'bytes'    => self::mockAttachmentPdf(),
                'filename' => 'liquidacion-marzo.pdf',
                'mimeType' => 'application/pdf',
                'mode'     => 'mock',
            ];
        }

        $res = self::mailFetch('/api/attachments/' . rawurlencode($attachmentId) . '/export', 'GET', null, false);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException('Attachment export failed (' . $res['status'] . ')');
        }
        $cd       = $res['headers']['content-disposition'] ?? '';
        $filename = 'adjunto-' . $attachmentId;
        if (preg_match('/filename="?([^";]+)"?/i', $cd, $m)) {
            $filename = $m[1];
        }

        return [
            'bytes'    => $res['body'],
            'filename' => $filename,
            'mimeType' => $res['headers']['content-type'] ?? 'application/octet-stream',
            'mode'     => 'live',
        ];
    }

    /**
     * @return array{bytes: string, filename: string, mode: string}
     */
    public static function downloadCertificate(string $threadId): array
    {
        if (self::baseUrl() === null) {
            return [
                'bytes'    => self::mockCertificatePdf('Liquidación marzo — revisión'),
                'filename' => 'certificado-hilo-' . $threadId . '.pdf',
                'mode'     => 'mock',
            ];
        }

        $path = '/api/threads/' . rawurlencode($threadId) . '/certificate';
        $res  = self::mailFetch($path, 'GET', null, false);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            // generate then retry
            self::mailFetch($path, 'POST', []);
            $res = self::mailFetch($path, 'GET', null, false);
            if ($res['status'] < 200 || $res['status'] >= 300) {
                throw new \RuntimeException('Certificate download failed (' . $res['status'] . ')');
            }
        }

        return [
            'bytes'    => $res['body'],
            'filename' => 'certificado-hilo-' . $threadId . '.pdf',
            'mode'     => 'live',
        ];
    }

    /**
     * @template T
     *
     * @param callable(): T $live
     * @param callable(): T $mock
     *
     * @return array{data: T, mode: string}
     */
    private static function withFallback(callable $live, callable $mock): array
    {
        if (self::baseUrl() === null) {
            return ['data' => $mock(), 'mode' => 'mock'];
        }

        try {
            return ['data' => $live(), 'mode' => 'live'];
        } catch (\Throwable) {
            return ['data' => $mock(), 'mode' => 'mock'];
        }
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private static function mailFetch(string $path, string $method = 'GET', ?array $jsonBody = null, bool $expectJson = true): array
    {
        $base = self::baseUrl();
        if ($base === null) {
            throw new \RuntimeException('IMAP_MAIL_BASE_URL no configurado');
        }
        $url = $base . (str_starts_with($path, '/') ? $path : '/' . $path);

        return self::httpRequest($url, $method, $jsonBody, 5.0);
    }

    /**
     * @return mixed
     */
    private static function parseJson(array $res)
    {
        $body = json_decode($res['body'], true);
        if ($res['status'] < 200 || $res['status'] >= 300 || ! is_array($body) || ! ($body['ok'] ?? false)) {
            $err = is_array($body) && isset($body['error']) ? (string) $body['error'] : 'imap-mail HTTP ' . $res['status'];

            throw new \RuntimeException($err);
        }

        return $body['data'] ?? null;
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private static function httpGet(string $url, float $timeout): array
    {
        return self::httpRequest($url, 'GET', null, $timeout);
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private static function httpRequest(string $url, string $method, ?array $jsonBody, float $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headers = ['Accept: application/json'];
        $opts    = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => (int) ceil($timeout),
            CURLOPT_CONNECTTIMEOUT => (int) max(1, floor($timeout)),
            CURLOPT_HEADER         => true,
        ];

        if ($jsonBody !== null) {
            $payload   = json_encode($jsonBody, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = $payload;
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);

            throw new \RuntimeException($err ?: 'curl error');
        }

        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerRaw = substr($raw, 0, $headerSize);
        $body      = substr($raw, $headerSize);
        $hdrs      = [];
        foreach (explode("\r\n", $headerRaw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))] = trim($v);
            }
        }

        return ['status' => $status, 'body' => $body === false ? '' : $body, 'headers' => $hdrs];
    }

    /** @return array<string, mixed> */
    private static function mockAccount(string $usuarioId): array
    {
        return [
            'id'           => self::MOCK_ACCOUNT_ID,
            'usuarioId'    => $usuarioId,
            'email'        => 'demo@repositoria.local',
            'displayName'  => 'Demo Repositoria (mock)',
            'provider'     => 'IMAP',
            'imapHost'     => 'imap.example.local',
            'imapPort'     => 993,
            'syncStatus'   => 'IDLE',
            'lastSyncedAt' => '2026-03-15T16:05:00.000Z',
            'createdAt'    => '2026-03-01T00:00:00.000Z',
        ];
    }

    /** @return array<string, mixed> */
    private static function mockThreadSummary(string $usuarioId): array
    {
        return [
            'id'            => self::MOCK_THREAD_ID,
            'accountId'     => self::MOCK_ACCOUNT_ID,
            'rootMessageId' => 'root.demo@repositoria.local',
            'subject'       => 'Liquidación marzo — revisión',
            'participants'  => [
                'Ana Pérez <ana@empresa.cl>',
                'Contabilidad <conta@empresa.cl>',
            ],
            'messageCount'  => 2,
            'lastMessageAt' => '2026-03-15T16:00:00.000Z',
            'account'       => [
                'id'        => self::MOCK_ACCOUNT_ID,
                'email'     => 'demo@repositoria.local',
                'usuarioId' => $usuarioId,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function mockThreadDetail(string $usuarioId): array
    {
        $summary = self::mockThreadSummary($usuarioId);

        return array_merge($summary, [
            'account'  => [
                'id'        => self::MOCK_ACCOUNT_ID,
                'email'     => 'demo@repositoria.local',
                'usuarioId' => $usuarioId,
                'provider'  => 'IMAP',
            ],
            'messages' => [
                [
                    'id'          => 'msg_demo_1',
                    'messageId'   => 'root.demo@repositoria.local',
                    'folder'      => 'INBOX',
                    'fromAddr'    => 'Ana Pérez <ana@empresa.cl>',
                    'toAddrs'     => 'Contabilidad <conta@empresa.cl>',
                    'subject'     => 'Liquidación marzo — revisión',
                    'bodyText'    => 'Hola, adjunto borrador de liquidación de marzo para revisión del departamento.',
                    'date'        => '2026-03-15T10:00:00.000Z',
                    'attachments' => [
                        [
                            'id'       => self::MOCK_ATTACH_ID,
                            'filename' => 'liquidacion-marzo.pdf',
                            'mimeType' => 'application/pdf',
                            'size'     => 48,
                        ],
                    ],
                ],
                [
                    'id'          => 'msg_demo_2',
                    'messageId'   => 'reply.demo@repositoria.local',
                    'folder'      => 'SENT',
                    'fromAddr'    => 'Contabilidad <conta@empresa.cl>',
                    'toAddrs'     => 'Ana Pérez <ana@empresa.cl>',
                    'subject'     => 'Re: Liquidación marzo — revisión',
                    'bodyText'    => 'Recibido. Queda archivado en el repositorio del departamento.',
                    'date'        => '2026-03-15T16:00:00.000Z',
                    'attachments' => [],
                ],
            ],
        ]);
    }

    private static function mockAttachmentPdf(): string
    {
        return "%PDF-1.4\n% mock attachment Repositoria Fase 3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    }

    private static function mockCertificatePdf(string $subject): string
    {
        $safe = preg_replace('/[^\w\s\-áéíóúñÁÉÍÓÚÑ]/ui', '', $subject) ?: 'hilo';

        return "%PDF-1.4\n% Certificado de hilo — {$safe}\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<<>>\n%%EOF\n";
    }
}
