<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../src/models/Product.php';

$token = $_GET['t'] ?? '';
$tenant = $token !== '' ? Tenant::findByPublicToken($token) : null;

if (!$tenant) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Catálogo no encontrado</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head>
    <body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f2f4;">
        <div class="text-center p-4">
            <h1 class="h4 fw-bold mb-2">Catálogo no encontrado</h1>
            <p class="text-secondary">El enlace que abriste no es válido o ya no está disponible.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$products = Product::allByTenant((int) $tenant['id']);
$products = array_values(array_filter($products, fn($p) => $p['status'] === 'active'));

$productsData = array_map(fn($p) => [
    'id' => (int) $p['id'],
    'name' => $p['name'],
    'sku' => $p['sku'] ?? '',
    'price' => (float) $p['price'],
    'stock' => (int) $p['stock_quantity'],
    'category' => $p['category'] ?? '',
    'brand' => $p['brand'] ?? '',
    'image' => Product::imageUrl($p) ?? '',
    'description' => $p['description'] ?? '',
], $products);

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['products' => $productsData], JSON_UNESCAPED_UNICODE);
    exit;
}

$productsJson = json_encode($productsData, JSON_UNESCAPED_UNICODE);
$whatsappDigits = preg_replace('/\D+/', '', $tenant['whatsapp_phone'] ?? '');
$whatsappGeneralUrl = $whatsappDigits
    ? 'https://wa.me/' . $whatsappDigits . '?text=' . rawurlencode('Hola, quiero hacer una consulta sobre sus productos.')
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tenant['name']) ?> · Catálogo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { margin: 0; background: #f1f2f4; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .catalog-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 1rem; position: sticky; top: 0; z-index: 10; }
        .catalog-body { max-width: 1100px; margin: 0 auto; padding: 1rem; }
        .catalog-search-row { display: flex; gap: .5rem; flex-wrap: wrap; }
        .catalog-search-row .input-group { flex: 1 1 220px; }
        .catalog-brand-select { flex: 0 1 180px; min-width: 140px; }
        .catalog-filters { display: flex; gap: .5rem; flex-wrap: wrap; margin: .75rem 0 1rem; }
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 1rem; }
        .catalog-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: box-shadow .15s; }
        .catalog-card:hover { box-shadow: 0 .4rem .8rem rgba(0,0,0,.08); }
        .catalog-card-image { aspect-ratio: 1 / 1; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 2rem; overflow: hidden; position: relative; }
        .catalog-card-image img { width: 100%; height: 100%; object-fit: cover; }
        .catalog-stock-badge { position: absolute; top: .4rem; right: .4rem; font-size: .68rem; }
        .catalog-card-body { padding: .65rem .8rem .8rem; display: flex; flex-direction: column; gap: .2rem; flex: 1; }
        .catalog-card-price { font-weight: 700; color: #00b28f; font-size: 1.05rem; }
        .catalog-card-name { font-weight: 600; font-size: .88rem; line-height: 1.25; }
        .catalog-card-sku { font-size: .72rem; color: #6b7280; }
        .catalog-card-actions { margin-top: auto; display: flex; gap: .35rem; padding-top: .35rem; }
        .catalog-in-cart-note { font-size: .72rem; color: #00b28f; font-weight: 600; }
        .catalog-whatsapp-fab {
            position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 20;
            width: 3.5rem; height: 3.5rem; border-radius: 50%; background: #25D366; color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.75rem;
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.2); text-decoration: none;
        }
        .catalog-whatsapp-fab:hover { background: #1ebe5a; color: #fff; }
        .cart-item-row { display: flex; align-items: center; gap: .6rem; padding: .6rem 0; border-bottom: 1px solid #f1f2f4; }
        .cart-item-name { font-weight: 600; font-size: .85rem; }
        .cart-item-price { font-size: .75rem; color: #6b7280; }
        .cart-qty { display: flex; align-items: center; gap: .35rem; }
        .cart-qty button { width: 1.6rem; height: 1.6rem; padding: 0; line-height: 1; }
        .detail-image { width: 100%; height: 220px; object-fit: contain; border-radius: .5rem; background: #f3f4f6; display: block; }
        .detail-placeholder { width: 100%; height: 220px; background: #f3f4f6; border-radius: .5rem; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 3rem; }
    </style>
</head>
<body>
    <div class="catalog-header">
        <div class="catalog-body py-0 d-flex align-items-center justify-content-between gap-3">
            <div class="min-w-0">
                <h1 class="h5 fw-bold mb-0 text-truncate"><?= htmlspecialchars($tenant['name']) ?></h1>
                <div class="text-secondary small">Catálogo en vivo · <span id="last-updated">actualizando...</span></div>
            </div>
            <?php if ($products): ?>
            <button type="button" class="btn btn-primary position-relative flex-shrink-0" data-bs-toggle="modal" data-bs-target="#cartModal">
                <i class="bi bi-cart3"></i>
                <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle" id="cart-badge" hidden>0</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="catalog-body">
        <?php if (!$products): ?>
            <p class="text-center text-secondary py-5">Este negocio todavía no tiene productos publicados.</p>
        <?php else: ?>
            <div class="catalog-search-row">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" id="catalog-search" class="form-control" placeholder="Buscar producto..." autocomplete="off">
                </div>
                <select id="catalog-brand-filter" class="form-select catalog-brand-select"></select>
            </div>
            <div class="catalog-filters" id="catalog-filters"></div>
            <div class="catalog-grid" id="catalog-grid"></div>
            <p id="catalog-no-results" class="text-center text-secondary py-5" hidden>No se encontraron productos.</p>
        <?php endif; ?>
    </div>

    <?php if ($whatsappGeneralUrl): ?>
        <a href="<?= htmlspecialchars($whatsappGeneralUrl) ?>" class="catalog-whatsapp-fab" target="_blank" rel="noopener" aria-label="Chatear por WhatsApp">
            <i class="bi bi-whatsapp"></i>
        </a>
    <?php endif; ?>

    <div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tu pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="cart-empty-msg" class="text-secondary text-center py-3">Todavía no agregaste productos.</div>
                    <div id="cart-items-list"></div>
                    <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-3 mt-2" id="cart-total-row" hidden>
                        <span>Total</span>
                        <span id="cart-total-amount">$0.00</span>
                    </div>
                </div>
                <div class="modal-footer flex-column align-items-stretch">
                    <button type="button" id="proceed-checkout-btn" class="btn btn-success w-100" disabled>
                        <i class="bi bi-whatsapp"></i> Enviar pedido por WhatsApp
                    </button>
                    <p id="whatsapp-order-hint" class="text-secondary small text-center mb-0 mt-2" hidden>Este negocio todavía no tiene WhatsApp configurado. Contáctalo directamente.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">Antes de enviarlo por WhatsApp, contanos a quién y dónde entregarlo.</p>
                    <div id="checkout-error" class="alert alert-danger py-2 small" hidden>Completa tu nombre y dirección para continuar.</div>
                    <div class="mb-3">
                        <label for="checkout-name" class="form-label">Nombre completo</label>
                        <input type="text" id="checkout-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="checkout-address" class="form-label">Dirección de entrega</label>
                        <input type="text" id="checkout-address" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer flex-column align-items-stretch">
                    <button type="button" id="confirm-checkout-btn" class="btn btn-success w-100">
                        <i class="bi bi-whatsapp"></i> Confirmar y enviar por WhatsApp
                    </button>
                    <button type="button" id="back-to-cart-btn" class="btn btn-link btn-sm text-decoration-none">&laquo; Volver al carrito</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detail-name">Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="detail-image-wrap" class="mb-3"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fs-4 fw-bold" style="color:#00b28f;" id="detail-price">$0.00</div>
                        <span class="badge" id="detail-stock-badge"></span>
                    </div>
                    <div class="text-secondary small mb-2" id="detail-meta"></div>
                    <p id="detail-description" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary flex-grow-1" id="detail-add-btn">Agregar</button>
                    <a href="#" id="detail-ask-btn" target="_blank" rel="noopener" class="btn btn-outline-success" hidden>
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        let products = <?= $productsJson ?: '[]' ?>;
        const whatsappDigits = <?= json_encode($whatsappDigits ?: '') ?>;
        const cartStorageKey = 'catalog_cart_' + <?= json_encode($token) ?>;
        let activeCategory = 'all';
        let activeBrand = 'all';

        const searchInput = document.getElementById('catalog-search');
        const brandFilterEl = document.getElementById('catalog-brand-filter');
        const filtersEl = document.getElementById('catalog-filters');
        const gridEl = document.getElementById('catalog-grid');
        const noResultsEl = document.getElementById('catalog-no-results');
        const lastUpdatedEl = document.getElementById('last-updated');
        const cartBadgeEl = document.getElementById('cart-badge');
        const cartEmptyMsgEl = document.getElementById('cart-empty-msg');
        const cartItemsListEl = document.getElementById('cart-items-list');
        const cartTotalRowEl = document.getElementById('cart-total-row');
        const cartTotalAmountEl = document.getElementById('cart-total-amount');
        const proceedCheckoutBtn = document.getElementById('proceed-checkout-btn');
        const whatsappOrderHint = document.getElementById('whatsapp-order-hint');
        const cartModalEl = document.getElementById('cartModal');
        const checkoutModalEl = document.getElementById('checkoutModal');
        const checkoutNameInput = document.getElementById('checkout-name');
        const checkoutAddressInput = document.getElementById('checkout-address');
        const checkoutErrorEl = document.getElementById('checkout-error');
        const confirmCheckoutBtn = document.getElementById('confirm-checkout-btn');
        const backToCartBtn = document.getElementById('back-to-cart-btn');
        const detailModalEl = document.getElementById('productDetailModal');
        const detailNameEl = document.getElementById('detail-name');
        const detailImageWrapEl = document.getElementById('detail-image-wrap');
        const detailPriceEl = document.getElementById('detail-price');
        const detailStockBadgeEl = document.getElementById('detail-stock-badge');
        const detailMetaEl = document.getElementById('detail-meta');
        const detailDescriptionEl = document.getElementById('detail-description');
        const detailAddBtn = document.getElementById('detail-add-btn');
        const detailAskBtn = document.getElementById('detail-ask-btn');

        if (!gridEl) return;

        const cart = new Map();
        const customerStorageKey = 'catalog_customer_' + <?= json_encode($token) ?>;
        let canOrder = false;
        let currentDetailProductId = null;

        function loadCart() {
            try {
                const raw = localStorage.getItem(cartStorageKey);
                if (!raw) return;
                const saved = JSON.parse(raw);
                Object.entries(saved).forEach(([id, quantity]) => {
                    cart.set(Number(id), { quantity });
                });
            } catch (e) {
                // localStorage no disponible (modo privado, etc.) — seguimos sin carrito persistente.
            }
        }

        function saveCart() {
            try {
                const plain = {};
                cart.forEach((entry, id) => { plain[id] = entry.quantity; });
                localStorage.setItem(cartStorageKey, JSON.stringify(plain));
            } catch (e) {
                // Ignorar si no se puede persistir.
            }
        }

        function loadCustomer() {
            try {
                const raw = localStorage.getItem(customerStorageKey);
                return raw ? JSON.parse(raw) : { name: '', address: '' };
            } catch (e) {
                return { name: '', address: '' };
            }
        }

        function saveCustomer(name, address) {
            try {
                localStorage.setItem(customerStorageKey, JSON.stringify({ name, address }));
            } catch (e) {
                // Ignorar si no se puede persistir.
            }
        }

        function formatMoney(value) {
            return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function findProduct(id) {
            return products.find((p) => p.id === id);
        }

        function cartQuantity(id) {
            return cart.get(id)?.quantity || 0;
        }

        function reconcileCartWithStock() {
            cart.forEach((entry, id) => {
                const product = findProduct(id);
                if (!product || product.stock <= 0) {
                    cart.delete(id);
                    return;
                }
                if (entry.quantity > product.stock) {
                    entry.quantity = product.stock;
                }
            });
            saveCart();
        }

        function addToCart(id) {
            const product = findProduct(id);
            if (!product) return;
            const current = cartQuantity(id);
            if (current >= product.stock) return;
            cart.set(id, { quantity: current + 1 });
            saveCart();
            renderGrid();
            renderCart();
        }

        function updateQuantity(id, quantity) {
            const product = findProduct(id);
            if (!product) return;
            quantity = Math.max(0, Math.min(quantity, product.stock));
            if (quantity === 0) {
                cart.delete(id);
            } else {
                cart.set(id, { quantity });
            }
            saveCart();
            renderGrid();
            renderCart();
        }

        function renderFilters() {
            const categories = Array.from(new Set(products.map((p) => p.category).filter(Boolean))).sort();
            const options = [{ value: 'all', label: 'Todos' }, ...categories.map((c) => ({ value: c, label: c }))];

            filtersEl.innerHTML = '';
            options.forEach(({ value, label }) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm rounded-pill ' + (activeCategory === value ? 'btn-primary' : 'btn-outline-secondary');
                btn.textContent = label;
                btn.addEventListener('click', () => {
                    activeCategory = value;
                    renderFilters();
                    renderGrid();
                });
                filtersEl.appendChild(btn);
            });
        }

        function renderBrandOptions() {
            const brands = Array.from(new Set(products.map((p) => p.brand).filter(Boolean))).sort();

            if (activeBrand !== 'all' && !brands.includes(activeBrand)) {
                activeBrand = 'all';
            }

            brandFilterEl.hidden = brands.length === 0;
            brandFilterEl.innerHTML = '';
            const allOption = document.createElement('option');
            allOption.value = 'all';
            allOption.textContent = 'Todas las marcas';
            brandFilterEl.appendChild(allOption);

            brands.forEach((brand) => {
                const option = document.createElement('option');
                option.value = brand;
                option.textContent = brand;
                brandFilterEl.appendChild(option);
            });

            brandFilterEl.value = activeBrand;
        }

        function renderGrid() {
            const term = searchInput.value.trim().toLowerCase();
            const filtered = products.filter((p) => {
                const matchesTerm = !term || p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
                const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
                const matchesBrand = activeBrand === 'all' || p.brand === activeBrand;
                return matchesTerm && matchesCategory && matchesBrand;
            });

            gridEl.innerHTML = '';
            noResultsEl.hidden = filtered.length > 0;

            filtered.forEach((product) => {
                const inStock = product.stock > 0;
                const inCartQty = cartQuantity(product.id);
                const maxedOut = inCartQty >= product.stock;
                const card = document.createElement('div');
                card.className = 'catalog-card';
                const askUrl = whatsappDigits
                    ? `https://wa.me/${whatsappDigits}?text=${encodeURIComponent('Hola, quiero consultar sobre: ' + product.name)}`
                    : null;
                card.innerHTML = `
                    <div class="catalog-card-image">
                        ${product.image ? `<img src="${escapeHtml(product.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<i class="bi bi-box-seam"></i>'}
                        <span class="badge ${inStock ? 'bg-success' : 'bg-danger'} catalog-stock-badge">${inStock ? product.stock + ' disp.' : 'Sin stock'}</span>
                    </div>
                    <div class="catalog-card-body">
                        <div class="catalog-card-price">${formatMoney(product.price)}</div>
                        <div class="catalog-card-name">${escapeHtml(product.name)}</div>
                        ${product.sku ? `<div class="catalog-card-sku">SKU: ${escapeHtml(product.sku)}</div>` : ''}
                        ${inCartQty > 0 ? `<div class="catalog-in-cart-note">En tu pedido: ${inCartQty}</div>` : ''}
                        <div class="catalog-card-actions">
                            <button type="button" class="btn btn-sm btn-primary flex-grow-1 add-to-cart-btn" ${!inStock || maxedOut ? 'disabled' : ''}>Agregar</button>
                            ${askUrl ? `<a href="${askUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Preguntar por WhatsApp"><i class="bi bi-whatsapp"></i></a>` : ''}
                        </div>
                    </div>
                `;
                card.querySelector('.add-to-cart-btn').addEventListener('click', () => addToCart(product.id));
                card.addEventListener('click', (e) => {
                    if (e.target.closest('.catalog-card-actions')) return;
                    openProductDetail(product.id);
                });
                gridEl.appendChild(card);
            });
        }

        function renderProductDetail(id) {
            const product = findProduct(id);
            if (!product) return;

            const inStock = product.stock > 0;
            const inCartQty = cartQuantity(id);
            const maxedOut = inCartQty >= product.stock;

            detailNameEl.textContent = product.name;
            detailImageWrapEl.innerHTML = product.image
                ? `<img src="${escapeHtml(product.image)}" alt="" class="detail-image" onerror="this.outerHTML='<div class=&quot;detail-placeholder&quot;><i class=&quot;bi bi-box-seam&quot;></i></div>'">`
                : '<div class="detail-placeholder"><i class="bi bi-box-seam"></i></div>';
            detailPriceEl.textContent = formatMoney(product.price);
            detailStockBadgeEl.className = 'badge ' + (inStock ? 'bg-success' : 'bg-danger');
            detailStockBadgeEl.textContent = inStock ? product.stock + ' disponibles' : 'Sin stock';

            const metaParts = [];
            if (product.sku) metaParts.push('SKU: ' + product.sku);
            if (product.brand) metaParts.push(product.brand);
            if (product.category) metaParts.push(product.category);
            detailMetaEl.textContent = metaParts.join(' · ');
            detailMetaEl.hidden = metaParts.length === 0;

            detailDescriptionEl.textContent = product.description || '';
            detailDescriptionEl.hidden = !product.description;

            detailAddBtn.disabled = !inStock || maxedOut;
            detailAddBtn.textContent = inCartQty > 0 ? `Agregar (${inCartQty} en tu pedido)` : 'Agregar';

            if (whatsappDigits) {
                detailAskBtn.hidden = false;
                detailAskBtn.href = `https://wa.me/${whatsappDigits}?text=${encodeURIComponent('Hola, quiero consultar sobre: ' + product.name)}`;
            } else {
                detailAskBtn.hidden = true;
            }
        }

        function openProductDetail(id) {
            currentDetailProductId = id;
            renderProductDetail(id);
            bootstrap.Modal.getOrCreateInstance(detailModalEl).show();
        }

        function renderCart() {
            let totalItems = 0;
            let total = 0;
            cartItemsListEl.innerHTML = '';

            cart.forEach((entry, id) => {
                const product = findProduct(id);
                if (!product) return;
                totalItems += entry.quantity;
                const subtotal = product.price * entry.quantity;
                total += subtotal;

                const row = document.createElement('div');
                row.className = 'cart-item-row';
                row.innerHTML = `
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="cart-item-name text-truncate">${escapeHtml(product.name)}</div>
                        <div class="cart-item-price">${formatMoney(product.price)} c/u</div>
                    </div>
                    <div class="cart-qty">
                        <button type="button" class="btn btn-outline-secondary btn-sm dec-btn">&minus;</button>
                        <span class="fw-semibold" style="min-width:1.4rem;text-align:center;">${entry.quantity}</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm inc-btn" ${entry.quantity >= product.stock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="text-end fw-semibold" style="min-width:4.5rem;">${formatMoney(subtotal)}</div>
                `;
                row.querySelector('.dec-btn').addEventListener('click', () => updateQuantity(id, entry.quantity - 1));
                row.querySelector('.inc-btn').addEventListener('click', () => updateQuantity(id, entry.quantity + 1));
                cartItemsListEl.appendChild(row);
            });

            const hasItems = totalItems > 0;
            cartEmptyMsgEl.hidden = hasItems;
            cartTotalRowEl.hidden = !hasItems;
            cartTotalAmountEl.textContent = formatMoney(total);

            if (cartBadgeEl) {
                cartBadgeEl.hidden = totalItems === 0;
                cartBadgeEl.textContent = totalItems;
            }

            canOrder = hasItems && !!whatsappDigits;
            proceedCheckoutBtn.disabled = !canOrder;
            whatsappOrderHint.hidden = !hasItems || !!whatsappDigits;
        }

        function buildOrderMessage(name, address) {
            const lines = ['Hola, quiero hacer un pedido:', `Nombre: ${name}`, `Dirección de entrega: ${address}`, ''];
            let total = 0;
            cart.forEach((entry, id) => {
                const product = findProduct(id);
                if (!product) return;
                const subtotal = product.price * entry.quantity;
                total += subtotal;
                lines.push(`- ${product.name} x${entry.quantity} = ${formatMoney(subtotal)}`);
            });
            lines.push('', `Total: ${formatMoney(total)}`);
            return lines.join('\n');
        }

        function refreshFromServer() {
            const url = new URL(window.location.href);
            url.searchParams.set('format', 'json');
            fetch(url.toString())
                .then((res) => res.json())
                .then((data) => {
                    products = data.products || [];
                    reconcileCartWithStock();
                    renderFilters();
                    renderBrandOptions();
                    renderGrid();
                    renderCart();
                    if (currentDetailProductId !== null && detailModalEl.classList.contains('show')) {
                        renderProductDetail(currentDetailProductId);
                    }
                    lastUpdatedEl.textContent = 'actualizado ' + new Date().toLocaleTimeString('es-CO');
                })
                .catch(() => {
                    lastUpdatedEl.textContent = 'sin conexión, mostrando últimos datos';
                });
        }

        searchInput.addEventListener('input', renderGrid);

        brandFilterEl.addEventListener('change', () => {
            activeBrand = brandFilterEl.value;
            renderGrid();
        });

        detailAddBtn.addEventListener('click', () => {
            if (currentDetailProductId === null) return;
            addToCart(currentDetailProductId);
            renderProductDetail(currentDetailProductId);
        });

        detailModalEl.addEventListener('hidden.bs.modal', () => {
            currentDetailProductId = null;
        });

        proceedCheckoutBtn.addEventListener('click', () => {
            if (!canOrder) return;
            const saved = loadCustomer();
            checkoutNameInput.value = saved.name || '';
            checkoutAddressInput.value = saved.address || '';
            checkoutErrorEl.hidden = true;
            bootstrap.Modal.getOrCreateInstance(cartModalEl).hide();
            bootstrap.Modal.getOrCreateInstance(checkoutModalEl).show();
        });

        backToCartBtn.addEventListener('click', () => {
            bootstrap.Modal.getOrCreateInstance(checkoutModalEl).hide();
            bootstrap.Modal.getOrCreateInstance(cartModalEl).show();
        });

        confirmCheckoutBtn.addEventListener('click', () => {
            const name = checkoutNameInput.value.trim();
            const address = checkoutAddressInput.value.trim();

            if (!name || !address) {
                checkoutErrorEl.hidden = false;
                return;
            }

            saveCustomer(name, address);
            const message = buildOrderMessage(name, address);
            window.open(`https://wa.me/${whatsappDigits}?text=${encodeURIComponent(message)}`, '_blank', 'noopener');

            bootstrap.Modal.getOrCreateInstance(checkoutModalEl).hide();
            cart.clear();
            saveCart();
            renderGrid();
            renderCart();
        });

        loadCart();
        reconcileCartWithStock();
        renderFilters();
        renderBrandOptions();
        renderGrid();
        renderCart();
        lastUpdatedEl.textContent = 'actualizado ' + new Date().toLocaleTimeString('es-CO');
        setInterval(refreshFromServer, 20000);
    });
    </script>
</body>
</html>
