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

        $upload = process_uploaded_image('imagen', 'prod');
        if ($upload['error']) {
            header('Location: ' . BASE_URL . '/productos.php?error=' . $upload['error']);
            exit;
        }

        Product::create($tenantId, $name, $price, [
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'category' => trim($_POST['category'] ?? '') ?: null,
            'brand' => trim($_POST['brand'] ?? '') ?: null,
            'imagen' => $upload['path'],
            'cost' => (float) ($_POST['cost'] ?? 0),
            'sale_unit' => $_POST['sale_unit'] ?? 'unit',
            'stock_quantity' => (float) ($_POST['stock_quantity'] ?? 0),
            'show_in_catalog' => isset($_POST['show_in_catalog']) ? 1 : 0,
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

        $upload = process_uploaded_image('imagen', 'prod');
        if ($upload['error']) {
            header('Location: ' . BASE_URL . '/productos.php?error=' . $upload['error']);
            exit;
        }

        $imagen = $product['imagen'];
        if ($upload['provided'] && $upload['path']) {
            delete_uploaded_image_file($product['imagen']);
            $imagen = $upload['path'];
        } elseif (isset($_POST['remove_image'])) {
            delete_uploaded_image_file($product['imagen']);
            $imagen = null;
        }

        Product::update($id, [
            'name' => $name,
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'category' => trim($_POST['category'] ?? '') ?: null,
            'brand' => trim($_POST['brand'] ?? '') ?: null,
            'imagen' => $imagen,
            'price' => $price,
            'cost' => (float) ($_POST['cost'] ?? 0),
            'sale_unit' => $_POST['sale_unit'] ?? 'unit',
            'stock_quantity' => (float) ($_POST['stock_quantity'] ?? 0),
            'show_in_catalog' => isset($_POST['show_in_catalog']) ? 1 : 0,
        ]);

        header('Location: ' . BASE_URL . '/productos.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::findForTenant($id, $tenantId);

        if ($product) {
            delete_uploaded_image_file($product['imagen']);
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
