<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/productos.php');
    exit;
}

$tenantId = currentTenantId();
$productId = (int) ($_POST['product_id'] ?? 0);

if (!$tenantId || !$productId) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}

$product = Product::findForTenant($productId, $tenantId);

if (!$product) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}

$variantLabel = trim($_POST['variant_name'] ?? '');
$price = trim($_POST['price'] ?? '');

if ($variantLabel === '' || $price === '' || (float) $price < 0) {
    header('Location: ' . BASE_URL . '/productos.php?edit=' . $productId . '&error=1');
    exit;
}

try {
    $newName = trim(Product::baseName($product['name']) . ' ' . $variantLabel);

    Product::create($tenantId, $newName, (float) $price, [
        'sku' => trim($_POST['sku'] ?? '') ?: null,
        'description' => $product['description'],
        'category' => $product['category'],
        'brand' => $product['brand'],
        'imagen' => $product['imagen'],
        'cost' => (float) ($_POST['cost'] ?? 0),
        'sale_unit' => Product::saleUnitForVariantLabel($variantLabel),
        'stock_quantity' => (float) ($_POST['stock'] ?? 0),
        'show_in_catalog' => $product['show_in_catalog'],
    ]);

    header('Location: ' . BASE_URL . '/productos.php?edit=' . $productId . '&success=variants&count=1');
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/productos.php?edit=' . $productId . '&error=1');
    exit;
}
