<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/models/Supplier.php';
require_once __DIR__ . '/../../src/models/Purchase.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/comprar.php');
    exit;
}

$tenantId = currentTenantId();
$userId = currentUserId();

if (!$tenantId || !$userId) {
    header('Location: ' . BASE_URL . '/comprar.php?error=1');
    exit;
}

$supplierId = (int) ($_POST['supplier_id'] ?? 0);
$supplier = Supplier::findForTenant($supplierId, $tenantId);

if (!$supplier) {
    header('Location: ' . BASE_URL . '/comprar.php?error=supplier');
    exit;
}

$quantities = $_POST['quantity'] ?? [];
$unitCosts = $_POST['unit_cost'] ?? [];
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

    $unitCost = (float) ($unitCosts[$productId] ?? $product['cost']);
    if ($unitCost < 0) {
        continue;
    }

    $items[] = [
        'product_id' => (int) $product['id'],
        'quantity' => $qty,
        'unit_cost' => $unitCost,
    ];
}

if (!$items) {
    header('Location: ' . BASE_URL . '/comprar.php?error=items');
    exit;
}

try {
    $purchaseId = Purchase::create($tenantId, $supplierId, $userId, $items);
    header('Location: ' . BASE_URL . '/comprar.php?success=1&purchase=' . $purchaseId);
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/comprar.php?error=1');
    exit;
}
