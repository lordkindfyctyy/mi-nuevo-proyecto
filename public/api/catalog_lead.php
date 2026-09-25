<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/debug_log.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$businessName = trim($_POST['business_name'] ?? '');
$catalogToken = trim($_POST['t'] ?? '');

if ($name === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Completá tu nombre y tu WhatsApp para que te contactemos.']);
    exit;
}

// Solo para dar contexto en el mail al equipo de SixSeven (desde qué
// catálogo llegó este interesado) — este lead nunca queda asociado a ese
// negocio ni aparece en su bandeja de mensajes.
$referringTenant = $catalogToken !== '' ? Tenant::findByPublicToken($catalogToken) : null;

$messageLines = ['Negocio propio: ' . ($businessName !== '' ? $businessName : '(no indicado)')];
if ($referringTenant) {
    $messageLines[] = 'Vino desde el catálogo de: ' . $referringTenant['name'];
}

try {
    ContactMessage::create([
        'name' => $name,
        'phone' => $phone,
        'message' => implode("\n", $messageLines),
        'source' => 'catalog_lead',
    ]);

    $body = '<p>Nuevo interesado en sumarse a ' . htmlspecialchars(APP_NAME) . ', dejado desde el catálogo público de un negocio:</p>'
        . '<p><strong>Nombre:</strong> ' . htmlspecialchars($name) . '<br>'
        . '<strong>WhatsApp:</strong> ' . htmlspecialchars($phone) . '<br>'
        . '<strong>Negocio propio:</strong> ' . htmlspecialchars($businessName !== '' ? $businessName : '(no indicado)') . '</p>'
        . ($referringTenant ? '<p>Vino desde el catálogo de: ' . htmlspecialchars($referringTenant['name']) . '</p>' : '');

    send_app_mail(CONTACT_NOTIFICATION_EMAIL, 'Nuevo interesado en ' . APP_NAME . ': ' . $name, $body);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    app_debug_log('[catalog_lead] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo enviar tu consulta. Intentá de nuevo.']);
}
