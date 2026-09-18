<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Sale.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$tenantId = currentTenantId();
$saleId = (int) ($_GET['sale_id'] ?? 0);

if (!$tenantId || !$saleId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Solicitud inválida.']);
    exit;
}

$sale = Sale::findForTenant($saleId, $tenantId);
if (!$sale) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Venta no encontrada.']);
    exit;
}

$items = array_map(fn($item) => [
    'product_id' => (int) $item['product_id'],
    'name' => $item['product_name'],
    'quantity' => (float) $item['quantity'],
    'unit_price' => (float) $item['unit_price'],
], Sale::itemsFor($saleId));

echo json_encode([
    'ok' => true,
    'sale' => [
        'id' => (int) $sale['id'],
        'status' => $sale['status'],
        'discount_amount' => (float) $sale['discount_amount'],
        'total' => (float) $sale['total'],
    ],
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
