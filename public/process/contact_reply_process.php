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
$source = $_POST['source'] ?? 'live_chat';

if (!in_array($source, ['live_chat', 'catalog_chat'], true)) {
    $source = 'live_chat';
}

$redirect = BASE_URL . '/mensaje_chat.php?source=' . urlencode($source) . '&email=' . urlencode($email);

if ($email === '' || $message === '') {
    header('Location: ' . $redirect . '&error=1');
    exit;
}

try {
    ContactMessage::create([
        'tenant_id' => $source === 'catalog_chat' ? currentTenantId() : null,
        'name' => currentUserName() ?? 'Soporte SixSeven',
        'email' => $email,
        'message' => $message,
        'source' => $source,
        'sender' => 'admin',
    ]);
} catch (Throwable $e) {
    header('Location: ' . $redirect . '&error=1');
    exit;
}

header('Location: ' . $redirect);
exit;
