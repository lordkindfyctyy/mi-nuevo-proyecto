<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/debug_log.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/contact.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_URL . '/contact.php?error=1');
    exit;
}

try {
    ContactMessage::create([
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'source' => 'contact_form',
    ]);

    $body = '<p>Nuevo mensaje desde el formulario de contacto de ' . htmlspecialchars(APP_NAME) . ':</p>'
        . '<p><strong>Nombre:</strong> ' . htmlspecialchars($name) . '<br>'
        . '<strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
        . '<p>' . nl2br(htmlspecialchars($message)) . '</p>';

    foreach (User::allSupportAdmins() as $admin) {
        send_app_mail($admin['email'], 'Nuevo contacto en ' . APP_NAME . ': ' . $name, $body);
    }
} catch (Throwable $e) {
    app_debug_log('[contact_process] ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/contact.php?error=1');
    exit;
}

header('Location: ' . BASE_URL . '/contact.php?success=1');
exit;
