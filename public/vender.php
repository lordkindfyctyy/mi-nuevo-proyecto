<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Sale.php';
require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../src/models/Customer.php';
require_once __DIR__ . '/../includes/receipt_helpers.php';
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

$tenant = $tenantId ? Tenant::find($tenantId) : null;

$lastSale = null;
$lastSaleWhatsappUrl = null;
if (isset($_GET['success'], $_GET['sale']) && $tenantId) {
    $lastSale = Sale::findForTenant((int) $_GET['sale'], $tenantId);
    if ($lastSale) {
        $lastSaleToken = Sale::getOrCreatePublicToken((int) $lastSale['id']);
        $lastSaleWhatsappUrl = receipt_whatsapp_share_url($tenant['name'] ?? APP_NAME, $lastSale, receipt_public_url($lastSaleToken));
    }
}
$catalogUrl = $tenantId ? BASE_URL . '/catalogo.php?t=' . Tenant::getOrCreatePublicToken($tenantId) : null;

$currentPage = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    ['label' => 'Compartir catálogo', 'icon' => 'bi-share', 'action' => 'share'],
    ['label' => 'Vender', 'href' => BASE_URL . '/vender.php', 'icon' => 'bi-cart3', 'match' => 'vender.php'],
    ['label' => 'Ventas', 'href' => BASE_URL . '/ventas.php', 'icon' => 'bi-receipt', 'match' => 'ventas.php'],
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
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
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
        .pos-tenant-name { font-size: 1rem; line-height: 1.2; margin-bottom: .1rem; }

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
        .cart-amount-row { display: flex; align-items: center; gap: .2rem; font-size: .68rem; color: #6b7280; margin-top: .15rem; }
        .amount-input {
            width: 4rem; border: none; border-bottom: 1px dashed #9ca3af; background: transparent;
            padding: 0; font: inherit; color: inherit;
        }
        .amount-input:focus { outline: none; border-bottom-color: var(--color-primary); }
        .amount-input::-webkit-outer-spin-button, .amount-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .pos-cart-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }
        .pos-cart-total { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.25rem; margin-bottom: .75rem; }
        .pos-continue-btn { width: 100%; padding: .85rem; font-size: 1.05rem; font-weight: 600; border-radius: .75rem; }
        .pos-discount-block { padding: .5rem .6rem; background: #f8f9fa; border-radius: .5rem; }
        .pos-discount-input-group { display: flex; gap: .3rem; width: 8rem; }
        .pos-discount-input-group .form-select, .pos-discount-input-group .form-control { padding: .2rem .4rem; font-size: .8rem; }
        .pos-summary-line { display: flex; justify-content: space-between; align-items: center; margin-bottom: .35rem; }
        .pos-cart-empty { text-align: center; color: #9ca3af; padding: 2rem 1rem; }
    </style>
</head>
<body class="pos-page">
<div class="pos-shell">

    <div class="pos-topbar d-lg-none">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#posSidebarOffcanvas" aria-controls="posSidebarOffcanvas">
            <i class="bi bi-list"></i>
        </button>
        <a href="<?= BASE_URL ?>/index.php" class="brand-logo brand-logo-sm text-decoration-none">
            <span class="brand-mark" aria-hidden="true">6&amp;7</span>
            <span class="brand-word"><?= APP_NAME ?></span>
        </a>
    </div>

    <aside class="pos-sidebar">
        <a href="<?= BASE_URL ?>/index.php" class="pos-sidebar-brand brand-logo">
            <span class="brand-mark" aria-hidden="true">6&amp;7</span>
            <span class="brand-word"><?= APP_NAME ?></span>
        </a>
        <nav class="pos-nav"><?php pos_render_nav($navItems, $currentPage); ?></nav>
        <div class="pos-sidebar-footer text-secondary text-center">
            <?php if (currentTenantName()): ?><div class="fw-bold text-dark text-truncate pos-tenant-name"><?= htmlspecialchars(currentTenantName()) ?></div><?php endif; ?>
            <div class="text-truncate small"><?= htmlspecialchars(currentUserName() ?? '') ?></div>
            <a href="<?= BASE_URL ?>/logout.php" class="small">Cerrar sesión</a>
        </div>
    </aside>

    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="posSidebarOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title brand-logo brand-logo-sm mb-0">
                <span class="brand-mark" aria-hidden="true">6&amp;7</span>
                <span class="brand-word"><?= APP_NAME ?></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <nav class="pos-nav"><?php pos_render_nav($navItems, $currentPage); ?></nav>
            <div class="pos-sidebar-footer text-secondary mt-auto text-center">
                <?php if (currentTenantName()): ?><div class="fw-bold text-dark text-truncate pos-tenant-name"><?= htmlspecialchars(currentTenantName()) ?></div><?php endif; ?>
                <div class="text-truncate small"><?= htmlspecialchars(currentUserName() ?? '') ?></div>
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

    <div class="modal fade" id="confirmSaleModal" tabindex="-1" aria-labelledby="confirmSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmSaleModalLabel">Confirmar venta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center fw-bold fs-5 mb-3">
                        <span>Total a cobrar</span>
                        <span id="confirm-sale-total">$0.00</span>
                    </div>
                    <label class="form-label small text-secondary mb-2">Medio de pago</label>
                    <div class="btn-group w-100 mb-3" role="group" aria-label="Medio de pago" id="payment-method-group">
                        <button type="button" class="btn btn-outline-primary payment-method-btn active" data-value="cash">Efectivo</button>
                        <button type="button" class="btn btn-outline-primary payment-method-btn" data-value="card">Tarjeta</button>
                        <button type="button" class="btn btn-outline-primary payment-method-btn" data-value="transfer">Transferencia</button>
                        <button type="button" class="btn btn-outline-primary payment-method-btn" data-value="qr">QR</button>
                    </div>
                    <div id="cash-received-block">
                        <label for="cash-received-input" class="form-label small text-secondary">¿Con cuánto paga el cliente? (opcional)</label>
                        <input type="number" step="any" min="0" class="form-control" id="cash-received-input" placeholder="Ej: 10000">
                        <div class="mt-2 small" id="change-due-line" hidden>
                            <span id="change-due-label"></span> <span id="change-due-amount" class="fw-semibold"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirm-sale-btn">Confirmar venta</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="variantPickerModal" tabindex="-1" aria-labelledby="variantPickerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="variantPickerModalLabel">Elegí una presentación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="variant-picker-list" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/settings_modal.php'; ?>
    <?php require_once __DIR__ . '/../includes/catalog_chat_admin_widget.php'; ?>

    <main class="pos-products">
        <div class="d-flex justify-content-end mb-2">
            <button type="button" class="btn btn-outline-secondary rounded-circle settings-gear-btn" data-bs-toggle="modal" data-bs-target="#settingsModal" title="Ajustes" aria-label="Ajustes">
                <i class="bi bi-gear"></i>
            </button>
        </div>
        <?php if ($lastSale): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex flex-wrap align-items-center gap-2" role="alert">
                <div class="flex-grow-1">
                    <strong>Venta #<?= (int) $lastSale['id'] ?> registrada.</strong> Total: $<?= number_format((float) $lastSale['total'], 2) ?>
                </div>
                <a href="<?= htmlspecialchars($lastSaleWhatsappUrl) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm text-nowrap">
                    <i class="bi bi-whatsapp"></i> Compartir por WhatsApp
                </a>
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
            </div>
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
        <form method="POST" action="<?= BASE_URL ?>/process/sale_process.php" id="sale-form" novalidate class="d-flex flex-column flex-grow-1 overflow-hidden">
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
                <input type="hidden" id="payment_method" name="payment_method" value="cash">
                <div class="pos-discount-block mb-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="discount-toggle">
                            <label class="form-check-label small" for="discount-toggle">Aplicar 10% desc.</label>
                        </div>
                        <div class="pos-discount-input-group" id="discount-input-group" hidden>
                            <select class="form-select form-select-sm" id="discount-type">
                                <option value="percent">%</option>
                                <option value="fixed">$</option>
                            </select>
                            <input type="number" step="any" min="0" class="form-control form-control-sm" id="discount-value" value="10">
                        </div>
                    </div>
                </div>
                <div class="pos-summary-line text-secondary small" id="discount-subtotal-line" hidden>
                    <span>Subtotal</span>
                    <span id="summary-subtotal">$0.00</span>
                </div>
                <div class="pos-summary-line text-danger small" id="discount-line" hidden>
                    <span>Descuento</span>
                    <span id="summary-discount">- $0.00</span>
                </div>
                <div class="pos-cart-total">
                    <span>Total</span>
                    <span id="grand-total">$0.00</span>
                </div>
                <button type="button" class="btn btn-primary pos-continue-btn" id="submit-btn" disabled data-bs-toggle="modal" data-bs-target="#confirmSaleModal">
                    Continuar · <span id="submit-total">$0.00</span>
                </button>
                <input type="hidden" name="discount_enabled" id="discount-enabled-input" value="0">
                <input type="hidden" name="discount_type" id="discount-type-input" value="percent">
                <input type="hidden" name="discount_value" id="discount-value-input" value="0">
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
    const discount = { enabled: false, type: 'percent', value: 10 };
    let currentGrandTotal = 0;

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
    const discountToggle = document.getElementById('discount-toggle');
    const discountInputGroup = document.getElementById('discount-input-group');
    const discountTypeSelect = document.getElementById('discount-type');
    const discountValueInput = document.getElementById('discount-value');
    const discountSubtotalLine = document.getElementById('discount-subtotal-line');
    const discountLine = document.getElementById('discount-line');
    const summarySubtotalEl = document.getElementById('summary-subtotal');
    const summaryDiscountEl = document.getElementById('summary-discount');
    const discountEnabledInput = document.getElementById('discount-enabled-input');
    const discountTypeInput = document.getElementById('discount-type-input');
    const discountValueInputHidden = document.getElementById('discount-value-input');

    const variantPickerModalEl = document.getElementById('variantPickerModal');
    const variantPickerModal = variantPickerModalEl ? new bootstrap.Modal(variantPickerModalEl) : null;
    const variantPickerLabel = document.getElementById('variantPickerModalLabel');
    const variantPickerList = document.getElementById('variant-picker-list');

    // Products that are really just different sizes/presentations of the
    // same item (e.g. "Royal Canin Mini Adulto 3k" / "... suelto") share a
    // base name once the trailing weight/"suelto" is stripped off. Grouping
    // them lets the sale grid show one card instead of one per size.
    const PRESENTATION_RE = /^(.*?)[\s-]+((?:x\s*)?\d+(?:[.,]\d+)?\s*(?:x\s*\d+(?:[.,]\d+)?\s*)?(?:kgs?|kilos?|k|grs?|gramos?|g|mls?|ml|lts?|litros?|l)\.?|suelto|a\s*granel)$/i;

    function splitPresentation(name) {
        const trimmed = (name || '').trim();
        const match = trimmed.match(PRESENTATION_RE);
        if (match && match[1].trim().length >= 3) {
            return { base: match[1].trim(), variant: match[2].trim() };
        }
        return { base: trimmed, variant: '' };
    }

    // Converts a presentation label ("3k", "1,5 kilos", "X15 Kg", "12x85gr",
    // "suelto") into a comparable weight in grams, so the variant picker can
    // sort presentations from lightest to heaviest. "suelto" (sold loose, no
    // fixed package size) and anything that couldn't be parsed sort first,
    // ahead of every known weight.
    const WEIGHT_VALUE_RE = /^x?\s*(\d+(?:[.,]\d+)?)\s*(?:x\s*(\d+(?:[.,]\d+)?)\s*)?(kgs?|kilos?|k|grs?|gramos?|g|mls?|ml|lts?|litros?|l)\.?$/i;

    function presentationWeightGrams(variantLabel) {
        const label = (variantLabel || '').trim().toLowerCase();
        if (label === '' || /^suelto$|^a\s*granel$/.test(label)) return 0;

        const match = label.match(WEIGHT_VALUE_RE);
        if (!match) return 0;

        const primary = parseFloat(match[1].replace(',', '.'));
        const packCount = match[2] ? parseFloat(match[2].replace(',', '.')) : 1;
        const gramsPerUnit = /^(kgs?|kilos?|k)\.?$/.test(match[3]) || /^(lts?|litros?|l)\.?$/.test(match[3]) ? 1000 : 1;

        return primary * packCount * gramsPerUnit;
    }

    const productIdToGroup = new Map();
    (function buildVariantGroups() {
        const groupsByKey = new Map();
        products.forEach((p) => {
            const { base } = splitPresentation(p.name);
            const key = base.toLowerCase().replace(/\s+/g, ' ');
            if (!groupsByKey.has(key)) groupsByKey.set(key, { label: base, members: [] });
            groupsByKey.get(key).members.push(p);
        });
        groupsByKey.forEach((group) => {
            if (group.members.length > 1) {
                group.members.forEach((p) => productIdToGroup.set(p.id, group));
            }
        });
    })();

    function formatMoney(value) {
        return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatQty(value) {
        return Number(value).toLocaleString('es-CO', { maximumFractionDigits: 3 });
    }

    // Calcula el descuento (nunca negativo ni mayor al subtotal) y actualiza
    // la línea de resumen + los inputs ocultos que se envían al backend, que
    // siempre revalida este monto contra el subtotal real de los productos.
    function applyDiscountSummary(subtotal) {
        let discountAmount = 0;
        if (discount.enabled && subtotal > 0) {
            discountAmount = discount.type === 'percent'
                ? subtotal * Math.min(Math.max(discount.value, 0), 100) / 100
                : Math.max(discount.value, 0);
            discountAmount = Math.max(0, Math.min(discountAmount, subtotal));
        }
        const total = subtotal - discountAmount;

        discountSubtotalLine.hidden = !discount.enabled;
        discountLine.hidden = !discount.enabled;
        if (discount.enabled) {
            summarySubtotalEl.textContent = formatMoney(subtotal);
            summaryDiscountEl.textContent = '- ' + formatMoney(discountAmount);
        }

        discountEnabledInput.value = discount.enabled ? '1' : '0';
        discountTypeInput.value = discount.type;
        discountValueInputHidden.value = discount.value;

        grandTotalEl.textContent = formatMoney(total);
        submitTotalEl.textContent = formatMoney(total);
        currentGrandTotal = total;

        return total;
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

        const renderedGroups = new Set();

        filtered.forEach((product) => {
            const group = productIdToGroup.get(product.id);
            if (group) {
                if (renderedGroups.has(group)) return;
                renderedGroups.add(group);
                renderGroupCard(group);
                return;
            }
            renderSingleCard(product);
        });
    }

    function renderSingleCard(product) {
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
    }

    function renderGroupCard(group) {
        const members = group.members;
        const withImage = members.find((p) => p.image);
        const prices = members.map((p) => p.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);
        const priceLabel = minPrice === maxPrice ? formatMoney(minPrice) : `Desde ${formatMoney(minPrice)}`;
        const anyInStock = members.some((p) => availableStock(p) > 0);

        const card = document.createElement('div');
        card.className = 'product-card' + (anyInStock ? '' : ' out-of-stock');
        card.innerHTML = `
            <div class="product-card-image">
                ${withImage ? `<img src="${escapeHtml(withImage.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<i class="bi bi-box-seam"></i>'}
                <span class="badge bg-primary product-stock-badge">${members.length} presentaciones</span>
            </div>
            <div class="product-card-body">
                <div class="product-card-price">${priceLabel}</div>
                <div class="product-card-name" title="${escapeHtml(group.label)}">${escapeHtml(group.label)}</div>
                <div class="product-card-desc">Elegí una presentación</div>
            </div>
        `;
        card.addEventListener('click', () => openVariantPicker(group));
        productGridEl.appendChild(card);
    }

    function openVariantPicker(group) {
        if (!variantPickerModal) return;

        variantPickerLabel.textContent = group.label;
        variantPickerList.innerHTML = '';

        const sortedMembers = [...group.members].sort((a, b) => {
            const weightA = presentationWeightGrams(splitPresentation(a.name).variant);
            const weightB = presentationWeightGrams(splitPresentation(b.name).variant);
            return weightA - weightB;
        });

        sortedMembers.forEach((product) => {
            const remaining = availableStock(product);
            const outOfStock = remaining <= 0;
            const { variant } = splitPresentation(product.name);
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'btn btn-outline-secondary d-flex justify-content-between align-items-center text-start py-2'
                + (outOfStock ? ' disabled' : '');
            row.innerHTML = `
                <span class="fw-semibold">${escapeHtml(variant || product.name)}</span>
                <span class="text-end d-flex align-items-center gap-2">
                    <span class="fw-semibold">${formatMoney(product.price)}</span>
                    <span class="badge ${outOfStock ? 'bg-danger' : 'bg-success'}">${outOfStock ? 'Sin stock' : formatQty(remaining) + ' disp.'}</span>
                </span>
            `;
            if (!outOfStock) {
                row.addEventListener('click', () => {
                    addToCart(product.id);
                    variantPickerModal.hide();
                });
            }
            variantPickerList.appendChild(row);
        });

        variantPickerModal.show();
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
        let subtotal = 0;
        cartListEl.querySelectorAll('.cart-item').forEach((row) => {
            const qty = parseFloat(row.querySelector('.qty-input').value);
            const price = parseFloat(row.querySelector('.price-input-inline').value);
            subtotal += (isNaN(qty) ? 0 : qty) * (isNaN(price) ? 0 : price);
        });
        applyDiscountSummary(subtotal);
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
            const isFractional = product.saleUnit === 'weight';
            const buttonStep = isFractional ? 0.5 : 1;
            const inputStepAttr = isFractional ? 'any' : '1';
            const unitLabel = isFractional ? 'kg' : 'unidad';
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
                            <input type="number" step="${inputStepAttr}" min="0" max="${product.stock}" class="qty-input" value="${quantity}">
                            <button type="button" class="qty-btn inc-btn" aria-label="Aumentar" ${quantity >= product.stock ? 'disabled' : ''}>+</button>
                        </div>
                        <div class="cart-item-unit-price">
                            Precio por 1 ${unitLabel}: $<input type="number" step="0.01" min="0" class="price-input-inline" value="${unitPrice}" title="Editar precio de esta venta"><i class="bi bi-pencil-fill"></i>
                        </div>
                        ${isFractional ? `
                            <div class="cart-amount-row">
                                <span>o por monto: $</span>
                                <input type="number" step="any" min="0" class="amount-input" placeholder="5000" title="Ingresá el monto y se calculan los kilos">
                            </div>
                        ` : ''}
                    </div>
                    <div class="cart-item-subtotal fw-semibold">${formatMoney(subtotal)}</div>
                </div>
            `;
            const qtyInput = row.querySelector('.qty-input');
            const priceInput = row.querySelector('.price-input-inline');

            row.querySelector('.dec-btn').addEventListener('click', () => updateQuantity(product.id, quantity - buttonStep));
            row.querySelector('.inc-btn').addEventListener('click', () => updateQuantity(product.id, quantity + buttonStep));
            row.querySelector('.remove-btn').addEventListener('click', () => removeFromCart(product.id));

            if (isFractional) {
                const amountInput = row.querySelector('.amount-input');
                const applyAmount = () => {
                    const amount = parseFloat(amountInput.value);
                    amountInput.value = '';
                    if (isNaN(amount) || amount < 0 || unitPrice <= 0) return;
                    updateQuantity(product.id, amount / unitPrice);
                };
                amountInput.addEventListener('change', applyAmount);
                amountInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        amountInput.blur();
                    }
                });
            }

            qtyInput.addEventListener('input', () => recalcRowLive(row));
            qtyInput.addEventListener('change', (e) => updateQuantity(product.id, parseFloat(e.target.value)));
            priceInput.addEventListener('input', () => recalcRowLive(row));
            priceInput.addEventListener('change', (e) => updateUnitPrice(product.id, parseFloat(e.target.value)));

            cartListEl.appendChild(row);
        });

        applyDiscountSummary(total);

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

    discountToggle.addEventListener('change', () => {
        discount.enabled = discountToggle.checked;
        discountInputGroup.hidden = !discount.enabled;
        if (discount.enabled) {
            discount.type = 'percent';
            discount.value = 10;
            discountTypeSelect.value = 'percent';
            discountValueInput.value = 10;
        }
        renderCart();
    });

    discountTypeSelect.addEventListener('change', () => {
        discount.type = discountTypeSelect.value === 'fixed' ? 'fixed' : 'percent';
        renderCart();
    });

    discountValueInput.addEventListener('input', () => {
        const value = parseFloat(discountValueInput.value);
        discount.value = isNaN(value) ? 0 : Math.max(0, value);
        recalcGrandTotalLive();
    });

    discountValueInput.addEventListener('change', () => {
        const value = parseFloat(discountValueInput.value);
        discount.value = isNaN(value) ? 0 : Math.max(0, value);
        renderCart();
    });

    // --- Confirmación de venta: medio de pago + vuelto ---
    const saleForm = document.getElementById('sale-form');
    const paymentMethodInput = document.getElementById('payment_method');
    const confirmSaleModalEl = document.getElementById('confirmSaleModal');
    const confirmSaleTotalEl = document.getElementById('confirm-sale-total');
    const paymentMethodButtons = Array.from(document.querySelectorAll('.payment-method-btn'));
    const cashReceivedBlock = document.getElementById('cash-received-block');
    const cashReceivedInput = document.getElementById('cash-received-input');
    const changeDueLine = document.getElementById('change-due-line');
    const changeDueLabel = document.getElementById('change-due-label');
    const changeDueAmount = document.getElementById('change-due-amount');
    const confirmSaleBtn = document.getElementById('confirm-sale-btn');

    function updateChangeDue() {
        const received = parseFloat(cashReceivedInput.value);
        if (isNaN(received) || received <= 0) {
            changeDueLine.hidden = true;
            return;
        }
        const diff = received - currentGrandTotal;
        changeDueLine.hidden = false;
        if (diff >= 0) {
            changeDueLabel.textContent = 'Vuelto:';
            changeDueAmount.textContent = formatMoney(diff);
            changeDueAmount.className = 'fw-semibold text-success';
        } else {
            changeDueLabel.textContent = 'Falta:';
            changeDueAmount.textContent = formatMoney(-diff);
            changeDueAmount.className = 'fw-semibold text-danger';
        }
    }

    function selectPaymentMethod(value) {
        paymentMethodInput.value = value;
        paymentMethodButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.value === value));
        cashReceivedBlock.hidden = value !== 'cash';
        if (value !== 'cash') {
            changeDueLine.hidden = true;
        }
    }

    paymentMethodButtons.forEach((btn) => {
        btn.addEventListener('click', () => selectPaymentMethod(btn.dataset.value));
    });

    cashReceivedInput.addEventListener('input', updateChangeDue);

    confirmSaleModalEl.addEventListener('show.bs.modal', () => {
        confirmSaleTotalEl.textContent = formatMoney(currentGrandTotal);
        cashReceivedInput.value = '';
        changeDueLine.hidden = true;
        selectPaymentMethod('cash');
    });

    confirmSaleBtn.addEventListener('click', () => {
        bootstrap.Modal.getInstance(confirmSaleModalEl)?.hide();
        saleForm.requestSubmit();
    });

    renderCategoryFilters();
    renderAll();
    focusSearch();
});
</script>
</body>
</html>
