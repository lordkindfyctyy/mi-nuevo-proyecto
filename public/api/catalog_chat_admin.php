<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$tenantId = currentTenantId();
$action = $_REQUEST['action'] ?? '';

if (!$tenantId) {
    echo json_encode(['ok' => false, 'error' => 'No hay ningún negocio registrado en tu sesión.']);
    exit;
}

if ($action === 'unread_count') {
    echo json_encode(['ok' => true, 'count' => ContactMessage::countUnreadBySource('catalog_chat', $tenantId)]);
    exit;
}

if ($action === 'list') {
    $conversations = ContactMessage::conversationsBySource('catalog_chat', $tenantId);

    echo json_encode([
        'ok' => true,
        'conversations' => array_map(static fn ($c) => [
            'email' => $c['email'],
            'name' => $c['name'],
            'phone' => $c['phone'],
            'last_message' => $c['last_message'],
            'last_sender' => $c['last_sender'],
            'last_created_at' => $c['last_created_at'],
            'unread_count' => (int) $c['unread_count'],
        ], $conversations),
    ]);
    exit;
}

if ($action === 'thread') {
    $email = trim($_GET['email'] ?? '');
    if ($email === '') {
        echo json_encode(['ok' => false, 'error' => 'Falta el email de la conversación.']);
        exit;
    }

    $afterId = (int) ($_GET['after_id'] ?? 0);

    if ($afterId === 0) {
        ContactMessage::markConversationRead('catalog_chat', $email, $tenantId);
    }

    $messages = $afterId > 0
        ? ContactMessage::conversationByEmailAfter('catalog_chat', $email, $afterId, $tenantId)
        : ContactMessage::conversationByEmail('catalog_chat', $email, $tenantId);

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

if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($email === '' || $message === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Escribí una respuesta antes de enviar.']);
        exit;
    }

    try {
        ContactMessage::create([
            'tenant_id' => $tenantId,
            'name' => currentUserName() ?? 'Vendedor',
            'email' => $email,
            'message' => $message,
            'source' => 'catalog_chat',
            'sender' => 'admin',
        ]);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo enviar la respuesta.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción no válida.']);
