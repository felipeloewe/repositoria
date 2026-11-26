<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Envío de correos Equity (verificación / invitación Admin).
 * Preferir SMTP autenticado; si no hay fromEmail, registra en log.
 */
final class EquityMail
{
    public static function enviarVerificacion(string $email, string $nombre, string $verifyUrl, ?string $tempPassword = null): bool
    {
        $fromEmail = (string) (env('email.fromEmail') ?: env('EMAIL_FROM') ?: '');
        $fromName  = (string) (env('email.fromName') ?: 'Equity');

        $plain = self::plainVerificacion($nombre, $verifyUrl, $tempPassword, null);
        $html  = self::htmlBranded([
            'preheader'   => 'Verifica tu correo para entrar a Equity',
            'greeting'    => 'Hola ' . self::safeName($nombre) . ',',
            'intro'       => 'Te invitaron a <strong>Equity</strong> para declarar tu información patrimonial.',
            'ctaLabel'    => 'Verificar correo',
            'ctaUrl'      => $verifyUrl,
            'tempPassword'=> $tempPassword,
            'footerNote'  => 'Al verificar podrás ingresar y registrar tus Activos y Pasivos.',
        ]);

        if ($fromEmail === '') {
            log_message('info', 'EquityMail verificación (sin SMTP) para {email}: {url} pass={pass}', [
                'email' => $email,
                'url'   => $verifyUrl,
                'pass'  => $tempPassword ?? '',
            ]);

            return false;
        }

        return self::send($fromEmail, $fromName, $email, 'Verifica tu correo · Equity', $plain, $html);
    }

    /**
     * Invitación al Admin Master de una empresa creada por Superadmin.
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

        $plain = self::plainVerificacion($nombre, $verifyUrl, $tempPassword, $empresaNombre);
        $html  = self::htmlBranded([
            'preheader'    => 'Invitación Admin · Equity',
            'greeting'     => 'Hola ' . self::safeName($nombre) . ',',
            'intro'        => 'Quedaste como administrador de <strong>' . esc($empresaNombre) . '</strong> en Equity.',
            'ctaLabel'     => 'Verificar correo',
            'ctaUrl'       => $verifyUrl,
            'tempPassword' => $tempPassword,
            'footerNote'   => 'Después de verificar, define tu clave e ingresa a registrar Activos y Pasivos. Desde Grupo / Usuarios podrás invitar a quienes declaran.',
        ]);

        if ($fromEmail === '') {
            log_message('info', 'EquityMail admin invite (sin SMTP) para {email}: {url} pass={pass} empresa={emp}', [
                'email' => $email,
                'url'   => $verifyUrl,
                'pass'  => $tempPassword ?? '',
                'emp'   => $empresaNombre,
            ]);

            return false;
        }

        return self::send($fromEmail, $fromName, $email, 'Invitación Admin · Equity', $plain, $html);
    }

    /**
     * @param array{
     *   preheader: string,
     *   greeting: string,
     *   intro: string,
     *   ctaLabel: string,
     *   ctaUrl: string,
     *   tempPassword?: ?string,
     *   footerNote?: string
     * } $data
     */
    private static function htmlBranded(array $data): string
    {
        $navy   = '#1B2A6B';
        $teal   = '#2EC4C6';
        $ink    = '#121a33';
        $muted  = '#5b6b86';
        $bg     = '#eef3fb';
        $ctaUrl = (string) $data['ctaUrl'];
        $logo   = rtrim(site_url('/'), '/') . '/assets/brand/app-icon-256.png';
        $pass   = trim((string) ($data['tempPassword'] ?? ''));
        $footer = (string) ($data['footerNote'] ?? '');

        $passBlock = '';
        if ($pass !== '') {
            $passBlock = '<tr><td style="padding:0 28px 8px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:15px;line-height:1.55;color:' . $ink . '">'
                . 'Usa esta <strong>clave temporal</strong> al ingresar (deberás cambiarla):'
                . '</td></tr>'
                . '<tr><td style="padding:0 28px 20px">'
                . '<div style="display:inline-block;background:#f3fafb;border:1px solid rgba(46,196,198,.35);border-radius:12px;padding:12px 16px;font-family:Consolas,Monaco,monospace;font-size:16px;font-weight:700;letter-spacing:.04em;color:' . $navy . '">'
                . esc($pass)
                . '</div></td></tr>';
        }

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">'
            . '<title>Equity</title></head>'
            . '<body style="margin:0;padding:0;background:' . $bg . ';">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . esc($data['preheader']) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $bg . ';padding:32px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:24px;border:1px solid rgba(27,42,107,.08);box-shadow:0 10px 30px rgba(27,42,107,.08);overflow:hidden">'
            . '<tr><td style="background:linear-gradient(135deg,' . $navy . ' 0%,#1F529E 55%,#268CB8 100%);padding:22px 28px;text-align:center">'
            . '<img src="' . esc($logo) . '" width="56" height="56" alt="Equity" style="display:inline-block;border:0;border-radius:14px">'
            . '<div style="margin-top:12px;font-family:\'Space Grotesk\',\'Plus Jakarta Sans\',Arial,sans-serif;font-size:22px;font-weight:700;letter-spacing:.02em;color:#ffffff">EQUITY</div>'
            . '<div style="margin-top:4px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:13px;color:rgba(255,255,255,.85)">Información financiera</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 28px 8px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:16px;line-height:1.55;color:' . $ink . '">'
            . esc($data['greeting'])
            . '</td></tr>'
            . '<tr><td style="padding:0 28px 18px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:15px;line-height:1.55;color:' . $muted . '">'
            . $data['intro']
            . '</td></tr>'
            . '<tr><td align="center" style="padding:8px 28px 22px">'
            . '<a href="' . esc($ctaUrl) . '" style="display:inline-block;background:' . $teal . ';color:#ffffff;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:15px;font-weight:700;text-decoration:none;padding:14px 28px;border-radius:12px;box-shadow:0 10px 20px rgba(46,196,198,.28)">'
            . esc($data['ctaLabel'])
            . '</a></td></tr>'
            . $passBlock
            . ($footer !== ''
                ? '<tr><td style="padding:0 28px 8px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:14px;line-height:1.55;color:' . $muted . '">' . esc($footer) . '</td></tr>'
                : '')
            . '<tr><td style="padding:8px 28px 12px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:12px;line-height:1.5;color:' . $muted . '">'
            . 'Si el botón no funciona, copia y pega este enlace en tu navegador:<br>'
            . '<a href="' . esc($ctaUrl) . '" style="color:#1F529E;word-break:break-all">' . esc($ctaUrl) . '</a>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 28px 24px;border-top:1px solid rgba(27,42,107,.08);font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:12px;color:' . $muted . '">'
            . 'Equity · equity.booklet.cl'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function plainVerificacion(string $nombre, string $verifyUrl, ?string $tempPassword, ?string $empresaNombre): string
    {
        $body = 'Hola ' . self::safeName($nombre) . ",\n\n";
        if ($empresaNombre !== null && $empresaNombre !== '') {
            $body .= 'Quedaste como administrador de «' . $empresaNombre . "» en Equity.\n\n";
        } else {
            $body .= "Te invitaron a Equity para declarar tu información patrimonial.\n\n";
        }
        $body .= "1) Verifica tu correo abriendo este enlace:\n{$verifyUrl}\n\n";
        if ($tempPassword !== null && $tempPassword !== '') {
            $body .= "2) Ingresa con tu correo y esta clave temporal:\n{$tempPassword}\n"
                . "   (al entrar deberás cambiarla)\n\n";
        }
        $body .= "Gracias por registrarte en EQUITY. Después de verificar, ingresa a registrar tus Activos y Pasivos.\n\n"
            . "Saludos,\nEquity\n";

        return $body;
    }

    private static function safeName(string $nombre): string
    {
        $nombre = trim($nombre);

        return $nombre !== '' ? $nombre : 'hola';
    }

    private static function send(
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $plainBody,
        string $htmlBody
    ): bool {
        try {
            $config = config('Email');
            $protocol = strtolower(trim((string) (env('email.protocol') ?: $config->protocol ?: 'mail')));
            $config->protocol   = $protocol !== '' ? $protocol : 'mail';
            $config->fromEmail  = $fromEmail;
            $config->fromName   = $fromName !== '' ? $fromName : (string) ($config->fromName ?: 'Equity');
            $config->mailType   = 'html';
            if ($config->protocol === 'smtp') {
                $host = trim((string) (env('email.SMTPHost') ?: $config->SMTPHost));
                $user = trim((string) (env('email.SMTPUser') ?: $config->SMTPUser));
                $pass = (string) (env('email.SMTPPass') ?: $config->SMTPPass);
                $port = (int) (env('email.SMTPPort') ?: $config->SMTPPort ?: 587);
                $crypto = strtolower(trim((string) (env('email.SMTPCrypto') ?: $config->SMTPCrypto ?: 'tls')));
                if ($host === '' || $user === '' || $pass === '') {
                    log_message('error', 'EquityMail SMTP incompleto (host/user/pass).');

                    return false;
                }
                $config->SMTPHost   = $host;
                $config->SMTPUser   = $user;
                $config->SMTPPass   = $pass;
                $config->SMTPPort   = $port > 0 ? $port : 587;
                $config->SMTPCrypto = in_array($crypto, ['tls', 'ssl', ''], true) ? $crypto : 'tls';
            }

            $emailSvc = new \CodeIgniter\Email\Email($config);
            $emailSvc->setFrom($fromEmail, $fromName);
            $emailSvc->setTo($to);
            $emailSvc->setSubject($subject);
            $emailSvc->setMessage($htmlBody);
            $emailSvc->setAltMessage($plainBody);
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
