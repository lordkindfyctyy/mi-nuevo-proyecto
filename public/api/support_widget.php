<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$message = trim($_POST['message'] ?? '');

if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Escribí un mensaje antes de enviar.']);
    exit;
}

$name = 'Visitante web';
$email = null;

if (isLoggedIn()) {
    require_once __DIR__ . '/../../src/models/User.php';
    $user = User::find(currentUserId());
    $name = $user['name'] ?? (currentUserName() ?? $name);
    $email = $user['email'] ?? null;
}

try {
    ContactMessage::create($name, $email, $message, 'live_chat');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar tu consulta. Intentá de nuevo.']);
}
