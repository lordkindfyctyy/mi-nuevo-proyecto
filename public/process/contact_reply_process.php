<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($email === '' || $message === '') {
    header('Location: ' . BASE_URL . '/mensaje_chat.php?email=' . urlencode($email) . '&error=1');
    exit;
}

try {
    ContactMessage::create([
        'name' => currentUserName() ?? 'Soporte SixSeven',
        'email' => $email,
        'message' => $message,
        'source' => 'live_chat',
        'sender' => 'admin',
    ]);
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/mensaje_chat.php?email=' . urlencode($email) . '&error=1');
    exit;
}

header('Location: ' . BASE_URL . '/mensaje_chat.php?email=' . urlencode($email));
exit;
