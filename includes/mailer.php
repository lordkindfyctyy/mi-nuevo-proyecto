<?php

require_once __DIR__ . '/../src/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../src/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../src/lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/debug_log.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Envío de mails transaccionales (recuperación de acceso, etc.).
 *
 * Si SMTP_HOST está configurado (ver config/config.php y
 * config/config.local.php.example), envía por SMTP con PHPMailer: la
 * mayoría de los hostings, incluido Hostinger, bloquean o marcan como spam
 * los mails salientes por mail() nativo si no salen autenticados desde un
 * buzón real del dominio.
 *
 * Sin SMTP configurado (típicamente en local, donde no hay un buzón real
 * a mano), cae a mail() nativo y, si tampoco eso funciona, deja el
 * contenido completo en el log de errores de PHP para poder probar el
 * flujo sin depender de un SMTP real.
 *
 * El detalle de cualquier fallo (SMTP o mail()) se guarda en el log del
 * servidor, nunca en una respuesta HTTP: esta función la llaman
 * endpoints públicos sin login, y devolver ahí detalle interno (host,
 * usuario, error de conexión) sería una fuga de información.
 */
function send_app_mail(string $to, string $subject, string $htmlBody): bool
{
    if (SMTP_HOST !== '') {
        return send_app_mail_via_smtp($to, $subject, $htmlBody);
    }

    return send_app_mail_via_native($to, $subject, $htmlBody);
}

function send_app_mail_via_smtp(string $to, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();

        return true;
    } catch (PHPMailerException $e) {
        // $mail->ErrorInfo trae el mensaje específico de SMTP (auth
        // rechazada, host inalcanzable, etc.), más útil que el genérico
        // de la excepción.
        app_debug_log('[mailer:smtp] To: ' . $to . ' | Host: ' . SMTP_HOST . ':' . SMTP_PORT . ' | ' . $mail->ErrorInfo);

        return false;
    }
}

function send_app_mail_via_native(string $to, string $subject, string $htmlBody): bool
{
    $hostHeader = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $safeHost = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $hostHeader);
    $safeHost = preg_replace('/^www\./i', '', $safeHost ?? '');
    if ($safeHost === '' || $safeHost === null) {
        $safeHost = 'localhost';
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . APP_NAME . ' <no-reply@' . $safeHost . ">\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $sent = @mail($to, $encodedSubject, $htmlBody, $headers);

    if (!$sent) {
        app_debug_log("[mailer:native] No se pudo enviar (o estamos en local sin SMTP/mail configurado). To: $to | Subject: $subject\n$htmlBody");
    }

    return $sent;
}
