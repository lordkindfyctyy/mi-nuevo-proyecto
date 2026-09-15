<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Sale.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$products = $tenantId ? Product::allByTenant($tenantId) : [];
$products = array_values(array_filter($products, fn($p) => $p['status'] === 'active' && (int) $p['stock_quantity'] > 0));

$productsJson = json_encode(array_map(fn($p) => [
    'id' => (int) $p['id'],
    'name' => $p['name'],
    'sku' => $p['sku'] ?? '',
    'price' => (float) $p['price'],
    'stock' => (int) $p['stock_quantity'],
], $products), JSON_UNESCAPED_UNICODE);

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
    <div id="cart-inputs"></div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="mb-3">
                <input type="search" id="product-search" class="form-control form-control-lg"
                       placeholder="Buscar producto por nombre o SKU..." autocomplete="off">
            </div>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody id="product-list"></tbody>
                </table>
                <p id="no-results" class="text-center text-secondary py-4 mb-0" hidden>No se encontraron productos.</p>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 fw-semibold mb-3">Carrito</h2>
                    <div id="cart-empty" class="text-secondary small py-3 text-center">
                        Todavía no has agregado productos.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="cart-table" hidden>
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width: 90px;">Cant.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items"></tbody>
                        </table>
                    </div>
                    <hr>
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
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" id="submit-btn" disabled>Registrar venta</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = <?= $productsJson ?: '[]' ?>;
    const cart = new Map();

    const searchInput = document.getElementById('product-search');
    const productListEl = document.getElementById('product-list');
    const noResultsEl = document.getElementById('no-results');
    const cartItemsEl = document.getElementById('cart-items');
    const cartTableEl = document.getElementById('cart-table');
    const cartEmptyEl = document.getElementById('cart-empty');
    const grandTotalEl = document.getElementById('grand-total');
    const submitBtn = document.getElementById('submit-btn');
    const cartInputsEl = document.getElementById('cart-inputs');

    function formatMoney(value) {
        return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function availableStock(product) {
        const inCart = cart.get(product.id);
        return product.stock - (inCart ? inCart.quantity : 0);
    }

    function renderProductList() {
        const term = searchInput.value.trim().toLowerCase();
        const filtered = products.filter((p) => {
            if (!term) return true;
            return p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
        });

        productListEl.innerHTML = '';
        noResultsEl.hidden = filtered.length > 0;

        filtered.forEach((product) => {
            const remaining = availableStock(product);
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(product.name)}</td>
                <td class="text-secondary">${escapeHtml(product.sku || '—')}</td>
                <td class="text-end">${formatMoney(product.price)}</td>
                <td class="text-end">${remaining}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary add-btn" ${remaining <= 0 ? 'disabled' : ''}>
                        Agregar
                    </button>
                </td>
            `;
            tr.querySelector('.add-btn').addEventListener('click', () => addToCart(product));
            productListEl.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function addToCart(product) {
        if (availableStock(product) <= 0) return;
        const existing = cart.get(product.id);
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.set(product.id, { product, quantity: 1 });
        }
        renderAll();
    }

    function updateQuantity(productId, quantity) {
        const entry = cart.get(productId);
        if (!entry) return;
        quantity = Math.max(1, Math.min(quantity, entry.product.stock));
        entry.quantity = quantity;
        renderAll();
    }

    function removeFromCart(productId) {
        cart.delete(productId);
        renderAll();
    }

    function renderCart() {
        cartItemsEl.innerHTML = '';
        const hasItems = cart.size > 0;
        cartTableEl.hidden = !hasItems;
        cartEmptyEl.hidden = hasItems;
        submitBtn.disabled = !hasItems;

        let total = 0;
        cart.forEach(({ product, quantity }) => {
            const subtotal = product.price * quantity;
            total += subtotal;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(product.name)}</td>
                <td>
                    <input type="number" class="form-control form-control-sm cart-qty-input"
                           min="1" max="${product.stock}" value="${quantity}">
                </td>
                <td class="text-end">${formatMoney(subtotal)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-btn" aria-label="Quitar">&times;</button>
                </td>
            `;
            tr.querySelector('.cart-qty-input').addEventListener('input', (e) => {
                updateQuantity(product.id, parseInt(e.target.value || '1', 10));
            });
            tr.querySelector('.remove-btn').addEventListener('click', () => removeFromCart(product.id));
            cartItemsEl.appendChild(tr);
        });

        grandTotalEl.textContent = formatMoney(total);

        cartInputsEl.innerHTML = '';
        cart.forEach(({ product, quantity }) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `quantity[${product.id}]`;
            input.value = quantity;
            cartInputsEl.appendChild(input);
        });
    }

    function renderAll() {
        renderProductList();
        renderCart();
    }

    searchInput.addEventListener('input', renderProductList);

    renderAll();
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
