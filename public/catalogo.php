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
    'image' => Product::imageUrl($p) ?? '',
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
        .catalog-filters { display: flex; gap: .5rem; flex-wrap: wrap; margin: .75rem 0 1rem; }
        .catalog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 1rem; }
        .catalog-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; display: flex; flex-direction: column; }
        .catalog-card-image { aspect-ratio: 1 / 1; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 2rem; overflow: hidden; position: relative; }
        .catalog-card-image img { width: 100%; height: 100%; object-fit: cover; }
        .catalog-stock-badge { position: absolute; top: .4rem; right: .4rem; font-size: .68rem; }
        .catalog-card-body { padding: .65rem .8rem .8rem; display: flex; flex-direction: column; gap: .2rem; flex: 1; }
        .catalog-card-price { font-weight: 700; color: #00b28f; font-size: 1.05rem; }
        .catalog-card-name { font-weight: 600; font-size: .88rem; line-height: 1.25; }
        .catalog-card-sku { font-size: .72rem; color: #6b7280; }
        .catalog-ask-btn { margin-top: auto; }
        .catalog-whatsapp-fab {
            position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 20;
            width: 3.5rem; height: 3.5rem; border-radius: 50%; background: #25D366; color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.75rem;
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.2); text-decoration: none;
        }
        .catalog-whatsapp-fab:hover { background: #1ebe5a; color: #fff; }
    </style>
</head>
<body>
    <div class="catalog-header">
        <div class="catalog-body py-0">
            <h1 class="h5 fw-bold mb-0"><?= htmlspecialchars($tenant['name']) ?></h1>
            <div class="text-secondary small">Catálogo en vivo · <span id="last-updated">actualizando...</span></div>
        </div>
    </div>

    <div class="catalog-body">
        <?php if (!$products): ?>
            <p class="text-center text-secondary py-5">Este negocio todavía no tiene productos publicados.</p>
        <?php else: ?>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" id="catalog-search" class="form-control" placeholder="Buscar producto..." autocomplete="off">
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

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        let products = <?= $productsJson ?: '[]' ?>;
        const whatsappDigits = <?= json_encode($whatsappDigits ?: '') ?>;
        let activeCategory = 'all';

        const searchInput = document.getElementById('catalog-search');
        const filtersEl = document.getElementById('catalog-filters');
        const gridEl = document.getElementById('catalog-grid');
        const noResultsEl = document.getElementById('catalog-no-results');
        const lastUpdatedEl = document.getElementById('last-updated');

        if (!gridEl) return;

        function formatMoney(value) {
            return '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
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

        function renderGrid() {
            const term = searchInput.value.trim().toLowerCase();
            const filtered = products.filter((p) => {
                const matchesTerm = !term || p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term);
                const matchesCategory = activeCategory === 'all' || p.category === activeCategory;
                return matchesTerm && matchesCategory;
            });

            gridEl.innerHTML = '';
            noResultsEl.hidden = filtered.length > 0;

            filtered.forEach((product) => {
                const inStock = product.stock > 0;
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
                        ${askUrl ? `<a href="${askUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success catalog-ask-btn"><i class="bi bi-whatsapp"></i> Preguntar</a>` : ''}
                    </div>
                `;
                gridEl.appendChild(card);
            });
        }

        function refreshFromServer() {
            const url = new URL(window.location.href);
            url.searchParams.set('format', 'json');
            fetch(url.toString())
                .then((res) => res.json())
                .then((data) => {
                    products = data.products || [];
                    renderFilters();
                    renderGrid();
                    lastUpdatedEl.textContent = 'actualizado ' + new Date().toLocaleTimeString('es-CO');
                })
                .catch(() => {
                    lastUpdatedEl.textContent = 'sin conexión, mostrando últimos datos';
                });
        }

        searchInput.addEventListener('input', renderGrid);

        renderFilters();
        renderGrid();
        lastUpdatedEl.textContent = 'actualizado ' + new Date().toLocaleTimeString('es-CO');
        setInterval(refreshFromServer, 20000);
    });
    </script>
</body>
</html>
