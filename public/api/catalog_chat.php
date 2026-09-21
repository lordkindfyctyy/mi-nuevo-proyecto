<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/Tenant.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Resolves the caller's identity for the public catalog chat.
 *
 * Always anonymous: the tenant is re-derived from the catalog's own public
 * token (never trusted from a raw client-supplied tenant id). Reading an
 * existing conversation ($requireTokenForExisting = true, used for GET)
 * requires the token handed back on that email's earlier messages, so a
 * stranger can't read someone else's chat just by knowing their address.
 * Sending a message never requires it: a customer writing from a new
 * device/browser (lost localStorage, cleared data, etc.) must still be able
 * to get a message through, even if they can't yet prove ownership of the
 * older history.
 *
 * @return array{ok:true,tenantId:int,name:string,email:string,phone:string,token:string}|array{ok:false,error:string,code:int}
 */
function resolveCatalogChatIdentity(bool $requireTokenForExisting): array
{
    $catalogToken = trim($_REQUEST['t'] ?? '');
    $tenant = $catalogToken !== '' ? Tenant::findByPublicToken($catalogToken) : null;

    if (!$tenant) {
        return ['ok' => false, 'error' => 'Catálogo no válido.', 'code' => 404];
    }

    $name = trim($_REQUEST['name'] ?? '');
    $email = trim($_REQUEST['email'] ?? '');
    $phone = trim($_REQUEST['phone'] ?? '');
    $token = trim($_REQUEST['token'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Completá tu nombre, email y celular para escribirle al vendedor.', 'code' => 422];
    }

    $tenantId = (int) $tenant['id'];

    if ($requireTokenForExisting && ContactMessage::countByEmail('catalog_chat', $email, $tenantId) > 0) {
        if ($token === '' || !ContactMessage::tokenMatchesEmail('catalog_chat', $email, $token)) {
            return ['ok' => false, 'error' => 'No pudimos verificar tu conversación. Iniciá un chat nuevo.', 'code' => 403];
        }
    }

    return ['ok' => true, 'tenantId' => $tenantId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'token' => $token];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $identity = resolveCatalogChatIdentity(true);

    if (!$identity['ok']) {
        // A visitor who hasn't identified yet isn't an error, just an empty chat.
        echo json_encode(['ok' => true, 'messages' => []]);
        exit;
    }

    $afterId = (int) ($_GET['after_id'] ?? 0);
    $messages = $afterId > 0
        ? ContactMessage::conversationByEmailAfter('catalog_chat', $identity['email'], $afterId, $identity['tenantId'])
        : ContactMessage::conversationByEmail('catalog_chat', $identity['email'], $identity['tenantId']);

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

    $identity = resolveCatalogChatIdentity(false);

    if (!$identity['ok']) {
        http_response_code($identity['code']);
        echo json_encode(['ok' => false, 'error' => $identity['error']]);
        exit;
    }

    $token = $identity['token'];
    if ($token === '' || !ContactMessage::tokenMatchesEmail('catalog_chat', $identity['email'], $token)) {
        $token = bin2hex(random_bytes(16));
    }

    try {
        ContactMessage::create([
            'tenant_id' => $identity['tenantId'],
            'name' => $identity['name'],
            'email' => $identity['email'],
            'phone' => $identity['phone'],
            'message' => $message,
            'source' => 'catalog_chat',
            'sender' => 'visitor',
            'widget_token' => $token,
        ]);
        echo json_encode(['ok' => true, 'token' => $token]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo enviar tu consulta. Intentá de nuevo.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
