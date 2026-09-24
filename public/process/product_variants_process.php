<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../includes/image_upload.php';
require_once __DIR__ . '/../../src/models/Product.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/productos.php');
    exit;
}

$tenantId = currentTenantId();

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}

try {
    $generalName = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '') ?: null;
    $description = trim($_POST['description'] ?? '') ?: null;

    $variantNames = $_POST['variant_name'] ?? [];
    $variantPrices = $_POST['variant_price'] ?? [];
    $variantCosts = $_POST['variant_cost'] ?? [];
    $variantStocks = $_POST['variant_stock'] ?? [];
    $variantSkus = $_POST['variant_sku'] ?? [];

    if ($generalName === '' || !is_array($variantNames) || count($variantNames) === 0) {
        header('Location: ' . BASE_URL . '/productos.php?error=1');
        exit;
    }

    $upload = process_uploaded_image('imagen', 'prod');
    if ($upload['error']) {
        header('Location: ' . BASE_URL . '/productos.php?error=' . $upload['error']);
        exit;
    }

    $created = 0;

    foreach ($variantNames as $index => $rawVariantName) {
        $variantName = trim((string) $rawVariantName);
        $price = trim((string) ($variantPrices[$index] ?? ''));

        if ($variantName === '' || $price === '' || (float) $price < 0) {
            continue;
        }

        Product::create($tenantId, trim($generalName . ' ' . $variantName), (float) $price, [
            'sku' => trim((string) ($variantSkus[$index] ?? '')) ?: null,
            'description' => $description,
            'category' => $category,
            'imagen' => $upload['path'],
            'cost' => (float) ($variantCosts[$index] ?? 0),
            'sale_unit' => 'unit',
            'stock_quantity' => (float) ($variantStocks[$index] ?? 0),
            'show_in_catalog' => isset($_POST['show_in_catalog']) ? 1 : 0,
        ]);
        $created++;
    }

    if ($created === 0) {
        header('Location: ' . BASE_URL . '/productos.php?error=1');
        exit;
    }

    header('Location: ' . BASE_URL . '/productos.php?success=variants&count=' . $created);
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}
