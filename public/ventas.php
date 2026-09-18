<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Sale.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$sales = $tenantId ? Sale::allByTenant($tenantId) : [];

$paymentLabels = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR',
    'other' => 'Otro',
];

$totalVendido = array_sum(array_map(
    fn($s) => $s['status'] === 'completed' ? (float) $s['total'] : 0,
    $sales
));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Historial de ventas</h1>
</div>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía.</div>
<?php elseif (!$sales): ?>
    <div class="alert alert-info">Todavía no has registrado ninguna venta. <a href="<?= BASE_URL ?>/vender.php">Registra la primera</a>.</div>
<?php else: ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Ventas registradas</div>
                <div class="h4 fw-bold mb-0"><?= count($sales) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total vendido</div>
                <div class="h4 fw-bold mb-0">$<?= number_format($totalVendido, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Pago</th>
                <th class="text-end">Total</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td>#<?= (int) $sale['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                    <td><?= htmlspecialchars($sale['customer_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($sale['seller_name']) ?></td>
                    <td><?= $paymentLabels[$sale['payment_method']] ?? $sale['payment_method'] ?></td>
                    <td class="text-end">$<?= number_format((float) $sale['total'], 2) ?></td>
                    <td>
                        <span class="badge <?= $sale['status'] === 'completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                            <?= $sale['status'] === 'completed' ? 'Completada' : 'Cancelada' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?= (int) $sale['id'] ?>">
                            Ver detalle
                        </button>
                    </td>
                </tr>
                <tr class="collapse" id="items-<?= (int) $sale['id'] ?>">
                    <td colspan="8" class="bg-light">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Precio unitario</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (Sale::itemsFor((int) $sale['id']) as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="text-end"><?= Product::formatQuantity($item['quantity']) ?></td>
                                        <td class="text-end">$<?= number_format((float) $item['unit_price'], 2) ?></td>
                                        <td class="text-end">$<?= number_format((float) $item['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ((float) $sale['discount_amount'] > 0): ?>
                                    <tr class="text-danger">
                                        <td colspan="3" class="text-end fw-semibold">Descuento aplicado</td>
                                        <td class="text-end fw-semibold">- $<?= number_format((float) $sale['discount_amount'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end fw-semibold">Total cobrado</td>
                                        <td class="text-end fw-semibold">$<?= number_format((float) $sale['total'], 2) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
