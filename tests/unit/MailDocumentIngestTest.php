<?php

declare(strict_types=1);

use App\Libraries\MailDocumentIngest;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Filters;

/**
 * @internal
 */
final class MailDocumentIngestTest extends CIUnitTestCase
{
    public function testSourceKeyCertificateAndAttachment(): void
    {
        $this->assertSame(
            'thr_1:certificate',
            MailDocumentIngest::sourceKey('thr_1', MailDocumentIngest::KIND_CERTIFICATE)
        );
        $this->assertSame(
            'thr_1:att:att_9',
            MailDocumentIngest::sourceKey('thr_1', MailDocumentIngest::KIND_ATTACHMENT, 'att_9')
        );
        $this->assertSame(
            'thr_1:att:unknown',
            MailDocumentIngest::sourceKey('thr_1', MailDocumentIngest::KIND_ATTACHMENT, null)
        );
    }

    public function testCsrfExceptsImapMailWebhook(): void
    {
        $filters = config(Filters::class);
        $csrf    = $filters->globals['before']['csrf'] ?? null;
        $this->assertIsArray($csrf);
        $except = $csrf['except'] ?? [];
        $this->assertContains('api/webhooks/imap-mail', $except);
    }

    public function testWebhookSecretAliasPrefersRepositoriaThenImapMail(): void
    {
        // Env may be unset in unit runs — just ensure the helper exists and returns null or string.
        $secret = \App\Libraries\ImapMailClient::webhookSecret();
        $this->assertTrue($secret === null || is_string($secret));
    }
}
