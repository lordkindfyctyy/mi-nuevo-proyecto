<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/User.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

function currentWidgetIdentity(): array
{
    $user = User::find(currentUserId());

    return [
        'name' => $user['name'] ?? (currentUserName() ?? 'Usuario'),
        'email' => $user['email'] ?? '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $identity = currentWidgetIdentity();
    $afterId = (int) ($_GET['after_id'] ?? 0);

    $messages = $afterId > 0
        ? ContactMessage::conversationByEmailAfter('live_chat', $identity['email'], $afterId)
        : ContactMessage::conversationByEmail('live_chat', $identity['email']);

    echo json_encode([
        'ok' => true,
        'messages' => array_map(static fn ($m) => [
            'id' => (int) $m['id'],
            'sender' => $m['sender'],
            'message' => $m['message'],
            'created_at' => $m['created_at'],
        ], $messages),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Escribí un mensaje antes de enviar.']);
        exit;
    }

    $identity = currentWidgetIdentity();

    try {
        ContactMessage::create([
            'name' => $identity['name'],
            'email' => $identity['email'],
            'message' => $message,
            'source' => 'live_chat',
            'sender' => 'visitor',
        ]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo enviar tu consulta. Intentá de nuevo.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
