<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/models/Sale.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

$tenantId = currentTenantId();
$userId = currentUserId();

if (!$tenantId || !$userId) {
    header('Location: ' . BASE_URL . '/vender.php?error=1');
    exit;
}

$quantities = $_POST['quantity'] ?? [];
$items = [];

foreach ($quantities as $productId => $qty) {
    $qty = (int) $qty;
    if ($qty <= 0) {
        continue;
    }

    $product = Product::findForTenant((int) $productId, $tenantId);
    if (!$product || $product['status'] !== 'active') {
        continue;
    }

    if ($qty > (int) $product['stock_quantity']) {
        header('Location: ' . BASE_URL . '/vender.php?error=stock');
        exit;
    }

    $items[] = [
        'product_id' => (int) $product['id'],
        'quantity' => $qty,
        'unit_price' => (float) $product['price'],
    ];
}

if (!$items) {
    header('Location: ' . BASE_URL . '/vender.php?error=items');
    exit;
}

$allowedMethods = ['cash', 'card', 'transfer', 'other'];
$paymentMethod = $_POST['payment_method'] ?? 'cash';
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = 'cash';
}

$customerName = trim($_POST['customer_name'] ?? '') ?: null;

try {
    $saleId = Sale::create($tenantId, $userId, $items, $customerName, $paymentMethod);
    header('Location: ' . BASE_URL . '/vender.php?success=1&sale=' . $saleId);
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/vender.php?error=1');
    exit;
}
