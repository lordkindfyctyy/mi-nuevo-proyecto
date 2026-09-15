<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/productos.php');
    exit;
}

$tenantId = currentTenantId();
$action = $_POST['action'] ?? '';

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}

try {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if ($name === '' || $price < 0) {
            header('Location: ' . BASE_URL . '/productos.php?error=1');
            exit;
        }

        Product::create($tenantId, $name, $price, [
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'category' => trim($_POST['category'] ?? '') ?: null,
            'image_url' => trim($_POST['image_url'] ?? '') ?: null,
            'cost' => (float) ($_POST['cost'] ?? 0),
            'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 0),
        ]);

        header('Location: ' . BASE_URL . '/productos.php?success=created');
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::findForTenant($id, $tenantId);

        if (!$product) {
            header('Location: ' . BASE_URL . '/productos.php?error=1');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if ($name === '' || $price < 0) {
            header('Location: ' . BASE_URL . '/productos.php?error=1');
            exit;
        }

        Product::update($id, [
            'name' => $name,
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'category' => trim($_POST['category'] ?? '') ?: null,
            'image_url' => trim($_POST['image_url'] ?? '') ?: null,
            'price' => $price,
            'cost' => (float) ($_POST['cost'] ?? 0),
            'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 0),
        ]);

        header('Location: ' . BASE_URL . '/productos.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::findForTenant($id, $tenantId);

        if ($product) {
            Product::delete($id);
        }

        header('Location: ' . BASE_URL . '/productos.php?success=deleted');
        exit;
    }

    header('Location: ' . BASE_URL . '/productos.php');
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/productos.php?error=1');
    exit;
}
