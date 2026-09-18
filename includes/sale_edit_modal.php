<?php
require_once __DIR__ . '/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';

$saleEditTenantId = currentTenantId();
$saleEditProducts = $saleEditTenantId ? Product::allByTenant($saleEditTenantId) : [];
$saleEditProducts = array_values(array_filter($saleEditProducts, fn($p) => $p['status'] === 'active'));
$saleEditProductsJson = json_encode(array_map(fn($p) => [
    'id' => (int) $p['id'],
    'name' => $p['name'],
    'sku' => $p['sku'] ?? '',
    'price' => (float) $p['price'],
    'stock' => (float) $p['stock_quantity'],
    'saleUnit' => $p['sale_unit'] ?? 'unit',
], $saleEditProducts), JSON_UNESCAPED_UNICODE);
$saleEditRedirectPath = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/ventas.php');
?>
<div class="modal fade" id="saleEditModal" tabindex="-1" aria-labelledby="saleEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saleEditModalLabel">Editar venta <span id="sale-edit-id-label"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="sale-edit-loading" class="text-center text-secondary py-4">Cargando...</div>
                <div id="sale-edit-error" class="alert alert-danger py-2 small" hidden></div>
                <div id="sale-edit-content" hidden>
                    <div class="mb-3 position-relative">
                        <label for="sale-edit-search" class="form-label small text-secondary">Agregar producto</label>
                        <input type="search" id="sale-edit-search" class="form-control" placeholder="Buscar por nombre o SKU..." autocomplete="off">
                        <div id="sale-edit-search-results" class="list-group position-absolute w-100 shadow-sm" style="z-index: 5; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:110px;">Cantidad</th>
                                    <th style="width:130px;">Precio unit.</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="sale-edit-items-body"></tbody>
                        </table>
                    </div>
                    <p id="sale-edit-empty-msg" class="text-secondary small text-center py-2" hidden>Sin ítems: agregá al menos un producto.</p>
                    <div class="d-flex justify-content-between fw-bold border-top pt-2">
                        <span>Total</span>
                        <span id="sale-edit-total">$0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="sale-edit-save-btn" disabled>Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('saleEditModal');
    if (!modalEl || !window.bootstrap) return;

    const products = <?= $saleEditProductsJson ?: '[]' ?>;
    const redirectPath = <?= json_encode($saleEditRedirectPath) ?>;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const loadingEl = document.getElementById('sale-edit-loading');
    const errorEl = document.getElementById('sale-edit-error');
    const contentEl = document.getElementById('sale-edit-content');
    const idLabelEl = document.getElementById('sale-edit-id-label');
    const itemsBody = document.getElementById('sale-edit-items-body');
    const emptyMsgEl = document.getElementById('sale-edit-empty-msg');
    const totalEl = document.getElementById('sale-edit-total');
    const saveBtn = document.getElementById('sale-edit-save-btn');
    const searchInput = document.getElementById('sale-edit-search');
    const searchResultsEl = document.getElementById('sale-edit-search-results');

    let currentSaleId = null;
    let items = [];

    function formatMoney(value) {
        return '$' + Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function findProduct(id) {
        return products.find((p) => p.id === id);
    }

    function renderItems() {
        itemsBody.innerHTML = '';
        let total = 0;
        items.forEach((item, index) => {
            const subtotal = item.quantity * item.unit_price;
            total += subtotal;
            const product = findProduct(item.product_id);
            const isWeight = (product ? product.saleUnit : item.saleUnit) === 'weight';
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(item.name)}</td>
                <td><input type="number" step="${isWeight ? 'any' : '1'}" min="0" class="form-control form-control-sm item-qty-input" value="${item.quantity}"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price-input" value="${item.unit_price}"></td>
                <td class="text-end item-subtotal">${formatMoney(subtotal)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0 item-remove-btn" aria-label="Quitar"><i class="bi bi-trash"></i></button></td>
            `;
            row.querySelector('.item-qty-input').addEventListener('input', (e) => {
                const qty = parseFloat(e.target.value);
                items[index].quantity = isNaN(qty) ? 0 : qty;
                renderItems();
            });
            row.querySelector('.item-price-input').addEventListener('input', (e) => {
                const price = parseFloat(e.target.value);
                items[index].unit_price = isNaN(price) ? 0 : price;
                renderItems();
            });
            row.querySelector('.item-remove-btn').addEventListener('click', () => {
                items.splice(index, 1);
                renderItems();
            });
            itemsBody.appendChild(row);
        });
        totalEl.textContent = formatMoney(total);
        emptyMsgEl.hidden = items.length > 0;
        saveBtn.disabled = items.length === 0;
    }

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.trim().toLowerCase();
        searchResultsEl.innerHTML = '';
        if (!term) return;
        const matches = products.filter((p) => p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term)).slice(0, 8);
        matches.forEach((p) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-1 small';
            btn.textContent = `${p.name} · ${formatMoney(p.price)}`;
            btn.addEventListener('click', () => {
                const existing = items.find((it) => it.product_id === p.id);
                if (existing) {
                    existing.quantity += p.saleUnit === 'weight' ? 0.5 : 1;
                } else {
                    items.push({ product_id: p.id, name: p.name, quantity: p.saleUnit === 'weight' ? 0.5 : 1, unit_price: p.price, saleUnit: p.saleUnit });
                }
                renderItems();
                searchInput.value = '';
                searchResultsEl.innerHTML = '';
            });
            searchResultsEl.appendChild(btn);
        });
    });

    document.addEventListener('click', (e) => {
        if (!searchResultsEl.contains(e.target) && e.target !== searchInput) {
            searchResultsEl.innerHTML = '';
        }
    });

    async function openForSale(saleId) {
        currentSaleId = saleId;
        idLabelEl.textContent = '#' + saleId;
        loadingEl.hidden = false;
        errorEl.hidden = true;
        contentEl.hidden = true;
        saveBtn.disabled = true;
        modal.show();

        try {
            const res = await fetch(`<?= BASE_URL ?>/api/sale_items.php?sale_id=${encodeURIComponent(saleId)}`);
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Tu sesión pudo haber expirado. Recargá la página.');
            }
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la venta.');

            items = data.items.map((item) => ({ ...item }));
            renderItems();
            loadingEl.hidden = true;
            contentEl.hidden = false;
        } catch (e) {
            loadingEl.hidden = true;
            errorEl.hidden = false;
            errorEl.textContent = e.message || 'No se pudo cargar la venta.';
        }
    }

    document.querySelectorAll('.sale-edit-trigger').forEach((btn) => {
        btn.addEventListener('click', () => openForSale(btn.dataset.saleId));
    });

    saveBtn.addEventListener('click', () => {
        if (!currentSaleId || items.length === 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/process/sale_edit_process.php';

        const addField = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        addField('sale_id', currentSaleId);
        addField('redirect_to', redirectPath);
        items.forEach((item) => {
            addField(`quantity[${item.product_id}]`, item.quantity);
            addField(`price[${item.product_id}]`, item.unit_price);
        });

        document.body.appendChild(form);
        form.submit();
    });
});
</script>
