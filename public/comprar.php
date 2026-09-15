<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Supplier.php';
require_once __DIR__ . '/../src/models/Purchase.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$products = $tenantId ? Product::allByTenant($tenantId) : [];
$products = array_filter($products, fn($p) => $p['status'] === 'active');
$suppliers = $tenantId ? Supplier::allByTenant($tenantId) : [];
$suppliers = array_filter($suppliers, fn($s) => $s['status'] === 'active');

$lastPurchase = null;
$lastPurchaseItems = [];
if (isset($_GET['success'], $_GET['purchase']) && $tenantId) {
    $lastPurchase = Purchase::findForTenant((int) $_GET['purchase'], $tenantId);
    if ($lastPurchase) {
        $lastPurchaseItems = Purchase::itemsFor((int) $_GET['purchase']);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Registrar compra</h1>
</div>

<?php if ($lastPurchase): ?>
    <div class="alert alert-success">
        <strong>Compra #<?= (int) $lastPurchase['id'] ?> registrada.</strong>
        Total: $<?= number_format((float) $lastPurchase['total'], 2) ?>. El stock de los productos se actualizó automáticamente.
        <ul class="mb-0 mt-2">
            <?php foreach ($lastPurchaseItems as $item): ?>
                <li><?= htmlspecialchars($item['product_name']) ?> x <?= (int) $item['quantity'] ?> = $<?= number_format((float) $item['subtotal'], 2) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        $errors = [
            'items' => 'Selecciona al menos un producto con cantidad mayor a cero.',
            'supplier' => 'Selecciona un proveedor válido.',
        ];
        echo $errors[$_GET['error']] ?? 'No se pudo registrar la compra. Intenta de nuevo.';
        ?>
    </div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> primero.</div>
<?php elseif (!$suppliers): ?>
    <div class="alert alert-warning">No hay proveedores activos. Agrega uno desde <a href="<?= BASE_URL ?>/proveedores.php">Proveedores</a>.</div>
<?php elseif (!$products): ?>
    <div class="alert alert-warning">No hay productos disponibles. Agrega productos desde <a href="<?= BASE_URL ?>/productos.php">Productos</a>.</div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/process/purchase_process.php" id="purchase-form">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="mb-3">
                <label for="supplier_id" class="form-label">Proveedor</label>
                <select id="supplier_id" name="supplier_id" class="form-select" required>
                    <option value="">Selecciona un proveedor</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Stock actual</th>
                            <th style="width: 140px;">Costo unitario</th>
                            <th style="width: 120px;">Cantidad</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td class="text-end"><?= (int) $product['stock_quantity'] ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm cost-input"
                                           data-cost="<?= (float) $product['cost'] ?>"
                                           name="unit_cost[<?= (int) $product['id'] ?>]" value="<?= (float) $product['cost'] ?>">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm qty-input"
                                           name="quantity[<?= (int) $product['id'] ?>]" min="0" value="0">
                                </td>
                                <td class="text-end subtotal-cell">$0.00</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 fw-semibold mb-3">Resumen</h2>
                    <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-3 mb-3">
                        <span>Total</span>
                        <span id="grand-total">$0.00</span>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Registrar compra</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('#purchase-form tbody tr');
    const grandTotalEl = document.getElementById('grand-total');

    function formatMoney(value) {
        return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculate() {
        let total = 0;
        rows.forEach((row) => {
            const cost = parseFloat(row.querySelector('.cost-input').value || '0');
            const qtyInput = row.querySelector('.qty-input');
            const qty = Math.max(0, parseInt(qtyInput.value || '0', 10));
            const subtotal = cost * qty;
            row.querySelector('.subtotal-cell').textContent = formatMoney(subtotal);
            total += subtotal;
        });
        grandTotalEl.textContent = formatMoney(total);
    }

    document.querySelectorAll('.qty-input, .cost-input').forEach((input) => {
        input.addEventListener('input', recalculate);
    });

    recalculate();
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
