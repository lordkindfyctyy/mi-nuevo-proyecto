<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Sale.php';
require_once __DIR__ . '/../../src/models/Product.php';
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
$quantities = $_POST['quantity'] ?? [];
$prices = $_POST['price'] ?? [];

if (!$tenantId || !$saleId) {
    header('Location: ' . BASE_URL . $redirectPath . '?edit_error=1');
    exit;
}

// Misma validación/normalización de cantidades y precios que sale_process.php:
// enteros para productos por unidad, hasta 3 decimales para los que se venden
// por peso, y el precio del catálogo como respaldo si no llega uno válido.
$items = [];
foreach ($quantities as $productId => $qty) {
    $product = Product::findForTenant((int) $productId, $tenantId);
    if (!$product || $product['status'] !== 'active') {
        continue;
    }

    $qty = is_numeric($qty) ? round((float) $qty, 3) : 0.0;
    if (($product['sale_unit'] ?? 'unit') !== 'weight') {
        $qty = (float) (int) $qty;
    }
    if ($qty <= 0) {
        continue;
    }

    $unitPrice = (float) $product['price'];
    if (isset($prices[$productId]) && is_numeric($prices[$productId]) && (float) $prices[$productId] >= 0) {
        $unitPrice = (float) $prices[$productId];
    }

    $items[] = [
        'product_id' => (int) $product['id'],
        'quantity' => $qty,
        'unit_price' => $unitPrice,
    ];
}

if (!$items) {
    header('Location: ' . BASE_URL . $redirectPath . '?edit_error=items');
    exit;
}

try {
    Sale::updateItems($tenantId, $saleId, $items);
    header('Location: ' . BASE_URL . $redirectPath . '?edit_success=1');
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . $redirectPath . '?edit_error=stock');
}
exit;
