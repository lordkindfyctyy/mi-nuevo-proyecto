<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/models/Sale.php';
require_once __DIR__ . '/../src/models/Product.php';

$token = $_GET['token'] ?? '';
$sale = $token !== '' ? Sale::findByPublicToken($token) : null;

if (!$sale) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Remito no encontrado</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head>
    <body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f1f2f4;">
        <div class="text-center p-4">
            <h1 class="h4 fw-bold mb-2">Remito no encontrado</h1>
            <p class="text-secondary">El enlace que abriste no es válido o ya no está disponible.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$items = Sale::itemsFor((int) $sale['id']);

$paymentLabels = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR',
    'other' => 'Otro',
];
$paymentLabel = $paymentLabels[$sale['payment_method']] ?? $sale['payment_method'];
$isCancelled = $sale['status'] !== 'completed';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remito #<?= (int) $sale['id'] ?> · <?= htmlspecialchars($sale['tenant_name']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(180deg, #e6f9f5 0%, #f1f2f4 220px);
            min-height: 100vh;
        }
        .receipt-wrap { max-width: 480px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .receipt-card {
            background: #fff; border-radius: 1.25rem; overflow: hidden;
            box-shadow: 0 1.5rem 3rem rgba(0, 178, 143, .16), 0 .25rem .75rem rgba(0, 0, 0, .06);
        }
        .receipt-header {
            background: linear-gradient(135deg, #00cfa8, #009677);
            color: #fff; text-align: center; padding: 1.75rem 1.5rem 1.5rem;
        }
        .receipt-brand {
            display: inline-flex; align-items: center; gap: .4rem; opacity: .95;
            font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            margin-bottom: .9rem;
        }
        .receipt-brand-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.4rem; height: 1.4rem; border-radius: .4rem;
            background: rgba(255, 255, 255, .25); font-size: .58rem; font-weight: 800; letter-spacing: -.03em;
        }
        .receipt-tenant-name { font-size: 1.4rem; font-weight: 800; margin-bottom: .3rem; }
        .receipt-tenant-meta { font-size: .8rem; opacity: .92; line-height: 1.5; }
        .receipt-body { padding: 1.5rem; }
        .receipt-status-pill {
            display: inline-flex; align-items: center; gap: .4rem; padding: .4rem 1.1rem;
            border-radius: 999px; font-weight: 700; font-size: .8rem; letter-spacing: .02em;
        }
        .receipt-status-pill.paid { background: #00e676; color: #063d24; box-shadow: 0 .3rem .75rem rgba(0, 230, 118, .35); }
        .receipt-status-pill.cancelled { background: #e5e7eb; color: #6b7280; }
        .receipt-hero { text-align: center; margin: 1.1rem 0 1.4rem; }
        .receipt-hero-label { font-size: .74rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .15rem; }
        .receipt-hero-value { font-size: 2.1rem; font-weight: 800; color: #0f172a; }
        .receipt-divider { border-top: 1px dashed #d1d5db; margin: 1.1rem 0; }
        .receipt-meta-row { display: flex; justify-content: space-between; font-size: .85rem; color: #374151; margin-bottom: .4rem; }
        .receipt-items-list { text-align: left; }
        .receipt-item-row { display: flex; gap: .65rem; align-items: center; padding: .6rem 0; border-bottom: 1px solid #f3f4f6; }
        .receipt-item-row:last-child { border-bottom: none; }
        .receipt-item-thumb {
            width: 46px; height: 46px; border-radius: .6rem; background: #f3f4f6; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; overflow: hidden; color: #9ca3af; font-size: 1.1rem;
        }
        .receipt-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .receipt-item-meta { flex: 1; min-width: 0; }
        .receipt-item-name { font-weight: 600; font-size: .88rem; color: #1f2937; }
        .receipt-item-sub { font-size: .76rem; color: #6b7280; }
        .receipt-item-subtotal { font-weight: 700; font-size: .9rem; color: #1f2937; white-space: nowrap; }
        .receipt-discount-row { display: flex; justify-content: space-between; font-size: .85rem; color: #dc3545; margin-top: .6rem; }
        .receipt-grand-total { display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.3rem; margin-top: .75rem; color: #009677; }
        .receipt-footer-band { background: #f6fefb; border-top: 1px dashed #d1d5db; padding: 1.35rem 1.5rem; text-align: center; }
        .receipt-footer-thanks { font-weight: 700; color: #00857a; margin-bottom: .3rem; }
        .receipt-footer-contact { font-size: .78rem; color: #6b7280; line-height: 1.5; }
        .receipt-outer-note { text-align: center; color: #9ca3af; font-size: .72rem; margin-top: 1.25rem; }
    </style>
</head>
<body>
    <div class="receipt-wrap">
        <div class="receipt-card">
            <div class="receipt-header">
                <div class="receipt-brand">
                    <span class="receipt-brand-mark" aria-hidden="true">6&amp;7</span>
                    SixSeven
                </div>
                <div class="receipt-tenant-name"><?= htmlspecialchars($sale['tenant_name']) ?></div>
                <div class="receipt-tenant-meta">
                    <?php if (!empty($sale['tenant_address'])): ?><?= htmlspecialchars($sale['tenant_address']) ?><br><?php endif; ?>
                    <?php if (!empty($sale['tenant_phone'])): ?><?= htmlspecialchars($sale['tenant_phone']) ?><?php endif; ?>
                    <?php if (!empty($sale['tenant_tax_id'])): ?><br>CUIT: <?= htmlspecialchars($sale['tenant_tax_id']) ?><?php endif; ?>
                </div>
            </div>

            <div class="receipt-body text-center">
                <span class="receipt-status-pill <?= $isCancelled ? 'cancelled' : 'paid' ?>">
                    <i class="bi <?= $isCancelled ? 'bi-x-circle-fill' : 'bi-check-circle-fill' ?>"></i>
                    <?= $isCancelled ? 'ANULADA' : 'PAGADA' ?>
                </span>

                <div class="receipt-hero">
                    <div class="receipt-hero-label">Total cobrado</div>
                    <div class="receipt-hero-value">$<?= number_format((float) $sale['total'], 2) ?></div>
                </div>

                <div class="receipt-divider"></div>

                <div class="text-start">
                    <div class="receipt-meta-row">
                        <span class="text-secondary">Venta</span>
                        <span class="fw-semibold">#<?= (int) $sale['id'] ?></span>
                    </div>
                    <div class="receipt-meta-row">
                        <span class="text-secondary">Fecha</span>
                        <span><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($sale['customer_name'])): ?>
                        <div class="receipt-meta-row">
                            <span class="text-secondary">Cliente</span>
                            <span><?= htmlspecialchars($sale['customer_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="receipt-meta-row">
                        <span class="text-secondary">Vendedor</span>
                        <span><?= htmlspecialchars($sale['seller_name']) ?></span>
                    </div>
                    <div class="receipt-meta-row mb-0">
                        <span class="text-secondary">Medio de pago</span>
                        <span><?= htmlspecialchars($paymentLabel) ?></span>
                    </div>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-items-list">
                    <?php foreach ($items as $item): ?>
                        <?php $itemImage = Product::imageUrl(['imagen' => $item['product_imagen'], 'image_url' => $item['product_image_url']]); ?>
                        <div class="receipt-item-row">
                            <div class="receipt-item-thumb">
                                <?php if ($itemImage): ?>
                                    <img src="<?= htmlspecialchars($itemImage) ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <i class="bi bi-box-seam"></i>
                                <?php endif; ?>
                            </div>
                            <div class="receipt-item-meta">
                                <div class="receipt-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="receipt-item-sub"><?= Product::formatQuantity($item['quantity']) ?> <?= ($item['product_sale_unit'] ?? 'unit') === 'weight' ? 'kg' : 'un.' ?> × $<?= number_format((float) $item['unit_price'], 2) ?></div>
                            </div>
                            <div class="receipt-item-subtotal">$<?= number_format((float) $item['subtotal'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ((float) $sale['discount_amount'] > 0): ?>
                    <div class="receipt-discount-row">
                        <span>Descuento</span>
                        <span>- $<?= number_format((float) $sale['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="receipt-grand-total">
                    <span>Total</span>
                    <span>$<?= number_format((float) $sale['total'], 2) ?></span>
                </div>
            </div>

            <div class="receipt-footer-band">
                <div class="receipt-footer-thanks">¡Gracias por tu compra!</div>
                <div class="receipt-footer-contact">
                    <?= htmlspecialchars($sale['tenant_name']) ?>
                    <?php if (!empty($sale['tenant_phone'])): ?> · <?= htmlspecialchars($sale['tenant_phone']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <p class="receipt-outer-note">Comprobante digital generado con SixSeven (6&amp;7)</p>
    </div>
</body>
</html>
