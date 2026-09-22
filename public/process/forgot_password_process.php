<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/PasswordReset.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/forgot_password.php');
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');

if ($identifier === '') {
    header('Location: ' . BASE_URL . '/forgot_password.php?error=empty');
    exit;
}

$user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
    ? User::findByEmailGlobal($identifier)
    : User::findByPhoneGlobal($identifier);

// Siempre respondemos igual, exista o no la cuenta, para no revelar qué
// correos/teléfonos están registrados en el sistema.
if ($user && $user['status'] === 'active') {
    $token = PasswordReset::create((int) $user['id']);
    $resetUrl = BASE_URL . '/reset_password.php?token=' . urlencode($token);

    $body = '<p>Hola ' . htmlspecialchars($user['name']) . ',</p>'
        . '<p>Tu nombre de usuario asociado a esta cuenta es: <strong>' . htmlspecialchars($user['name']) . '</strong> (' . htmlspecialchars($user['email']) . ')</p>'
        . '<p>Recibimos una solicitud para restablecer tu contraseña en ' . htmlspecialchars(APP_NAME) . '. Si fuiste vos, hacé clic en el siguiente enlace (válido por 1 hora):</p>'
        . '<p><a href="' . htmlspecialchars($resetUrl) . '">Elegir una nueva contraseña</a></p>'
        . '<p>Si el enlace no funciona, copiá y pegá esta dirección en tu navegador:<br>' . htmlspecialchars($resetUrl) . '</p>'
        . '<p>Si no fuiste vos quien lo solicitó, podés ignorar este correo: tu contraseña actual sigue siendo válida.</p>';

    send_app_mail($user['email'], 'Recuperá tu acceso a ' . APP_NAME, $body);
}

header('Location: ' . BASE_URL . '/forgot_password.php?sent=1');
exit;
