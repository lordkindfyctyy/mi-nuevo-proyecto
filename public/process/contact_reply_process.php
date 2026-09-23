<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/User.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

$message = trim($_POST['message'] ?? '');
$source = $_POST['source'] ?? 'live_chat';

if (!in_array($source, ['live_chat', 'catalog_chat'], true)) {
    $source = 'live_chat';
}

// catalog_chat conversations are keyed by an anonymous per-browser token (no
// email is collected); live_chat (tech support) is keyed by email, always
// the logged-in user's own — never trust the client for this, or any user
// could reply into (and thus impersonate support inside) another
// business's conversation.
$keyedByToken = $source === 'catalog_chat';
if ($keyedByToken) {
    $key = trim($_POST['token'] ?? '');
} else {
    $currentUser = User::find(currentUserId());
    $key = $currentUser['email'] ?? '';
}

$redirect = BASE_URL . '/mensaje_chat.php?source=' . urlencode($source) . '&' . ($keyedByToken ? 'token=' : 'email=') . urlencode($key);

if ($key === '' || $message === '') {
    header('Location: ' . $redirect . '&error=1');
    exit;
}

try {
    ContactMessage::create([
        'tenant_id' => $keyedByToken ? currentTenantId() : null,
        'name' => currentUserName() ?? 'Soporte SixSeven',
        'email' => $keyedByToken ? null : $key,
        'message' => $message,
        'source' => $source,
        'sender' => 'admin',
        'widget_token' => $keyedByToken ? $key : null,
    ]);
} catch (Throwable $e) {
    header('Location: ' . $redirect . '&error=1');
    exit;
}

header('Location: ' . $redirect);
exit;
