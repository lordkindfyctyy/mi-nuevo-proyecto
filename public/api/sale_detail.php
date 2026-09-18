<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Sale.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../includes/receipt_helpers.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$paymentLabels = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR',
    'other' => 'Otro',
];

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

$tenant = Tenant::find($tenantId);
$seller = User::find((int) $sale['user_id']);
$rawItems = Sale::itemsFor($saleId);

$revenue = 0.0;
$cost = 0.0;
$items = array_map(function ($item) use (&$revenue, &$cost) {
    $subtotal = (float) $item['subtotal'];
    $itemCost = (float) ($item['product_cost'] ?? 0) * (float) $item['quantity'];
    $revenue += $subtotal;
    $cost += $itemCost;

    return [
        'product_id' => (int) $item['product_id'],
        'name' => $item['product_name'],
        'image' => Product::imageUrl(['imagen' => $item['product_imagen'], 'image_url' => $item['product_image_url']]),
        'quantity' => (float) $item['quantity'],
        'sale_unit' => $item['product_sale_unit'] ?? 'unit',
        'unit_price' => (float) $item['unit_price'],
        'subtotal' => $subtotal,
    ];
}, $rawItems);

$token = Sale::getOrCreatePublicToken($saleId);
$remitoUrl = receipt_public_url($token);

echo json_encode([
    'ok' => true,
    'sale' => [
        'id' => (int) $sale['id'],
        'created_at' => $sale['created_at'],
        'status' => $sale['status'],
        'status_label' => $sale['status'] === 'completed' ? 'Pagada' : 'Anulada',
        'payment_method' => $sale['payment_method'],
        'payment_label' => $paymentLabels[$sale['payment_method']] ?? $sale['payment_method'],
        'customer_name' => $sale['customer_name'],
        'seller_name' => $seller['name'] ?? null,
        'subtotal' => $revenue,
        'discount_amount' => (float) $sale['discount_amount'],
        'total' => (float) $sale['total'],
        'cost' => $cost,
        'profit' => $revenue - $cost,
        'receipt_url' => $remitoUrl,
        'whatsapp_url' => receipt_whatsapp_share_url($tenant['name'] ?? APP_NAME, $sale, $remitoUrl),
    ],
    'tenant' => [
        'name' => $tenant['name'] ?? APP_NAME,
        'address' => $tenant['address'] ?? null,
        'phone' => $tenant['phone'] ?? null,
        'tax_id' => $tenant['tax_id'] ?? null,
    ],
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
