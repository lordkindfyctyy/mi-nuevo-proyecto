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

/**
 * Validates and stores an uploaded product photo under public/uploads/,
 * with a server-generated unique name and an extension derived from the
 * file's real detected type (never from the client-supplied filename).
 *
 * @return array{provided: bool, path: ?string, error: ?string}
 */
function processUploadedProductImage(): array
{
    if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
        return ['provided' => false, 'path' => null, 'error' => null];
    }

    if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($_FILES['imagen']['size'] > $maxBytes) {
        return ['provided' => true, 'path' => null, 'error' => 'size'];
    }

    $imageInfo = @getimagesize($_FILES['imagen']['tmp_name']);
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if ($imageInfo === false || !isset($mimeToExt[$imageInfo['mime']])) {
        return ['provided' => true, 'path' => null, 'error' => 'type'];
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    $filename = uniqid('prod_', true) . '.' . $mimeToExt[$imageInfo['mime']];
    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
        return ['provided' => true, 'path' => null, 'error' => 'upload'];
    }

    return ['provided' => true, 'path' => 'uploads/' . $filename, 'error' => null];
}

function deleteProductImageFile(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $fullPath = __DIR__ . '/../' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

try {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if ($name === '' || $price < 0) {
            header('Location: ' . BASE_URL . '/productos.php?error=1');
            exit;
        }

        $upload = processUploadedProductImage();
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

        $upload = processUploadedProductImage();
        if ($upload['error']) {
            header('Location: ' . BASE_URL . '/productos.php?error=' . $upload['error']);
            exit;
        }

        $imagen = $product['imagen'];
        if ($upload['provided'] && $upload['path']) {
            deleteProductImageFile($product['imagen']);
            $imagen = $upload['path'];
        } elseif (isset($_POST['remove_image'])) {
            deleteProductImageFile($product['imagen']);
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
        ]);

        header('Location: ' . BASE_URL . '/productos.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $product = Product::findForTenant($id, $tenantId);

        if ($product) {
            deleteProductImageFile($product['imagen']);
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
