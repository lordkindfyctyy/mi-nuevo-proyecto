<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Sale.php';
requireLogin();

$redirectPath = $_POST['redirect_to'] ?? '/ventas.php';
if (!is_string($redirectPath) || $redirectPath === '' || $redirectPath[0] !== '/') {
    $redirectPath = '/ventas.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . $redirectPath);
    exit;
}

$tenantId = currentTenantId();
$saleId = (int) ($_POST['sale_id'] ?? 0);

if (!$tenantId || !$saleId) {
    header('Location: ' . BASE_URL . $redirectPath . '?cancel_error=1');
    exit;
}

try {
    Sale::cancelAndRestoreStock($tenantId, $saleId);
    header('Location: ' . BASE_URL . $redirectPath . '?cancel_success=1');
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . $redirectPath . '?cancel_error=1');
}
exit;
