<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$tenantId = currentTenantId();
$id = (int) ($_POST['id'] ?? 0);
$stock = $_POST['stock_quantity'] ?? null;

if (!$tenantId || !$id || !is_numeric($stock) || (float) $stock < 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos.']);
    exit;
}

$product = Product::findForTenant($id, $tenantId);
if (!$product) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Producto no encontrado.']);
    exit;
}

Product::update($id, ['stock_quantity' => round((float) $stock, 3)]);

echo json_encode(['ok' => true]);
