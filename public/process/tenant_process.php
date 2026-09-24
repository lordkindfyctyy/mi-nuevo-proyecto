<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
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
    Tenant::update($tenantId, ['ai_assistant_enabled' => ($_POST['ai_assistant_enabled'] ?? '') === '1' ? 1 : 0]);

    header('Location: ' . BASE_URL . '/vender.php?share_success=1');
    exit;
}

header('Location: ' . BASE_URL . '/vender.php');
exit;
