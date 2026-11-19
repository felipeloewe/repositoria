<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Envío de correos Equity (verificación). Si SMTP no está configurado, registra en log.
 */
final class EquityMail
{
    public static function enviarVerificacion(string $email, string $nombre, string $verifyUrl, ?string $tempPassword = null): bool
    {
        $fromEmail = (string) (env('email.fromEmail') ?: env('EMAIL_FROM') ?: '');
        $fromName  = (string) (env('email.fromName') ?: 'Equity');

        $body = "Hola {$nombre},\n\n"
            . "Te invitaron a Equity para declarar tu información patrimonial.\n\n"
            . "1) Verifica tu correo abriendo este enlace:\n{$verifyUrl}\n\n";
        if ($tempPassword !== null && $tempPassword !== '') {
            $body .= "2) Ingresa con tu correo y esta clave temporal:\n{$tempPassword}\n"
                . "   (al entrar deberás cambiarla)\n\n";
        }
        $body .= "Saludos,\nEquity\n";

        if ($fromEmail === '') {
            log_message('info', 'EquityMail verificación (sin SMTP) para {email}: {url} pass={pass}', [
                'email' => $email,
                'url'   => $verifyUrl,
                'pass'  => $tempPassword ?? '',
            ]);

            return false;
        }

        try {
            $emailSvc = \Config\Services::email();
            $emailSvc->setFrom($fromEmail, $fromName);
            $emailSvc->setTo($email);
            $emailSvc->setSubject('Verifica tu correo · Equity');
            $emailSvc->setMessage(nl2br(esc($body)));
            $emailSvc->setAltMessage($body);
            $ok = $emailSvc->send(false);
            if (! $ok) {
                log_message('error', 'EquityMail send failed: {dbg}', ['dbg' => $emailSvc->printDebugger(['headers', 'subject'])]);
            }

            return (bool) $ok;
        } catch (\Throwable $e) {
            log_message('error', 'EquityMail exception: {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }
}
