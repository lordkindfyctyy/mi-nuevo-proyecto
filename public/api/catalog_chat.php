<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../includes/gemini_client.php';
require_once __DIR__ . '/../../includes/debug_log.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Resolves the caller for the public catalog chat.
 *
 * Always anonymous. The tenant is re-derived from the catalog's own public
 * token (never trusted from a raw client-supplied tenant id). The
 * conversation itself is identified by an opaque `token` the browser
 * generates once (on the identify step) and keeps in localStorage — holding
 * that token IS the proof of ownership, so there is nothing to "verify"
 * against a stored account: no email/password, just a per-browser secret.
 *
 * @return array{ok:true,tenantId:int,name:string,phone:?string,token:string}|array{ok:false,error:string,code:int}
 */
function resolveCatalogChatCaller(): array
{
    $catalogToken = trim($_REQUEST['t'] ?? '');
    $tenant = $catalogToken !== '' ? Tenant::findByPublicToken($catalogToken) : null;

    if (!$tenant) {
        return ['ok' => false, 'error' => 'Catálogo no válido.', 'code' => 404];
    }

    $token = trim($_REQUEST['token'] ?? '');
    if ($token === '') {
        return ['ok' => false, 'error' => 'Falta identificarte para chatear.', 'code' => 422];
    }

    $name = trim($_REQUEST['name'] ?? '');
    $phone = trim($_REQUEST['phone'] ?? '');

    return ['ok' => true, 'tenantId' => (int) $tenant['id'], 'name' => $name, 'phone' => $phone !== '' ? $phone : null, 'token' => $token];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $caller = resolveCatalogChatCaller();

    if (!$caller['ok']) {
        // Not identified yet isn't an error, just an empty chat.
        echo json_encode(['ok' => true, 'messages' => []]);
        exit;
    }

    $afterId = (int) ($_GET['after_id'] ?? 0);
    $messages = $afterId > 0
        ? ContactMessage::conversationByTokenAfter('catalog_chat', $caller['token'], $afterId, $caller['tenantId'])
        : ContactMessage::conversationByToken('catalog_chat', $caller['token'], $caller['tenantId']);

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

    $caller = resolveCatalogChatCaller();

    if (!$caller['ok']) {
        http_response_code($caller['code']);
        echo json_encode(['ok' => false, 'error' => $caller['error']]);
        exit;
    }

    if ($caller['name'] === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Completá tu nombre para escribirle al vendedor.']);
        exit;
    }

    try {
        ContactMessage::create([
            'tenant_id' => $caller['tenantId'],
            'name' => $caller['name'],
            'phone' => $caller['phone'],
            'message' => $message,
            'source' => 'catalog_chat',
            'sender' => 'visitor',
            'widget_token' => $caller['token'],
        ]);

        // Respuesta automática del asistente de IA (si el negocio la tiene
        // activada): nunca debe romper el envío del mensaje del cliente, así
        // que cualquier falla acá queda solo en el log.
        try {
            $tenant = Tenant::find($caller['tenantId']);
            if (!empty($tenant['ai_assistant_enabled'])) {
                $products = Product::allByTenant($caller['tenantId']);
                $history = ContactMessage::conversationByToken('catalog_chat', $caller['token'], $caller['tenantId']);
                $reply = gemini_catalog_reply($tenant, $products, $history, $message);

                if ($reply !== null) {
                    ContactMessage::create([
                        'tenant_id' => $caller['tenantId'],
                        'name' => $tenant['name'] ?? 'Asistente',
                        'message' => "🤖 $reply",
                        'source' => 'catalog_chat',
                        'sender' => 'admin',
                        'widget_token' => $caller['token'],
                    ]);
                }
            }
        } catch (Throwable $e) {
            app_debug_log('[gemini_catalog_reply] ' . $e->getMessage());
        }

        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo enviar tu consulta. Intentá de nuevo.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
