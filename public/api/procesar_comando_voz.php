<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/services/GeminiVoiceCommandParser.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

function voice_command_fail(int $status, string $error): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voice_command_fail(405, 'Método no permitido.');
}

$tenantId = currentTenantId();
if (!$tenantId) {
    voice_command_fail(400, 'No hay ningún negocio activo en esta sesión.');
}

if (!AI_VOICE_API_KEY) {
    voice_command_fail(503, 'El comando de voz por IA no está configurado en este servidor.');
}

if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    voice_command_fail(400, 'No se recibió ningún audio.');
}

// ~8MB alcanza de sobra para un comando hablado corto (unos 20-30s
// comprimidos en Opus); evita que una grabación larga o un archivo
// manipulado disparen una llamada innecesariamente cara a la IA.
$maxBytes = 8 * 1024 * 1024;
if ($_FILES['audio']['size'] > $maxBytes) {
    voice_command_fail(400, 'El audio es demasiado largo. Grabá un comando más corto.');
}

$audioBytes = file_get_contents($_FILES['audio']['tmp_name']);
if ($audioBytes === false || $audioBytes === '') {
    voice_command_fail(400, 'El audio recibido está vacío.');
}
$mimeType = $_FILES['audio']['type'] ?: 'audio/webm';

$products = array_values(array_filter(Product::allByTenant($tenantId), fn($p) => $p['status'] === 'active'));
if (!$products) {
    voice_command_fail(400, 'No hay productos activos para reconocer por voz.');
}

$catalog = array_map(fn($p) => [
    'id' => (int) $p['id'],
    'sku' => $p['sku'] ?? '',
    'nombre' => $p['name'],
    'precio' => (float) $p['price'],
    'unidad' => ($p['sale_unit'] ?? 'unit') === 'weight' ? 'peso' : 'unidad',
], $products);

try {
    $parsed = GeminiVoiceCommandParser::parse($audioBytes, $mimeType, $catalog);
} catch (Throwable $e) {
    voice_command_fail(502, 'No se pudo interpretar el audio: ' . $e->getMessage());
}

// Nunca se confía en los datos que devuelve la IA tal cual: cada producto
// se vuelve a buscar en el catálogo real del tenant, y las cantidades se
// recalculan/recortan igual que en sale_process.php.
$byId = [];
$bySku = [];
foreach ($products as $p) {
    $byId[(int) $p['id']] = $p;
    if (!empty($p['sku'])) {
        $bySku[mb_strtolower($p['sku'])] = $p;
    }
}

$items = [];
$warnings = [];

foreach ((array) ($parsed['items'] ?? []) as $rawItem) {
    if (!is_array($rawItem)) {
        continue;
    }

    $product = null;
    if (!empty($rawItem['producto_id']) && isset($byId[(int) $rawItem['producto_id']])) {
        $product = $byId[(int) $rawItem['producto_id']];
    } elseif (!empty($rawItem['sku']) && isset($bySku[mb_strtolower((string) $rawItem['sku'])])) {
        $product = $bySku[mb_strtolower((string) $rawItem['sku'])];
    }

    $detectedName = (string) ($rawItem['nombre_detectado'] ?? '?');

    if (!$product) {
        $warnings[] = 'No se encontró ningún producto que coincida con "' . $detectedName . '".';
        continue;
    }

    $price = (float) $product['price'];
    $isWeight = ($product['sale_unit'] ?? 'unit') === 'weight';

    $quantity = null;
    if (isset($rawItem['monto']) && is_numeric($rawItem['monto']) && (float) $rawItem['monto'] > 0 && $price > 0) {
        $quantity = (float) $rawItem['monto'] / $price;
    } elseif (isset($rawItem['cantidad']) && is_numeric($rawItem['cantidad'])) {
        $quantity = (float) $rawItem['cantidad'];
    }

    if ($quantity === null || $quantity <= 0) {
        $warnings[] = 'No se entendió la cantidad para "' . $product['name'] . '".';
        continue;
    }

    $quantity = $isWeight ? round($quantity, 3) : (float) round($quantity);
    $quantity = min($quantity, (float) $product['stock_quantity']);

    if ($quantity <= 0) {
        $warnings[] = $product['name'] . ' no tiene stock disponible.';
        continue;
    }

    $items[] = [
        'product_id' => (int) $product['id'],
        'name' => $product['name'],
        'quantity' => $quantity,
        'unit_price' => $price,
    ];
}

$discount = null;
if (!empty($parsed['descuento']) && is_array($parsed['descuento'])) {
    $tipo = $parsed['descuento']['tipo'] ?? null;
    $tipo = $tipo === 'fixed' ? 'fixed' : ($tipo === 'percent' ? 'percent' : null);
    $valor = $parsed['descuento']['valor'] ?? null;
    if ($tipo && is_numeric($valor) && (float) $valor > 0) {
        $discount = ['type' => $tipo, 'value' => (float) $valor];
    }
}

if (!$items && !$warnings) {
    $warnings[] = 'No se detectó ningún producto en el audio.';
}

echo json_encode([
    'ok' => true,
    'items' => $items,
    'discount' => $discount,
    'warnings' => $warnings,
    'transcript' => $parsed['transcripcion'] ?? null,
], JSON_UNESCAPED_UNICODE);
