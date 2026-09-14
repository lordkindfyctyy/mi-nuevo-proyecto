<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Sale.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$products = $tenantId ? Product::allByTenant($tenantId) : [];
$products = array_filter($products, fn($p) => $p['status'] === 'active' && (int) $p['stock_quantity'] > 0);

$lastSale = null;
$lastSaleItems = [];
if (isset($_GET['success'], $_GET['sale']) && $tenantId) {
    $lastSale = Sale::findForTenant((int) $_GET['sale'], $tenantId);
    if ($lastSale) {
        $lastSaleItems = Sale::itemsFor((int) $_GET['sale']);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Registrar venta</h1>
</div>

<?php if ($lastSale): ?>
    <div class="alert alert-success">
        <strong>Venta #<?= (int) $lastSale['id'] ?> registrada.</strong>
        Total: $<?= number_format((float) $lastSale['total'], 2) ?>
        <ul class="mb-0 mt-2">
            <?php foreach ($lastSaleItems as $item): ?>
                <li><?= htmlspecialchars($item['product_name']) ?> x <?= (int) $item['quantity'] ?> = $<?= number_format((float) $item['subtotal'], 2) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        $errors = [
            'stock' => 'No hay suficiente stock para uno o más productos seleccionados.',
            'items' => 'Selecciona al menos un producto con cantidad mayor a cero.',
        ];
        echo $errors[$_GET['error']] ?? 'No se pudo registrar la venta. Intenta de nuevo.';
        ?>
    </div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> primero.</div>
<?php elseif (!$products): ?>
    <div class="alert alert-warning">No hay productos con stock disponible. Agrega productos desde <a href="<?= BASE_URL ?>/productos.php">Productos</a>.</div>
<?php else: ?>

<form method="POST" action="<?= BASE_URL ?>/process/sale_process.php" id="sale-form">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Stock</th>
                            <th style="width: 120px;">Cantidad</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td class="text-end" data-price="<?= (float) $product['price'] ?>">$<?= number_format((float) $product['price'], 2) ?></td>
                                <td class="text-end"><?= (int) $product['stock_quantity'] ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm qty-input"
                                           name="quantity[<?= (int) $product['id'] ?>]"
                                           min="0" max="<?= (int) $product['stock_quantity'] ?>" value="0">
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
                    <div class="mb-3">
                        <label for="customer_name" class="form-label">Cliente (opcional)</label>
                        <input type="text" id="customer_name" name="customer_name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Método de pago</label>
                        <select id="payment_method" name="payment_method" class="form-select">
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="transfer">Transferencia</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-3 mb-3">
                        <span>Total</span>
                        <span id="grand-total">$0.00</span>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Registrar venta</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('#sale-form tbody tr');
    const grandTotalEl = document.getElementById('grand-total');

    function formatMoney(value) {
        return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalculate() {
        let total = 0;
        rows.forEach((row) => {
            const price = parseFloat(row.querySelector('[data-price]').dataset.price);
            const qtyInput = row.querySelector('.qty-input');
            const qty = Math.max(0, parseInt(qtyInput.value || '0', 10));
            const subtotal = price * qty;
            row.querySelector('.subtotal-cell').textContent = formatMoney(subtotal);
            total += subtotal;
        });
        grandTotalEl.textContent = formatMoney(total);
    }

    document.querySelectorAll('.qty-input').forEach((input) => {
        input.addEventListener('input', recalculate);
    });

    recalculate();
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
