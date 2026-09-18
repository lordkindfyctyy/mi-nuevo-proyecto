<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Sale.php';
require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../includes/receipt_helpers.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$tenant = $tenantId ? Tenant::find($tenantId) : null;
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

<?php if (isset($_GET['edit_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Venta actualizada correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif (isset($_GET['edit_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">No se pudo actualizar la venta<?= $_GET['edit_error'] === 'stock' ? ' (no hay suficiente stock para uno o más productos)' : '' ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['cancel_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Venta anulada y stock restituido.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif (isset($_GET['cancel_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">No se pudo anular la venta.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

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
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?= (int) $sale['id'] ?>">
                            Ver detalle
                        </button>
                        <?php $saleWhatsappUrl = receipt_whatsapp_share_url($tenant['name'] ?? APP_NAME, $sale, receipt_public_url(Sale::getOrCreatePublicToken((int) $sale['id']))); ?>
                        <a href="<?= htmlspecialchars($saleWhatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Compartir por WhatsApp" aria-label="Compartir por WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <?php if ($sale['status'] === 'completed'): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary sale-edit-trigger" data-sale-id="<?= (int) $sale['id'] ?>" title="Editar venta" aria-label="Editar venta">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="<?= BASE_URL ?>/process/sale_cancel_process.php" class="d-inline" onsubmit="return confirm('¿Anular la venta #<?= (int) $sale['id'] ?>? Se restituirá el stock de los productos vendidos.');">
                                <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
                                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Anular venta" aria-label="Anular venta">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/sale_edit_modal.php'; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
