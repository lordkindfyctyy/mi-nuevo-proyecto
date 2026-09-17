<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Sale.php';
require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../src/models/Customer.php';
requireLogin();

$tenantId = currentTenantId();
$products = $tenantId ? Product::allByTenant($tenantId) : [];
$customers = $tenantId ? Customer::allByTenant($tenantId) : [];
$products = array_values(array_filter($products, fn($p) => $p['status'] === 'active'));

$productsJson = json_encode(array_map(fn($p) => [
    'id' => (int) $p['id'],
    'name' => $p['name'],
    'sku' => $p['sku'] ?? '',
    'price' => (float) $p['price'],
    'stock' => (float) $p['stock_quantity'],
    'saleUnit' => $p['sale_unit'] ?? 'unit',
    'category' => $p['category'] ?? '',
    'image' => Product::imageUrl($p) ?? '',
    'description' => $p['description'] ?? '',
], $products), JSON_UNESCAPED_UNICODE);

$lastSale = null;
if (isset($_GET['success'], $_GET['sale']) && $tenantId) {
    $lastSale = Sale::findForTenant((int) $_GET['sale'], $tenantId);
}

$tenant = $tenantId ? Tenant::find($tenantId) : null;
$catalogUrl = $tenantId ? BASE_URL . '/catalogo.php?t=' . Tenant::getOrCreatePublicToken($tenantId) : null;

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    ['label' => 'Compartir catálogo', 'icon' => 'bi-share', 'action' => 'share'],
    ['label' => 'Vender', 'href' => BASE_URL . '/vender.php', 'icon' => 'bi-cart3', 'match' => 'vender.php'],
    ['label' => 'Balance', 'href' => BASE_URL . '/reportes.php', 'icon' => 'bi-bar-chart-line', 'match' => 'reportes.php'],
    ['label' => 'Inventario', 'href' => BASE_URL . '/productos.php', 'icon' => 'bi-box-seam', 'match' => 'productos.php'],
    ['label' => 'Clientes', 'href' => BASE_URL . '/clientes.php', 'icon' => 'bi-people', 'match' => 'clientes.php'],
    ['label' => 'Proveedores', 'href' => BASE_URL . '/proveedores.php', 'icon' => 'bi-truck', 'match' => 'proveedores.php'],
];

function pos_render_nav(array $items, string $currentPage): void
{
    foreach ($items as $item) {
        if (($item['action'] ?? null) === 'share') {
            echo '<button type="button" class="pos-nav-link border-0 bg-transparent text-start w-100" data-bs-toggle="modal" data-bs-target="#shareCatalogModal">';
            echo '<i class="bi ' . htmlspecialchars($item['icon']) . '"></i> ' . htmlspecialchars($item['label']);
            echo '</button>';
            continue;
        }

        if ($item['href'] === null) {
            echo '<span class="pos-nav-link disabled">';
            echo '<i class="bi ' . htmlspecialchars($item['icon']) . '"></i> ' . htmlspecialchars($item['label']);
            echo '<span class="badge bg-secondary-subtle text-secondary-emphasis ms-auto">Pronto</span>';
            echo '</span>';
            continue;
        }

        $active = $item['match'] !== null && $currentPage === $item['match'];
        echo '<a class="pos-nav-link' . ($active ? ' active' : '') . '" href="' . htmlspecialchars($item['href']) . '">';
        echo '<i class="bi ' . htmlspecialchars($item['icon']) . '"></i> ' . htmlspecialchars($item['label']);
        echo '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        html, body { height: 100%; }
        body.pos-page { margin: 0; background: #f1f2f4; }

        .pos-shell { display: flex; flex-direction: column; min-height: 100vh; }
        @media (min-width: 992px) {
            .pos-shell { flex-direction: row; height: 100vh; overflow: hidden; }
        }

        .pos-topbar {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1rem; background: #fff; border-bottom: 1px solid var(--color-border);
        }
        @media (min-width: 992px) { .pos-topbar { display: none; } }

        .pos-sidebar { display: none; }
        @media (min-width: 992px) {
            .pos-sidebar {
                display: flex; flex-direction: column; width: 220px; flex-shrink: 0;
                background: #fff; border-right: 1px solid var(--color-border); overflow-y: auto;
            }
        }
        .pos-sidebar-brand {
            padding: 1.25rem 1rem; font-weight: 700; color: var(--color-primary);
            font-size: 1.1rem; text-decoration: none; border-bottom: 1px solid var(--color-border);
        }
        .pos-nav { display: flex; flex-direction: column; padding: .75rem .5rem; gap: .2rem; flex: 1; }
        .pos-nav-link {
            display: flex; align-items: center; gap: .6rem; padding: .6rem .75rem;
            border-radius: .5rem; color: var(--color-text); text-decoration: none; font-weight: 500;
        }
        .pos-nav-link i { font-size: 1.1rem; width: 1.25rem; text-align: center; }
        .pos-nav-link:hover { background: #f3f4f6; }
        .pos-nav-link.active { background: rgba(0, 178, 143, .12); color: var(--color-primary); }
        .pos-nav-link.disabled { color: #9ca3af; }
        .pos-sidebar-footer { padding: .75rem 1rem; border-top: 1px solid var(--color-border); font-size: .85rem; }

        .pos-products { flex: 1; min-width: 0; display: flex; flex-direction: column; padding: 1rem 1.25rem; }
        @media (min-width: 992px) { .pos-products { overflow-y: auto; } }

        .pos-category-filters { display: flex; gap: .5rem; flex-wrap: wrap; margin: .75rem 0 1rem; }
        .pos-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }

        .product-card {
            background: #fff; border: 1px solid var(--color-border); border-radius: .75rem; overflow: hidden;
            cursor: pointer; transition: box-shadow .15s, transform .15s; display: flex; flex-direction: column;
        }
        .product-card:hover { box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .08); transform: translateY(-2px); }
        .product-card.out-of-stock { opacity: .55; cursor: not-allowed; }
        .product-card.out-of-stock:hover { box-shadow: none; transform: none; }
        .product-card-image {
            aspect-ratio: 1 / 1; background: #f3f4f6; display: flex; align-items: center; justify-content: center;
            color: #9ca3af; font-size: 2rem; overflow: hidden; position: relative;
        }
        .product-card-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-stock-badge { position: absolute; top: .4rem; right: .4rem; font-size: .68rem; }
        .product-card-body { padding: .6rem .75rem .75rem; display: flex; flex-direction: column; gap: .15rem; }
        .product-card-price { font-weight: 700; color: var(--color-primary); font-size: 1.05rem; }
        .product-card-name {
            font-weight: 600; font-size: .85rem; line-height: 1.25;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .product-card-desc { font-size: .75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .pos-cart { display: flex; flex-direction: column; background: #fff; border-top: 1px solid var(--color-border); }
        @media (min-width: 992px) {
            .pos-cart { width: 380px; flex-shrink: 0; border-top: none; border-left: 1px solid var(--color-border); overflow: hidden; }
        }
        .pos-cart-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border);
        }
        .pos-cart-items { flex: 1; overflow-y: auto; padding: 0 1.25rem; max-height: 50vh; }
        @media (min-width: 992px) { .pos-cart-items { max-height: none; } }
        .cart-item { padding: .75rem 0; border-bottom: 1px solid #f1f2f4; }
        .cart-item-top { display: flex; align-items: center; gap: .6rem; }
        .cart-item-thumb {
            width: 36px; height: 36px; border-radius: .5rem; border: 1px solid var(--color-border);
            background: #f3f4f6; display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0; color: #9ca3af; font-size: 1rem;
        }
        .cart-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .cart-item-name { font-weight: 600; font-size: .85rem; flex: 1; min-width: 0; }
        .cart-item-bottom { display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; margin-top: .5rem; }
        .cart-item-qty-block { display: flex; flex-direction: column; gap: .35rem; }
        .cart-item-unit-price { font-size: .72rem; color: #6b7280; }
        .cart-item-subtotal { min-width: 5rem; text-align: right; }
        .cart-qty { display: flex; align-items: center; gap: .5rem; }
        .qty-btn {
            width: 1.85rem; height: 1.85rem; border-radius: 50%; border: 1px solid var(--color-border);
            background: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; line-height: 1; padding: 0; color: var(--color-text); flex-shrink: 0;
        }
        .qty-btn:hover:not(:disabled) { background: #f3f4f6; }
        .qty-btn:disabled { opacity: .4; }
        .qty-input {
            width: 3rem; text-align: center; border: 1px solid var(--color-border); border-radius: .5rem;
            padding: .2rem 0; font-weight: 600; font-size: .85rem;
        }
        .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .qty-input { -moz-appearance: textfield; }
        .price-input-inline {
            border: none; border-bottom: 1px dashed #9ca3af; background: transparent; padding: 0 0 1px; width: 4.5rem;
            color: inherit; font: inherit; cursor: text;
        }
        .price-input-inline:focus { outline: none; border-bottom: 1px dashed var(--color-primary); }
        .price-input-inline::-webkit-outer-spin-button, .price-input-inline::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .cart-item-unit-price .bi-pencil-fill { font-size: .62rem; opacity: .55; margin-left: .15rem; }
        .pos-cart-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }
        .pos-cart-total { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.25rem; margin-bottom: .75rem; }
        .pos-continue-btn { width: 100%; padding: .85rem; font-size: 1.05rem; font-weight: 600; border-radius: .75rem; }
        .pos-cart-empty { text-align: center; color: #9ca3af; padding: 2rem 1rem; }

        #voice-btn.listening {
            background: var(--color-primary); color: #fff; border-color: var(--color-primary);
            animation: voice-pulse 1.5s infinite;
        }
        @keyframes voice-pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 178, 143, .5); }
            70% { box-shadow: 0 0 0 .6rem rgba(0, 178, 143, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 178, 143, 0); }
        }
    </style>
</head>
<body class="pos-page">
<div class="pos-shell">

    <div class="pos-topbar d-lg-none">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#posSidebarOffcanvas" aria-controls="posSidebarOffcanvas">
            <i class="bi bi-list"></i>
        </button>
        <a href="<?= BASE_URL ?>/index.php" class="fw-bold text-primary text-decoration-none"><?= APP_NAME ?></a>
    </div>

    <aside class="pos-sidebar">
        <a href="<?= BASE_URL ?>/index.php" class="pos-sidebar-brand"><?= APP_NAME ?></a>
        <nav class="pos-nav"><?php pos_render_nav($navItems, $currentPage); ?></nav>
        <div class="pos-sidebar-footer text-secondary">
            <div class="fw-semibold text-truncate"><?= htmlspecialchars(currentUserName() ?? '') ?></div>
            <?php if (currentTenantName()): ?><div class="text-truncate small"><?= htmlspecialchars(currentTenantName()) ?></div><?php endif; ?>
            <a href="<?= BASE_URL ?>/logout.php" class="small">Cerrar sesión</a>
        </div>
    </aside>

    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="posSidebarOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><?= APP_NAME ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="pos-nav"><?php pos_render_nav($navItems, $currentPage); ?></nav>
            <div class="pos-sidebar-footer text-secondary mt-auto">
                <div class="fw-semibold text-truncate"><?= htmlspecialchars(currentUserName() ?? '') ?></div>
                <?php if (currentTenantName()): ?><div class="text-truncate small"><?= htmlspecialchars(currentTenantName()) ?></div><?php endif; ?>
                <a href="<?= BASE_URL ?>/logout.php" class="small">Cerrar sesión</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shareCatalogModal" tabindex="-1" aria-labelledby="shareCatalogModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareCatalogModalLabel">Compartir catálogo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($_GET['share_success'])): ?>
                        <div class="alert alert-success py-2">Número de WhatsApp actualizado.</div>
                    <?php elseif (($_GET['share_error'] ?? '') === 'phone'): ?>
                        <div class="alert alert-danger py-2">Ingresa un número de teléfono válido (mínimo 8 dígitos).</div>
                    <?php endif; ?>

                    <p class="text-secondary small">Comparte este enlace con tus clientes: podrán ver tus productos y el stock disponible en tiempo real.</p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control form-control-sm" id="catalog-url-input" value="<?= htmlspecialchars($catalogUrl ?? '') ?>" readonly>
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="copy-catalog-url-btn">Copiar</button>
                    </div>
                    <a href="<?= htmlspecialchars($catalogUrl ?? '') ?>" target="_blank" rel="noopener" class="small">Ver catálogo público</a>

                    <hr>

                    <h2 class="h6 fw-semibold">Número de WhatsApp para consultas</h2>
                    <p class="text-secondary small mb-2">Tus clientes verán un botón de WhatsApp en el catálogo para escribirte directamente. Ingresa el número completo con código de país, solo números (ej. <code>573001234567</code>).</p>
                    <form method="POST" action="<?= BASE_URL ?>/process/tenant_process.php" class="d-flex gap-2">
                        <input type="hidden" name="action" value="update_whatsapp">
                        <input type="tel" name="whatsapp_phone" class="form-control form-control-sm" placeholder="573001234567" value="<?= htmlspecialchars($tenant['whatsapp_phone'] ?? '') ?>">
                        <button type="submit" class="btn btn-primary btn-sm text-nowrap">Guardar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="voiceConfirmModal" tabindex="-1" aria-labelledby="voiceConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="voiceConfirmModalLabel">Confirmar venta por voz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Confirmas registrar esta venta?</p>
                    <div id="voice-confirm-summary" class="small"></div>
                    <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
                        <span>Total</span>
                        <span id="voice-confirm-total">$0.00</span>
                    </div>
                    <p class="text-secondary small mt-3 mb-0">Podés decir <strong>&laquo;confirmar&raquo;</strong> para registrarla o <strong>&laquo;cancelar&raquo;</strong> para volver.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="voice-cancel-btn">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="voice-confirm-btn">Sí, registrar venta</button>
                </div>
            </div>
        </div>
    </div>

    <main class="pos-products">
        <?php if ($lastSale): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Venta #<?= (int) $lastSale['id'] ?> registrada.</strong> Total: $<?= number_format((float) $lastSale['total'], 2) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php
                $errors = [
                    'stock' => 'No hay suficiente stock para uno o más productos seleccionados.',
                    'items' => 'Selecciona al menos un producto con cantidad mayor a cero.',
                ];
                echo $errors[$_GET['error']] ?? 'No se pudo registrar la venta. Intenta de nuevo.';
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$tenantId): ?>
            <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> primero.</div>
        <?php elseif (!$products): ?>
            <div class="alert alert-warning">No hay productos registrados. Agrega productos desde <a href="<?= BASE_URL ?>/productos.php">Inventario</a>.</div>
        <?php else: ?>
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
                <input type="search" id="product-search" class="form-control" placeholder="Buscar por nombre o SKU / código de barras..." autocomplete="off" autofocus>
                <button type="button" id="voice-btn" class="btn btn-outline-secondary" title="Comando de voz">
                    <i class="bi bi-mic" id="voice-icon"></i>
                </button>
            </div>
            <div id="voice-feedback" class="small text-secondary mt-1" hidden></div>
            <div class="pos-category-filters" id="category-filters"></div>
            <div class="pos-product-grid" id="product-grid"></div>
            <p id="no-results" class="text-center text-secondary py-5" hidden>No se encontraron productos.</p>
        <?php endif; ?>
    </main>

    <?php if ($tenantId && $products): ?>
    <aside class="pos-cart">
        <div class="pos-cart-header">
            <h2 class="h6 fw-bold mb-0">Productos</h2>
            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" id="clear-cart-btn">Vaciar canasta</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/process/sale_process.php" id="sale-form" class="d-flex flex-column flex-grow-1 overflow-hidden">
            <div id="cart-inputs"></div>
            <div class="pos-cart-items">
                <div class="pos-cart-empty" id="cart-empty">
                    <i class="bi bi-cart3 fs-1 d-block mb-2"></i>
                    Escanea o agrega productos para comenzar la venta.
                </div>
                <div id="cart-list"></div>
            </div>
            <div class="pos-cart-footer">
                <div class="mb-2">
                    <select id="customer_id" name="customer_id" class="form-select form-select-sm">
                        <option value="">Cliente ocasional (sin registrar)</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= (int) $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?= BASE_URL ?>/clientes.php" target="_blank" class="form-text text-decoration-none">¿No está en la lista? Agrégalo en Clientes.</a>
                </div>
                <div class="mb-3">
                    <select id="payment_method" name="payment_method" class="form-select form-select-sm">
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="transfer">Transferencia</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
                <div class="pos-cart-total">
                    <span>Total</span>
                    <span id="grand-total">$0.00</span>
                </div>
                <button type="submit" class="btn btn-primary pos-continue-btn" id="submit-btn" disabled>
                    Continuar · <span id="submit-total">$0.00</span>
                </button>
            </div>
        </form>
    </aside>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const shareModalEl = document.getElementById('shareCatalogModal');
    if (shareModalEl) {
        const shareModal = new bootstrap.Modal(shareModalEl);

        const copyBtn = document.getElementById('copy-catalog-url-btn');
        const urlInput = document.getElementById('catalog-url-input');
        if (copyBtn && urlInput) {
            copyBtn.addEventListener('click', () => {
                urlInput.select();
                navigator.clipboard?.writeText(urlInput.value).then(() => {
                    copyBtn.textContent = '¡Copiado!';
                    setTimeout(() => { copyBtn.textContent = 'Copiar'; }, 1500);
                }).catch(() => {
                    document.execCommand('copy');
                });
            });
        }

        const params = new URLSearchParams(window.location.search);
        if (params.has('share_success') || params.has('share_error')) {
            shareModal.show();
        }
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const products = <?= $productsJson ?: '[]' ?>;
    const cart = new Map();
    let activeCategory = 'all';

    const searchInput = document.getElementById('product-search');
    if (!searchInput) return;

    const categoryFiltersEl = document.getElementById('category-filters');
    const productGridEl = document.getElementById('product-grid');
    const noResultsEl = document.getElementById('no-results');
    const cartListEl = document.getElementById('cart-list');
    const cartEmptyEl = document.getElementById('cart-empty');
    const cartInputsEl = document.getElementById('cart-inputs');
    const grandTotalEl = document.getElementById('grand-total');
    const submitTotalEl = document.getElementById('submit-total');
    const submitBtn = document.getElementById('submit-btn');
    const clearCartBtn = document.getElementById('clear-cart-btn');

    function formatMoney(value) {
        return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatQty(value) {
        return Number(value).toLocaleString('es-CO', { maximumFractionDigits: 3 });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function availableStock(product) {
        const inCart = cart.get(product.id);
        return product.stock - (inCart ? inCart.quantity : 0);
    }

    function findProduct(id) {
        return products.find((p) => p.id === id);
    }

    function renderCategoryFilters() {
        const categories = Array.from(new Set(products.map((p) => p.category).filter(Boolean))).sort();
        const options = [{ value: 'all', label: 'Todos' }, ...categories.map((c) => ({ value: c, label: c }))];

        categoryFiltersEl.innerHTML = '';
        options.forEach(({ value, label }) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm rounded-pill ' + (activeCategory === value ? 'btn-primary' : 'btn-outline-secondary');
            btn.textContent = label;
            btn.addEventListener('click', () => {
                activeCategory = value;
                renderCategoryFilters();
                renderProducts();
            });
            categoryFiltersEl.appendChild(btn);
        });
    }

    function renderProducts() {
        const term = searchInput.value.trim().toLowerCase();
        const filtered = products.filter((p) => {
            const matchesTerm = !term || p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
            const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
            return matchesTerm && matchesCategory;
        });

        productGridEl.innerHTML = '';
        noResultsEl.hidden = filtered.length > 0;

        filtered.forEach((product) => {
            const remaining = availableStock(product);
            const outOfStock = remaining <= 0;
            const card = document.createElement('div');
            card.className = 'product-card' + (outOfStock ? ' out-of-stock' : '');
            card.innerHTML = `
                <div class="product-card-image">
                    ${product.image ? `<img src="${escapeHtml(product.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<i class="bi bi-box-seam"></i>'}
                    <span class="badge ${outOfStock ? 'bg-danger' : 'bg-success'} product-stock-badge">${outOfStock ? 'Sin stock' : formatQty(remaining) + ' disp.'}</span>
                </div>
                <div class="product-card-body">
                    <div class="product-card-price">${formatMoney(product.price)}</div>
                    <div class="product-card-name" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</div>
                    <div class="product-card-desc">${escapeHtml(product.sku || product.description || '')}${product.saleUnit === 'weight' ? ' · por peso' : ''}</div>
                </div>
            `;
            if (!outOfStock) {
                card.addEventListener('click', () => addToCart(product.id));
            }
            productGridEl.appendChild(card);
        });
    }

    function addToCart(id, qty = 1) {
        const product = findProduct(id);
        if (!product) return 0;
        const toAdd = Math.min(qty, availableStock(product));
        if (toAdd <= 0) return 0;
        const existing = cart.get(id);
        if (existing) {
            existing.quantity += toAdd;
        } else {
            cart.set(id, { product, quantity: toAdd, unitPrice: product.price });
        }
        renderAll();
        focusSearch();
        return toAdd;
    }

    function updateQuantity(id, quantity) {
        const entry = cart.get(id);
        if (!entry) return;
        if (isNaN(quantity)) quantity = 0;
        quantity = entry.product.saleUnit === 'weight'
            ? Math.round(quantity * 1000) / 1000
            : Math.round(quantity);
        quantity = Math.max(0, Math.min(quantity, entry.product.stock));
        // Llegar a 0 no saca la fila del carrito: el producto sigue ahí,
        // en $0, hasta que se aumente de nuevo o se borre con el tacho.
        entry.quantity = quantity;
        renderAll();
    }

    function updateUnitPrice(id, price) {
        const entry = cart.get(id);
        if (!entry) return;
        if (isNaN(price) || price < 0) {
            price = entry.unitPrice;
        }
        entry.unitPrice = price;
        renderAll();
    }

    function removeFromCart(id) {
        cart.delete(id);
        renderAll();
    }

    // Recalcula el subtotal de una fila y el total general leyendo los
    // valores que el usuario está tipeando en vivo, sin reconstruir el
    // DOM (así no se pierde el foco/cursor mientras escribe). El valor
    // definitivo (redondeo, límites de stock, etc.) se aplica recién en
    // el evento "change" vía updateQuantity()/updateUnitPrice().
    function recalcRowLive(row) {
        const qty = parseFloat(row.querySelector('.qty-input').value);
        const price = parseFloat(row.querySelector('.price-input-inline').value);
        const subtotal = (isNaN(qty) ? 0 : qty) * (isNaN(price) ? 0 : price);
        row.querySelector('.cart-item-subtotal').textContent = formatMoney(subtotal);
        recalcGrandTotalLive();
    }

    function recalcGrandTotalLive() {
        let total = 0;
        cartListEl.querySelectorAll('.cart-item').forEach((row) => {
            const qty = parseFloat(row.querySelector('.qty-input').value);
            const price = parseFloat(row.querySelector('.price-input-inline').value);
            total += (isNaN(qty) ? 0 : qty) * (isNaN(price) ? 0 : price);
        });
        grandTotalEl.textContent = formatMoney(total);
        submitTotalEl.textContent = formatMoney(total);
    }

    function renderCart() {
        cartListEl.innerHTML = '';
        const hasItems = cart.size > 0;
        const hasSellableItems = Array.from(cart.values()).some((entry) => entry.quantity > 0);
        cartEmptyEl.hidden = hasItems;
        submitBtn.disabled = !hasSellableItems;

        let total = 0;
        cart.forEach(({ product, quantity, unitPrice }) => {
            const subtotal = unitPrice * quantity;
            total += subtotal;
            const step = product.saleUnit === 'weight' ? 0.1 : 1;
            const unitLabel = product.saleUnit === 'weight' ? 'kg' : 'unidad';
            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="cart-item-top">
                    <div class="cart-item-thumb">
                        ${product.image ? `<img src="${escapeHtml(product.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<i class="bi bi-box-seam"></i>'}
                    </div>
                    <div class="cart-item-name text-truncate">${escapeHtml(product.name)}</div>
                    <button type="button" class="btn btn-sm btn-link text-danger remove-btn p-0" aria-label="Quitar"><i class="bi bi-trash"></i></button>
                </div>
                <div class="cart-item-bottom">
                    <div class="cart-item-qty-block">
                        <div class="cart-qty">
                            <button type="button" class="qty-btn dec-btn" aria-label="Disminuir" ${quantity <= 0 ? 'disabled' : ''}>&minus;</button>
                            <input type="number" step="${step}" min="0" max="${product.stock}" class="qty-input" value="${quantity}">
                            <button type="button" class="qty-btn inc-btn" aria-label="Aumentar" ${quantity >= product.stock ? 'disabled' : ''}>+</button>
                        </div>
                        <div class="cart-item-unit-price">
                            Precio por 1 ${unitLabel}: $<input type="number" step="0.01" min="0" class="price-input-inline" value="${unitPrice}" title="Editar precio de esta venta"><i class="bi bi-pencil-fill"></i>
                        </div>
                    </div>
                    <div class="cart-item-subtotal fw-semibold">${formatMoney(subtotal)}</div>
                </div>
            `;
            const qtyInput = row.querySelector('.qty-input');
            const priceInput = row.querySelector('.price-input-inline');

            row.querySelector('.dec-btn').addEventListener('click', () => updateQuantity(product.id, quantity - step));
            row.querySelector('.inc-btn').addEventListener('click', () => updateQuantity(product.id, quantity + step));
            row.querySelector('.remove-btn').addEventListener('click', () => removeFromCart(product.id));

            qtyInput.addEventListener('input', () => recalcRowLive(row));
            qtyInput.addEventListener('change', (e) => updateQuantity(product.id, parseFloat(e.target.value)));
            priceInput.addEventListener('input', () => recalcRowLive(row));
            priceInput.addEventListener('change', (e) => updateUnitPrice(product.id, parseFloat(e.target.value)));

            cartListEl.appendChild(row);
        });

        grandTotalEl.textContent = formatMoney(total);
        submitTotalEl.textContent = formatMoney(total);

        cartInputsEl.innerHTML = '';
        cart.forEach(({ product, quantity, unitPrice }) => {
            const qtyInput = document.createElement('input');
            qtyInput.type = 'hidden';
            qtyInput.name = `quantity[${product.id}]`;
            qtyInput.value = quantity;
            cartInputsEl.appendChild(qtyInput);

            const priceInput = document.createElement('input');
            priceInput.type = 'hidden';
            priceInput.name = `price[${product.id}]`;
            priceInput.value = unitPrice;
            cartInputsEl.appendChild(priceInput);
        });
    }

    function renderAll() {
        renderProducts();
        renderCart();
    }

    function focusSearch() {
        searchInput.focus();
        searchInput.select();
    }

    function matchesActiveFilters(p, term) {
        const matchesTerm = p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
        const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
        return matchesTerm && matchesCategory;
    }

    searchInput.addEventListener('input', renderProducts);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const term = searchInput.value.trim().toLowerCase();
        if (!term) return;

        const exactSku = products.find((p) => (p.sku || '').toLowerCase() === term);
        if (exactSku) {
            addToCart(exactSku.id);
            searchInput.value = '';
            renderProducts();
            return;
        }

        const visible = products.filter((p) => matchesActiveFilters(p, term));
        if (visible.length === 1) {
            addToCart(visible[0].id);
            searchInput.value = '';
            renderProducts();
        }
    });

    clearCartBtn.addEventListener('click', () => {
        if (cart.size === 0) return;
        if (!confirm('¿Vaciar la canasta actual?')) return;
        cart.clear();
        renderAll();
    });

    renderCategoryFilters();
    renderAll();
    focusSearch();

    // --- Comandos de voz ---
    const voiceBtn = document.getElementById('voice-btn');
    const voiceIcon = document.getElementById('voice-icon');
    const voiceFeedbackEl = document.getElementById('voice-feedback');
    const voiceConfirmModalEl = document.getElementById('voiceConfirmModal');
    const voiceConfirmSummaryEl = document.getElementById('voice-confirm-summary');
    const voiceConfirmTotalEl = document.getElementById('voice-confirm-total');
    const voiceConfirmBtn = document.getElementById('voice-confirm-btn');
    const voiceCancelBtn = document.getElementById('voice-cancel-btn');
    const saleForm = document.getElementById('sale-form');

    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognitionCtor) {
        voiceBtn.disabled = true;
        voiceBtn.title = 'Tu navegador no soporta comandos de voz (usa Chrome o Edge).';
    } else {
        const recognition = new SpeechRecognitionCtor();
        recognition.lang = 'es-ES';
        recognition.continuous = true;
        recognition.interimResults = false;

        const voiceConfirmModal = bootstrap.Modal.getOrCreateInstance(voiceConfirmModalEl);
        let listening = false;
        let awaitingConfirmation = false;
        let isSpeaking = false;

        const numberWords = {
            un: 1, uno: 1, una: 1, dos: 2, tres: 3, cuatro: 4, cinco: 5, seis: 6,
            siete: 7, ocho: 8, nueve: 9, diez: 10, once: 11, doce: 12, trece: 13,
            catorce: 14, quince: 15, veinte: 20,
        };

        function normalizeVoiceText(text) {
            return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
        }

        function showVoiceFeedback(message, tone = 'muted') {
            voiceFeedbackEl.hidden = false;
            voiceFeedbackEl.textContent = message;
            voiceFeedbackEl.className = 'small mt-1 text-' + (tone === 'error' ? 'danger' : tone === 'success' ? 'success' : 'secondary');
        }

        // Pausa el reconocimiento mientras el asistente habla, para que el
        // micrófono no se escuche a sí mismo (eso generaba comandos falsos
        // y errores por tener grabación y reproducción de audio a la vez).
        function speak(text) {
            if (!window.speechSynthesis) return;

            const resumeListening = () => {
                isSpeaking = false;
                if (listening) {
                    try { recognition.start(); } catch (e) { /* ya estaba iniciado */ }
                }
            };

            try {
                isSpeaking = true;
                if (listening) {
                    try { recognition.stop(); } catch (e) { /* ya estaba detenido */ }
                }

                const utter = new SpeechSynthesisUtterance(text);
                utter.lang = 'es-ES';
                utter.onend = resumeListening;
                utter.onerror = resumeListening;
                window.speechSynthesis.speak(utter);
            } catch (e) {
                resumeListening();
            }
        }

        function extractQuantity(text) {
            const digitMatch = text.match(/^(\d+)\s+(.*)$/);
            if (digitMatch) {
                return { quantity: parseInt(digitMatch[1], 10), rest: digitMatch[2] };
            }
            const words = text.split(/\s+/);
            if (words.length > 1 && numberWords[words[0]] !== undefined) {
                return { quantity: numberWords[words[0]], rest: words.slice(1).join(' ') };
            }
            return { quantity: 1, rest: text };
        }

        function findBestProductMatch(term) {
            const cleaned = term.replace(/^(el|la|los|las|de|un|una|unos|unas)\s+/, '').trim();
            if (!cleaned) return null;

            let best = null;
            let bestScore = 0;
            products.forEach((product) => {
                const name = normalizeVoiceText(product.name);
                const sku = normalizeVoiceText(product.sku || '');
                let score = 0;
                if (sku && sku === cleaned) {
                    score = 100;
                } else if (name === cleaned) {
                    score = 90;
                } else if (name.includes(cleaned)) {
                    score = 70;
                } else {
                    const termWords = cleaned.split(/\s+/);
                    const nameWords = name.split(/\s+/);
                    const overlap = termWords.filter((w) => nameWords.includes(w)).length;
                    if (overlap > 0) score = 40 + overlap * 5;
                }
                if (score > bestScore) {
                    bestScore = score;
                    best = product;
                }
            });
            return bestScore >= 40 ? best : null;
        }

        function voiceCartSummary() {
            const lines = [];
            let total = 0;
            cart.forEach(({ product, quantity, unitPrice }) => {
                total += unitPrice * quantity;
                lines.push(`${product.name} x${quantity}`);
            });
            return { lines, total };
        }

        function stopListening() {
            listening = false;
            isSpeaking = false;
            window.speechSynthesis?.cancel();
            try { recognition.stop(); } catch (e) { /* ya estaba detenido */ }
            voiceBtn.classList.remove('listening');
            voiceIcon.className = 'bi bi-mic';
        }

        function openVoiceConfirm() {
            if (cart.size === 0) {
                showVoiceFeedback('El carrito está vacío. Agregá productos antes de confirmar.', 'error');
                speak('El carrito está vacío.');
                return;
            }
            const { lines, total } = voiceCartSummary();
            voiceConfirmSummaryEl.innerHTML = lines.map((line) => `<div>${escapeHtml(line)}</div>`).join('');
            voiceConfirmTotalEl.textContent = formatMoney(total);
            awaitingConfirmation = true;
            voiceConfirmModal.show();
            speak(`Vas a registrar una venta por ${formatMoney(total)}. Decí confirmar para registrarla, o cancelar.`);
        }

        function finalizeVoiceSale() {
            awaitingConfirmation = false;
            voiceConfirmModal.hide();
            stopListening();
            saleForm.requestSubmit();
        }

        function cancelVoiceConfirm() {
            awaitingConfirmation = false;
            voiceConfirmModal.hide();
            showVoiceFeedback('Confirmación cancelada.', 'muted');
        }

        function handleAddCommand(rest) {
            const { quantity, rest: term } = extractQuantity(normalizeVoiceText(rest));
            const product = findBestProductMatch(term);
            if (!product) {
                showVoiceFeedback(`No encontré ningún producto que coincida con "${term}".`, 'error');
                speak(`No encontré ningún producto que coincida con ${term}.`);
                return;
            }
            const added = addToCart(product.id, quantity);
            if (added <= 0) {
                showVoiceFeedback(`${product.name} no tiene stock disponible.`, 'error');
                speak(`${product.name} no tiene stock disponible.`);
            } else if (added < quantity) {
                showVoiceFeedback(`Solo había stock para agregar ${added} de ${product.name}.`, 'error');
                speak(`Solo agregué ${added} de ${product.name} por falta de stock.`);
            } else {
                showVoiceFeedback(`Agregado: ${product.name} x${added}.`, 'success');
                speak(`Agregué ${added} de ${product.name}.`);
            }
        }

        function handleSearchCommand(term) {
            searchInput.value = term;
            renderProducts();
            showVoiceFeedback(`Mostrando resultados para "${term}".`, 'muted');
        }

        function handleVoiceCommand(rawText) {
            const text = normalizeVoiceText(rawText);
            if (!text) return;

            if (awaitingConfirmation) {
                if (/^(si|sí|confirmar|confirmar venta|registrar venta)\b/.test(text)) {
                    finalizeVoiceSale();
                } else if (/^(no|cancelar)\b/.test(text)) {
                    cancelVoiceConfirm();
                } else {
                    showVoiceFeedback('Decí "confirmar" para registrar la venta o "cancelar" para volver.', 'muted');
                }
                return;
            }

            if (/^(confirmar( venta)?|registrar venta|finalizar venta)\b/.test(text)) {
                openVoiceConfirm();
                return;
            }

            const addMatch = text.match(/^(agregar|anadir|agrega|pon|anade)\s+(.+)$/);
            if (addMatch) {
                handleAddCommand(addMatch[2]);
                return;
            }

            const searchMatch = text.match(/^(buscar|busca)\s+(.+)$/);
            if (searchMatch) {
                handleSearchCommand(searchMatch[2]);
                return;
            }

            handleAddCommand(text);
        }

        recognition.addEventListener('start', () => {
            listening = true;
            voiceBtn.classList.add('listening');
            voiceIcon.className = 'bi bi-mic-fill';
            showVoiceFeedback('Escuchando... decí, por ejemplo, "agregar dos arroz".', 'muted');
        });

        recognition.addEventListener('result', (event) => {
            const result = event.results[event.results.length - 1];
            if (!result.isFinal) return;
            handleVoiceCommand(result[0].transcript);
        });

        recognition.addEventListener('error', (event) => {
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                listening = false;
                voiceBtn.classList.remove('listening');
                voiceIcon.className = 'bi bi-mic';
                showVoiceFeedback('Permiso de micrófono denegado.', 'error');
            }
            // Otros errores (silencio, red) se recuperan solos con el reinicio en "end".
        });

        recognition.addEventListener('end', () => {
            if (listening && !isSpeaking) {
                // Si isSpeaking es true, es speak() quien reinicia el
                // reconocimiento cuando termine de hablar (evita el
                // reinicio duplicado y que se escuche a sí mismo).
                try { recognition.start(); } catch (e) { /* ya estaba iniciado */ }
            } else if (!listening) {
                voiceBtn.classList.remove('listening');
                voiceIcon.className = 'bi bi-mic';
            }
        });

        voiceBtn.addEventListener('click', () => {
            if (listening) {
                stopListening();
                showVoiceFeedback('Comando de voz detenido.', 'muted');
            } else {
                try {
                    recognition.start();
                } catch (e) {
                    // el reconocimiento ya estaba en marcha
                }
            }
        });

        voiceConfirmBtn.addEventListener('click', finalizeVoiceSale);
        voiceCancelBtn.addEventListener('click', cancelVoiceConfirm);
        voiceConfirmModalEl.addEventListener('hidden.bs.modal', () => {
            awaitingConfirmation = false;
        });
    }
});
</script>
</body>
</html>
