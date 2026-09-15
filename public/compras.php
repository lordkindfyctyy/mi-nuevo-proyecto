<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Purchase.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$purchases = $tenantId ? Purchase::allByTenant($tenantId) : [];

$totalComprado = array_sum(array_map(
    fn($p) => $p['status'] === 'completed' ? (float) $p['total'] : 0,
    $purchases
));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Historial de compras</h1>
    <a href="<?= BASE_URL ?>/comprar.php" class="btn btn-primary rounded-pill px-4">Registrar compra</a>
</div>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía.</div>
<?php elseif (!$purchases): ?>
    <div class="alert alert-info">Todavía no has registrado ninguna compra. <a href="<?= BASE_URL ?>/comprar.php">Registra la primera</a>.</div>
<?php else: ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Compras registradas</div>
                <div class="h4 fw-bold mb-0"><?= count($purchases) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total comprado</div>
                <div class="h4 fw-bold mb-0">$<?= number_format($totalComprado, 2) ?></div>
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
                <th>Proveedor</th>
                <th>Registrado por</th>
                <th class="text-end">Total</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td>#<?= (int) $purchase['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?></td>
                    <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($purchase['buyer_name']) ?></td>
                    <td class="text-end">$<?= number_format((float) $purchase['total'], 2) ?></td>
                    <td>
                        <span class="badge <?= $purchase['status'] === 'completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                            <?= $purchase['status'] === 'completed' ? 'Completada' : 'Cancelada' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#purchase-items-<?= (int) $purchase['id'] ?>">
                            Ver detalle
                        </button>
                    </td>
                </tr>
                <tr class="collapse" id="purchase-items-<?= (int) $purchase['id'] ?>">
                    <td colspan="7" class="bg-light">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Costo unitario</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (Purchase::itemsFor((int) $purchase['id']) as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="text-end"><?= (int) $item['quantity'] ?></td>
                                        <td class="text-end">$<?= number_format((float) $item['unit_cost'], 2) ?></td>
                                        <td class="text-end">$<?= number_format((float) $item['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
