<?php

require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../src/models/User.php';
require_once __DIR__ . '/../src/models/Product.php';

$tenantId = Tenant::create('Tienda Doña Rosa', 'contacto@donarosa.test', '300-123-4567');
echo "Tenant creado: $tenantId\n";

$ownerId = User::create($tenantId, 'Rosa Gómez', 'rosa@donarosa.test', 'password123', 'owner');
$employeeId = User::create($tenantId, 'Carlos Pérez', 'carlos@donarosa.test', 'password123', 'employee');
echo "Usuarios creados: $ownerId, $employeeId\n";

$products = [
    ['name' => 'Arroz 1kg', 'sku' => 'ARR-001', 'price' => 4500, 'cost' => 3200, 'stock_quantity' => 50],
    ['name' => 'Aceite 1L', 'sku' => 'ACE-001', 'price' => 9800, 'cost' => 7500, 'stock_quantity' => 30],
    ['name' => 'Leche 1L', 'sku' => 'LEC-001', 'price' => 3200, 'cost' => 2400, 'stock_quantity' => 40],
    ['name' => 'Panela 500g', 'sku' => 'PAN-001', 'price' => 2800, 'cost' => 1900, 'stock_quantity' => 25],
    ['name' => 'Café 250g', 'sku' => 'CAF-001', 'price' => 11500, 'cost' => 8700, 'stock_quantity' => 15],
];

foreach ($products as $data) {
    $id = Product::create($tenantId, $data['name'], $data['price'], $data);
    echo "Producto creado: $id - {$data['name']}\n";
}

echo "Listo.\n";
