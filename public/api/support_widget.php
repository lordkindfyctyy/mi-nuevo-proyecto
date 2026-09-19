<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Resolves the caller's identity for the live-chat widget.
 *
 * Logged-in users are trusted from the session alone (name/email), so any
 * client-supplied name/email/phone/token is ignored for them. Anonymous
 * visitors must prove ownership of their conversation with a token that was
 * handed to them on their first message.
 *
 * @return array{ok:true,name:string,email:string,phone:?string,loggedIn:bool}|array{ok:false,error:string,code:int}
 */
function resolveWidgetIdentity(): array
{
    if (isLoggedIn()) {
        require_once __DIR__ . '/../../src/models/User.php';
        $user = User::find(currentUserId());

        return [
            'ok' => true,
            'name' => $user['name'] ?? (currentUserName() ?? 'Usuario'),
            'email' => $user['email'] ?? '',
            'phone' => null,
            'loggedIn' => true,
        ];
    }

    $name = trim($_REQUEST['name'] ?? '');
    $email = trim($_REQUEST['email'] ?? '');
    $phone = trim($_REQUEST['phone'] ?? '');
    $token = trim($_REQUEST['token'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Completá tu nombre, email y celular para iniciar el chat.', 'code' => 422];
    }

    if (ContactMessage::countByEmail($email) > 0) {
        if ($token === '' || !ContactMessage::tokenMatchesEmail($email, $token)) {
            return ['ok' => false, 'error' => 'No pudimos verificar tu conversación. Iniciá un chat nuevo.', 'code' => 403];
        }
    }

    return ['ok' => true, 'name' => $name, 'email' => $email, 'phone' => $phone, 'loggedIn' => false, 'token' => $token];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $identity = resolveWidgetIdentity();

    if (!$identity['ok']) {
        // A visitor who hasn't identified yet isn't an error, just an empty chat.
        echo json_encode(['ok' => true, 'identity' => null, 'messages' => []]);
        exit;
    }

    $afterId = (int) ($_GET['after_id'] ?? 0);
    $messages = $afterId > 0
        ? ContactMessage::conversationByEmailAfter($identity['email'], $afterId)
        : ContactMessage::conversationByEmail($identity['email']);

    echo json_encode([
        'ok' => true,
        'identity' => [
            'name' => $identity['name'],
            'email' => $identity['email'],
            'phone' => $identity['phone'],
            'loggedIn' => $identity['loggedIn'],
        ],
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

    $identity = resolveWidgetIdentity();

    if (!$identity['ok']) {
        http_response_code($identity['code']);
        echo json_encode(['ok' => false, 'error' => $identity['error']]);
        exit;
    }

    if ($identity['loggedIn']) {
        $token = null;
    } elseif (ContactMessage::countByEmail($identity['email']) > 0) {
        $token = $identity['token'];
    } else {
        $token = bin2hex(random_bytes(16));
    }

    try {
        ContactMessage::create([
            'name' => $identity['name'],
            'email' => $identity['email'],
            'phone' => $identity['phone'],
            'message' => $message,
            'source' => 'live_chat',
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
