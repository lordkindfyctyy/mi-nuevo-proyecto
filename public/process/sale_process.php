<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/models/Sale.php';
require_once __DIR__ . '/../../src/models/Customer.php';
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
$customPrices = $_POST['price'] ?? [];
$items = [];

foreach ($quantities as $productId => $qty) {
    $product = Product::findForTenant((int) $productId, $tenantId);
    if (!$product || $product['status'] !== 'active') {
        continue;
    }

    // Los productos "por unidad" solo aceptan enteros; los "por peso"
    // (ej. fracciones de kilo) aceptan hasta 3 decimales.
    $qty = is_numeric($qty) ? round((float) $qty, 3) : 0.0;
    if (($product['sale_unit'] ?? 'unit') !== 'weight') {
        $qty = (float) (int) $qty;
    }

    if ($qty <= 0) {
        continue;
    }

    if ($qty > (float) $product['stock_quantity']) {
        header('Location: ' . BASE_URL . '/vender.php?error=stock');
        exit;
    }

    // El vendedor puede ajustar el precio de venta desde la canasta (ej.
    // descuentos o redondeos); si no manda un precio válido, se usa el de
    // catálogo. Siempre se re-valida contra 0 para no aceptar precios
    // negativos.
    $unitPrice = (float) $product['price'];
    if (isset($customPrices[$productId]) && is_numeric($customPrices[$productId])) {
        $customPrice = (float) $customPrices[$productId];
        if ($customPrice >= 0) {
            $unitPrice = $customPrice;
        }
    }

    $items[] = [
        'product_id' => (int) $product['id'],
        'quantity' => $qty,
        'unit_price' => $unitPrice,
    ];
}

if (!$items) {
    header('Location: ' . BASE_URL . '/vender.php?error=items');
    exit;
}

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += $item['unit_price'] * $item['quantity'];
}

// El descuento se recalcula siempre en el servidor a partir del subtotal
// real de los productos validados; nunca se confía en un monto de
// descuento que venga ya calculado desde el navegador.
$discountAmount = 0.0;
if (($_POST['discount_enabled'] ?? '') === '1') {
    $discountType = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $discountValue = is_numeric($_POST['discount_value'] ?? null) ? (float) $_POST['discount_value'] : 0.0;
    $discountValue = max(0.0, $discountValue);

    $discountAmount = $discountType === 'percent'
        ? $subtotal * min($discountValue, 100) / 100
        : $discountValue;
    $discountAmount = round(max(0.0, min($discountAmount, $subtotal)), 2);
}

$allowedMethods = ['cash', 'card', 'transfer', 'qr', 'other'];
$paymentMethod = $_POST['payment_method'] ?? 'cash';
if (!in_array($paymentMethod, $allowedMethods, true)) {
    $paymentMethod = 'cash';
}

$customerId = null;
$customerName = null;
$postedCustomerId = (int) ($_POST['customer_id'] ?? 0);
if ($postedCustomerId > 0) {
    $customer = Customer::findForTenant($postedCustomerId, $tenantId);
    if ($customer) {
        $customerId = (int) $customer['id'];
        $customerName = $customer['name'];
    }
}

try {
    $saleId = Sale::create($tenantId, $userId, $items, $customerName, $paymentMethod, $customerId, $discountAmount);
    header('Location: ' . BASE_URL . '/vender.php?success=1&sale=' . $saleId);
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/vender.php?error=1');
    exit;
}
