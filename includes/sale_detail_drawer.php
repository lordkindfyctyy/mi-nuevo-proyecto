<?php
$saleDetailRedirectPath = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/ventas.php');
?>
<div class="offcanvas offcanvas-end sale-detail-drawer" tabindex="-1" id="saleDetailDrawer" aria-labelledby="saleDetailDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-0" id="saleDetailDrawerLabel">Venta <span id="sd-sale-number"></span></h5>
            <span class="badge mt-1" id="sd-status-badge"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <div id="sd-loading" class="text-center text-secondary py-5">Cargando...</div>
        <div id="sd-error" class="alert alert-danger m-3" hidden></div>
        <div id="sd-content" class="flex-grow-1 overflow-auto" hidden>
            <div class="p-3 border-bottom text-center bg-light">
                <div class="h3 fw-bold mb-1" id="sd-total"></div>
                <div class="small fw-semibold text-success" id="sd-profit"></div>
            </div>
            <div class="p-3 border-bottom small">
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Fecha</span><span id="sd-date"></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Medio de pago</span><span id="sd-payment"></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Cliente</span><span id="sd-customer"></span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Vendedor</span><span id="sd-seller"></span></div>
            </div>
            <div class="p-3">
                <div id="sd-items"></div>
                <div class="d-flex justify-content-between small text-danger mt-2" id="sd-discount-row" hidden>
                    <span>Descuento</span><span id="sd-discount-amount"></span>
                </div>
            </div>
        </div>
    </div>
    <div class="sale-detail-actions border-top p-3" id="sd-actions" hidden>
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary rounded-circle sd-action-btn" id="sd-print-btn" title="Imprimir" aria-label="Imprimir">
                <i class="bi bi-printer"></i>
            </button>
            <a href="#" target="_blank" rel="noopener" class="btn btn-outline-success rounded-circle sd-action-btn" id="sd-whatsapp-btn" title="Comprobante por WhatsApp" aria-label="Comprobante por WhatsApp">
                <i class="bi bi-whatsapp"></i>
            </a>
            <button type="button" class="btn btn-outline-secondary rounded-circle sd-action-btn" id="sd-email-btn" title="Enviar por email" aria-label="Enviar por email">
                <i class="bi bi-envelope"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-circle sd-action-btn" id="sd-share-btn" title="Compartir" aria-label="Compartir">
                <i class="bi bi-share"></i>
            </button>
            <button type="button" class="btn btn-outline-primary rounded-circle sd-action-btn" id="sd-edit-btn" title="Editar" aria-label="Editar">
                <i class="bi bi-pencil"></i>
            </button>
            <button type="button" class="btn btn-outline-danger rounded-circle sd-action-btn" id="sd-delete-btn" title="Eliminar" aria-label="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="input-group input-group-sm" id="sd-email-form" hidden>
            <input type="email" class="form-control" id="sd-email-input" placeholder="Email del cliente" autocomplete="off">
            <button class="btn btn-primary" type="button" id="sd-email-send-btn">Enviar</button>
            <button class="btn btn-outline-secondary" type="button" id="sd-email-cancel-btn" aria-label="Cancelar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="small text-secondary text-center mt-1" id="sd-share-hint" hidden></div>
    </div>
</div>

<div id="print-receipt"></div>

<style>
    .sale-detail-drawer { width: min(420px, 100vw); }
    .sd-action-btn { width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; padding: 0; font-size: 1.1rem; }
    .sd-item-row { display: flex; gap: .6rem; align-items: center; padding: .55rem 0; border-bottom: 1px solid #f1f2f4; }
    .sd-item-row:last-child { border-bottom: none; }
    .sd-item-thumb {
        width: 42px; height: 42px; border-radius: .5rem; background: #f3f4f6; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; overflow: hidden; color: #9ca3af;
    }
    .sd-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .sd-item-meta { flex: 1; min-width: 0; }
    .sd-item-name { font-weight: 600; font-size: .86rem; }
    .sd-item-sub { font-size: .74rem; color: #6b7280; }
    .sd-item-subtotal { font-weight: 600; white-space: nowrap; font-size: .88rem; }
    tr.sale-row { cursor: pointer; }
    tr.sale-row:hover { background: #f8f9fa; }

    #print-receipt { display: none; }

    @media print {
        body * { visibility: hidden; }
        #print-receipt, #print-receipt * { visibility: visible; }
        #print-receipt {
            display: block !important;
            position: absolute; top: 0; left: 0; width: 100%;
        }
        @page { margin: 8mm; }

        .receipt-ticket {
            width: 280px;
            margin: 0 auto;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
            font-size: 12px;
        }
        .receipt-ticket .rt-brand { text-align: center; font-weight: 700; letter-spacing: .05em; margin-bottom: 2px; }
        .receipt-ticket .rt-tenant-name { text-align: center; font-weight: 700; font-size: 14px; }
        .receipt-ticket .rt-tenant-meta { text-align: center; font-size: 11px; margin-bottom: 6px; }
        .receipt-ticket hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        .receipt-ticket .rt-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .receipt-ticket table.rt-items { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .receipt-ticket table.rt-items th { text-align: left; font-size: 10px; border-bottom: 1px solid #000; padding-bottom: 2px; }
        .receipt-ticket table.rt-items th.num, .receipt-ticket table.rt-items td.num { text-align: right; }
        .receipt-ticket table.rt-items td { font-size: 11px; padding: 2px 0; vertical-align: top; }
        .receipt-ticket .rt-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 14px; margin-top: 4px; }
        .receipt-ticket .rt-thanks { text-align: center; margin-top: 10px; font-size: 11px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const drawerEl = document.getElementById('saleDetailDrawer');
    if (!drawerEl || !window.bootstrap) return;

    const redirectPath = <?= json_encode($saleDetailRedirectPath) ?>;
    const drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    const loadingEl = document.getElementById('sd-loading');
    const errorEl = document.getElementById('sd-error');
    const contentEl = document.getElementById('sd-content');
    const actionsEl = document.getElementById('sd-actions');
    const saleNumberEl = document.getElementById('sd-sale-number');
    const statusBadgeEl = document.getElementById('sd-status-badge');
    const totalEl = document.getElementById('sd-total');
    const profitEl = document.getElementById('sd-profit');
    const dateEl = document.getElementById('sd-date');
    const paymentEl = document.getElementById('sd-payment');
    const customerEl = document.getElementById('sd-customer');
    const sellerEl = document.getElementById('sd-seller');
    const itemsEl = document.getElementById('sd-items');
    const discountRowEl = document.getElementById('sd-discount-row');
    const discountAmountEl = document.getElementById('sd-discount-amount');
    const printBtn = document.getElementById('sd-print-btn');
    const whatsappBtn = document.getElementById('sd-whatsapp-btn');
    const emailBtn = document.getElementById('sd-email-btn');
    const emailForm = document.getElementById('sd-email-form');
    const emailInput = document.getElementById('sd-email-input');
    const emailSendBtn = document.getElementById('sd-email-send-btn');
    const emailCancelBtn = document.getElementById('sd-email-cancel-btn');
    const shareBtn = document.getElementById('sd-share-btn');
    const shareHintEl = document.getElementById('sd-share-hint');
    const editBtn = document.getElementById('sd-edit-btn');
    const deleteBtn = document.getElementById('sd-delete-btn');
    const printReceiptEl = document.getElementById('print-receipt');

    let currentDetail = null;

    function formatMoney(value) {
        return '$' + Number(value || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatQty(value) {
        return Number(value).toLocaleString('es-CO', { maximumFractionDigits: 3 });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // Resumen en texto plano (para el body de un mailto: y para el texto de
    // Web Share, ninguno de los dos admite HTML) con el detalle de la venta
    // y el link al remito con el diseño completo.
    function buildShareSummary(data) {
        const sale = data.sale;
        const dateLabel = new Date(sale.created_at.replace(' ', 'T')).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
        const lines = [
            `Hola, te compartimos el comprobante de tu compra en ${data.tenant.name}.`,
            '',
            `Venta #${sale.id} · ${dateLabel}`,
            'Detalle de productos:',
            ...data.items.map((item) => `- ${item.name} x${formatQty(item.quantity)} = ${formatMoney(item.subtotal)}`),
            '',
            `Total: ${formatMoney(sale.total)}`,
            `Medio de pago: ${sale.payment_label}`,
            '',
            `Ver el remito completo: ${sale.receipt_url}`,
            '',
            '¡Gracias por tu compra!',
        ];
        return lines.join('\n');
    }

    function renderDetail(data) {
        const sale = data.sale;
        saleNumberEl.textContent = '#' + sale.id;
        statusBadgeEl.textContent = sale.status_label;
        statusBadgeEl.className = 'badge mt-1 ' + (sale.status === 'completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis');
        totalEl.textContent = formatMoney(sale.total);
        profitEl.textContent = 'Ganancia: ' + formatMoney(sale.profit);
        profitEl.className = 'small fw-semibold ' + (sale.profit >= 0 ? 'text-success' : 'text-danger');
        dateEl.textContent = new Date(sale.created_at.replace(' ', 'T')).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
        paymentEl.textContent = sale.payment_label;
        customerEl.textContent = sale.customer_name || 'Ocasional';
        sellerEl.textContent = sale.seller_name || '—';

        itemsEl.innerHTML = '';
        data.items.forEach((item) => {
            const unitLabel = item.sale_unit === 'weight' ? 'kg' : 'un.';
            const row = document.createElement('div');
            row.className = 'sd-item-row';
            row.innerHTML = `
                <div class="sd-item-thumb">
                    ${item.image ? `<img src="${escapeHtml(item.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : '<i class="bi bi-box-seam"></i>'}
                </div>
                <div class="sd-item-meta">
                    <div class="sd-item-name">${escapeHtml(item.name)}</div>
                    <div class="sd-item-sub">${formatQty(item.quantity)} ${unitLabel} × ${formatMoney(item.unit_price)}</div>
                </div>
                <div class="sd-item-subtotal">${formatMoney(item.subtotal)}</div>
            `;
            itemsEl.appendChild(row);
        });

        discountRowEl.hidden = sale.discount_amount <= 0;
        if (sale.discount_amount > 0) {
            discountAmountEl.textContent = '- ' + formatMoney(sale.discount_amount);
        }

        whatsappBtn.href = sale.whatsapp_url;
        editBtn.hidden = sale.status !== 'completed';
        deleteBtn.hidden = sale.status !== 'completed';
    }

    function buildPrintReceipt(data) {
        const sale = data.sale;
        const tenant = data.tenant;
        const itemsRows = data.items.map((item) => `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td class="num">${formatQty(item.quantity)}</td>
                <td class="num">${formatMoney(item.unit_price)}</td>
                <td class="num">${formatMoney(item.subtotal)}</td>
            </tr>
        `).join('');

        const dateLabel = new Date(sale.created_at.replace(' ', 'T')).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' });

        printReceiptEl.innerHTML = `
            <div class="receipt-ticket">
                <div class="rt-brand">6&amp;7 SixSeven</div>
                <div class="rt-tenant-name">${escapeHtml(tenant.name)}</div>
                <div class="rt-tenant-meta">
                    ${tenant.address ? escapeHtml(tenant.address) + '<br>' : ''}
                    ${tenant.phone ? escapeHtml(tenant.phone) : ''}
                    ${tenant.tax_id ? '<br>CUIT: ' + escapeHtml(tenant.tax_id) : ''}
                </div>
                <hr>
                <div class="rt-row"><span>Venta</span><span>#${sale.id}</span></div>
                <div class="rt-row"><span>Fecha</span><span>${dateLabel}</span></div>
                ${sale.customer_name ? `<div class="rt-row"><span>Cliente</span><span>${escapeHtml(sale.customer_name)}</span></div>` : ''}
                <hr>
                <table class="rt-items">
                    <thead>
                        <tr><th>Producto</th><th class="num">Cant.</th><th class="num">P.Unit</th><th class="num">Subt.</th></tr>
                    </thead>
                    <tbody>${itemsRows}</tbody>
                </table>
                <hr>
                ${sale.discount_amount > 0 ? `<div class="rt-row"><span>Descuento</span><span>-${formatMoney(sale.discount_amount)}</span></div>` : ''}
                <div class="rt-total"><span>TOTAL</span><span>${formatMoney(sale.total)}</span></div>
                <div class="rt-row"><span>Medio de pago</span><span>${escapeHtml(sale.payment_label)}</span></div>
                <hr>
                <div class="rt-thanks">¡Gracias por tu compra!</div>
            </div>
        `;
    }

    async function openDrawerForSale(saleId) {
        loadingEl.hidden = false;
        errorEl.hidden = true;
        contentEl.hidden = true;
        actionsEl.hidden = true;
        emailForm.hidden = true;
        shareHintEl.hidden = true;
        drawer.show();

        try {
            const res = await fetch(`<?= BASE_URL ?>/api/sale_detail.php?sale_id=${encodeURIComponent(saleId)}`);
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Tu sesión pudo haber expirado. Recargá la página.');
            }
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la venta.');

            currentDetail = data;
            renderDetail(data);
            buildPrintReceipt(data);
            loadingEl.hidden = true;
            contentEl.hidden = false;
            actionsEl.hidden = false;
        } catch (e) {
            loadingEl.hidden = true;
            errorEl.hidden = false;
            errorEl.textContent = e.message || 'No se pudo cargar la venta.';
        }
    }

    document.addEventListener('click', (e) => {
        const row = e.target.closest('.sale-row');
        if (!row || e.target.closest('a, button, form, input')) return;
        openDrawerForSale(row.dataset.saleId);
    });

    printBtn.addEventListener('click', () => {
        window.print();
    });

    editBtn.addEventListener('click', () => {
        if (!currentDetail || !window.openSaleEditModal) return;
        const saleId = currentDetail.sale.id;
        drawer.hide();
        setTimeout(() => window.openSaleEditModal(saleId), 200);
    });

    emailBtn.addEventListener('click', () => {
        emailForm.hidden = false;
        emailInput.value = '';
        emailInput.focus();
    });

    emailCancelBtn.addEventListener('click', () => {
        emailForm.hidden = true;
    });

    emailSendBtn.addEventListener('click', () => {
        const email = emailInput.value.trim();
        if (!email || !email.includes('@')) {
            emailInput.focus();
            return;
        }
        if (!currentDetail) return;

        const sale = currentDetail.sale;
        const subject = `Comprobante de tu compra en ${currentDetail.tenant.name} · Venta #${sale.id}`;
        const body = buildShareSummary(currentDetail);
        // mailto: no admite HTML en el body (es solo texto plano); por eso
        // el cuerpo es un resumen y el diseño completo vive en el link al
        // remito. Abre el cliente de correo del vendedor, no envía nada
        // desde el servidor (esta app no tiene un servicio de email configurado).
        window.location.href = `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        emailForm.hidden = true;
    });

    shareBtn.addEventListener('click', async () => {
        if (!currentDetail) return;
        const sale = currentDetail.sale;
        shareHintEl.hidden = true;

        if (navigator.share) {
            try {
                await navigator.share({
                    title: `Comprobante de venta #${sale.id}`,
                    text: buildShareSummary(currentDetail),
                    url: sale.receipt_url,
                });
            } catch (e) {
                // El usuario cerró la hoja de compartir sin elegir nada: no es un error.
            }
            return;
        }

        try {
            await navigator.clipboard.writeText(sale.receipt_url);
            shareHintEl.textContent = 'Tu navegador no soporta compartir nativo: copiamos el link del remito al portapapeles.';
            shareHintEl.hidden = false;
        } catch (e) {
            shareHintEl.textContent = 'Tu navegador no soporta compartir nativo. Copiá el link manualmente: ' + sale.receipt_url;
            shareHintEl.hidden = false;
        }
    });

    deleteBtn.addEventListener('click', async () => {
        if (!currentDetail) return;
        const saleId = currentDetail.sale.id;
        if (!confirm(`¿Anular la venta #${saleId}? Se restituirá el stock de los productos vendidos.`)) return;

        const fd = new URLSearchParams();
        fd.set('sale_id', saleId);
        fd.set('redirect_to', redirectPath);

        try {
            const res = await fetch('<?= BASE_URL ?>/process/sale_cancel_process.php', { method: 'POST', body: fd });
            window.location.href = res.url;
        } catch (e) {
            alert('No se pudo anular la venta.');
        }
    });
});
</script>
