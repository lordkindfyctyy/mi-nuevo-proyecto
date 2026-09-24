<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/debug_log.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

$tenantId = currentTenantId();
$action = $_POST['action'] ?? '';

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/vender.php?error=1');
    exit;
}

if ($action === 'update_whatsapp') {
    $raw = trim($_POST['whatsapp_phone'] ?? '');
    $digits = preg_replace('/\D+/', '', $raw);

    if ($raw !== '' && ($digits === '' || strlen($digits) < 8)) {
        header('Location: ' . BASE_URL . '/vender.php?share_error=phone');
        exit;
    }

    Tenant::update($tenantId, ['whatsapp_phone' => $digits ?: null]);

    header('Location: ' . BASE_URL . '/vender.php?share_success=1');
    exit;
}

if ($action === 'update_ai_assistant') {
    $tenant = Tenant::find($tenantId);
    $wantsEnabled = ($_POST['ai_assistant_enabled'] ?? '') === '1';

    // Es un add-on pago: este toggle solo pausa/reactiva el asistente de un
    // negocio al que el admin ya le otorgó el add-on (ai_assistant_granted_at),
    // nunca lo activa por primera vez — eso requiere pedirlo y que se
    // confirme el pago (ver "request_ai_assistant" abajo).
    if (!empty($tenant['ai_assistant_granted_at'])) {
        Tenant::update($tenantId, ['ai_assistant_enabled' => $wantsEnabled ? 1 : 0]);
    }

    header('Location: ' . BASE_URL . '/vender.php?share_success=1');
    exit;
}

if ($action === 'request_ai_assistant') {
    $tenant = Tenant::find($tenantId);

    if ($tenant && empty($tenant['ai_assistant_granted_at']) && empty($tenant['ai_assistant_requested_at'])) {
        try {
            ContactMessage::create([
                'tenant_id' => $tenantId,
                'name' => $tenant['name'],
                'email' => $tenant['email'],
                'phone' => $tenant['phone'],
                'message' => 'Este negocio quiere contratar el asistente de IA del catálogo ($5.000 ARS/mes).',
                'source' => 'contact_form',
                'sender' => 'visitor',
            ]);

            $body = '<p>' . htmlspecialchars($tenant['name']) . ' (' . htmlspecialchars($tenant['email']) . ') quiere contratar el asistente de IA del catálogo por $5.000 ARS/mes.</p>';
            send_app_mail(CONTACT_NOTIFICATION_EMAIL, 'Solicitud de asistente de IA: ' . $tenant['name'], $body);

            Tenant::markAiAssistantRequested($tenantId);
        } catch (Throwable $e) {
            app_debug_log('[request_ai_assistant] ' . $e->getMessage());
        }
    }

    header('Location: ' . BASE_URL . '/vender.php?share_success=1');
    exit;
}

header('Location: ' . BASE_URL . '/vender.php');
exit;
