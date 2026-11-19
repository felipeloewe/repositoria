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

        return self::send($fromEmail, $fromName, $email, 'Verifica tu correo · Equity', $body);
    }

    /**
     * Invitación al Admin Master de una empresa creada por Superadmin.
     * Debe verificar correo, entrar con clave temporal y autogestionar (cambiar) su password.
     */
    public static function enviarInvitacionAdmin(
        string $email,
        string $nombre,
        string $verifyUrl,
        ?string $tempPassword,
        string $empresaNombre
    ): bool {
        $fromEmail = (string) (env('email.fromEmail') ?: env('EMAIL_FROM') ?: '');
        $fromName  = (string) (env('email.fromName') ?: 'Equity');

        $body = "Hola {$nombre},\n\n"
            . "Quedaste como administrador de «{$empresaNombre}» en Equity.\n\n"
            . "1) Verifica tu correo abriendo este enlace:\n{$verifyUrl}\n\n";
        if ($tempPassword !== null && $tempPassword !== '') {
            $body .= "2) Ingresa con tu correo y esta clave temporal:\n{$tempPassword}\n"
                . "   Al entrar deberás definir tu propia clave (autogestión).\n\n";
        }
        $body .= "3) Desde Administración → Grupo / Usuarios podrás inscribir a las personas que declaran patrimonio.\n\n"
            . "Saludos,\nEquity\n";

        if ($fromEmail === '') {
            log_message('info', 'EquityMail admin invite (sin SMTP) para {email}: {url} pass={pass} empresa={emp}', [
                'email' => $email,
                'url'   => $verifyUrl,
                'pass'  => $tempPassword ?? '',
                'emp'   => $empresaNombre,
            ]);

            return false;
        }

        return self::send($fromEmail, $fromName, $email, 'Invitación Admin · Equity', $body);
    }

    private static function send(string $fromEmail, string $fromName, string $to, string $subject, string $body): bool
    {
        try {
            $emailSvc = \Config\Services::email();
            $emailSvc->setFrom($fromEmail, $fromName);
            $emailSvc->setTo($to);
            $emailSvc->setSubject($subject);
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
