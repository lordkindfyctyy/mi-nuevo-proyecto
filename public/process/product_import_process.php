<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Product.php';
require_once __DIR__ . '/../../src/lib/SimpleXLSX/SimpleXLSX.php';
requireLogin();

use Shuchkin\SimpleXLSX;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/productos.php');
    exit;
}

$tenantId = currentTenantId();

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/productos.php?import_error=bad_format');
    exit;
}

if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . BASE_URL . '/productos.php?import_error=no_file');
    exit;
}

$tmpPath = $_FILES['import_file']['tmp_name'];
$originalName = $_FILES['import_file']['name'];

if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
    header('Location: ' . BASE_URL . '/productos.php?import_error=bad_format');
    exit;
}

$xlsx = SimpleXLSX::parse($tmpPath);
if (!$xlsx) {
    header('Location: ' . BASE_URL . '/productos.php?import_error=bad_format');
    exit;
}

$rows = $xlsx->rows();
if (count($rows) < 2) {
    header('Location: ' . BASE_URL . '/productos.php?import_error=empty');
    exit;
}

// Encabezado esperado (case-insensitive, acepta orden distinto):
// Nombre | SKU | Categoría | Marca | Precio | Costo | Stock | Descripción
$header = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), $rows[0]);
$columnIndex = [];
foreach ($header as $i => $label) {
    $columnIndex[$label] = $i;
}

function import_col(array $row, array $columnIndex, array $names): ?string
{
    foreach ($names as $name) {
        if (isset($columnIndex[$name]) && isset($row[$columnIndex[$name]])) {
            $value = trim((string) $row[$columnIndex[$name]]);

            return $value === '' ? null : $value;
        }
    }

    return null;
}

function import_parse_number(?string $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    // Acepta "1.234,56" (formato AR) y "1234.56" (formato US).
    $normalized = str_replace(' ', '', $value);
    if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (strpos($normalized, ',') !== false) {
        $normalized = str_replace(',', '.', $normalized);
    }

    return is_numeric($normalized) ? (float) $normalized : null;
}

$created = 0;
$skipped = 0;

for ($i = 1, $total = count($rows); $i < $total; $i++) {
    $row = $rows[$i];
    if (!array_filter($row, static fn ($v) => trim((string) $v) !== '')) {
        continue; // fila completamente vacía, no cuenta como error
    }

    $name = import_col($row, $columnIndex, ['nombre', 'producto', 'name']);
    $price = import_parse_number(import_col($row, $columnIndex, ['precio', 'price']));

    if ($name === null || $price === null || $price < 0) {
        $skipped++;
        continue;
    }

    $cost = import_parse_number(import_col($row, $columnIndex, ['costo', 'cost'])) ?? 0;
    $stock = import_parse_number(import_col($row, $columnIndex, ['stock', 'stock inicial', 'cantidad'])) ?? 0;
    $sku = import_col($row, $columnIndex, ['sku', 'código', 'codigo']);
    $category = import_col($row, $columnIndex, ['categoría', 'categoria', 'category']);
    $brand = import_col($row, $columnIndex, ['marca', 'brand']);
    $description = import_col($row, $columnIndex, ['descripción', 'descripcion', 'description']);

    try {
        Product::create($tenantId, $name, $price, [
            'sku' => $sku,
            'category' => $category,
            'brand' => $brand,
            'description' => $description,
            'cost' => $cost,
            'stock_quantity' => $stock,
        ]);
        $created++;
    } catch (Throwable $e) {
        // SKU duplicado u otro error puntual de esta fila: seguimos con las demás.
        $skipped++;
    }
}

if ($created === 0) {
    header('Location: ' . BASE_URL . '/productos.php?import_error=all_failed');
    exit;
}

header('Location: ' . BASE_URL . '/productos.php?success=import&count=' . $created . '&skipped=' . $skipped);
exit;
