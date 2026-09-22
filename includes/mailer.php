<?php

/**
 * Envío de mails transaccionales (recuperación de acceso, etc.) usando la
 * función nativa mail() de PHP, que en Hostinger (y hostings similares)
 * sale por el MTA local sin configuración extra. No hay servidor SMTP en
 * local (XAMPP), así que ahí mail() normalmente falla o no está
 * configurado: cuando eso pasa, dejamos el contenido del mail en el log de
 * errores de PHP para poder probar el flujo completo sin depender de un
 * SMTP real.
 */
function send_app_mail(string $to, string $subject, string $htmlBody): bool
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
        error_log("[mailer] No se pudo enviar (o estamos en local sin SMTP). To: $to | Subject: $subject\n$htmlBody");
    }

    return $sent;
}
