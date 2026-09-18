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
        body { margin: 0; background: #f1f2f4; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .receipt-wrap { max-width: 480px; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; }
        .receipt-card { background: #fff; border-radius: .75rem; box-shadow: 0 .25rem 1rem rgba(0,0,0,.06); overflow: hidden; }
        .receipt-brand { display: flex; align-items: center; justify-content: center; gap: .5rem; padding: 1rem 0 .5rem; }
        .receipt-brand-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: .6rem;
            background: linear-gradient(135deg, #00b28f, #009677); color: #fff;
            font-weight: 800; font-size: .78rem; letter-spacing: -.04em;
        }
        .receipt-brand-word { font-weight: 700; color: #00b28f; font-size: .95rem; }
        .receipt-body { padding: 0 1.5rem 1.5rem; text-align: center; }
        .receipt-tenant-name { font-weight: 700; font-size: 1.2rem; margin-bottom: .15rem; }
        .receipt-tenant-meta { color: #6b7280; font-size: .82rem; line-height: 1.4; }
        .receipt-cancelled-badge { display: inline-block; margin-top: .5rem; }
        .receipt-divider { border-top: 1px dashed #d1d5db; margin: 1.1rem 0; }
        .receipt-meta-row { display: flex; justify-content: space-between; font-size: .85rem; color: #374151; margin-bottom: .3rem; }
        .receipt-items-table { width: 100%; font-size: .85rem; text-align: left; border-collapse: collapse; }
        .receipt-items-table th { font-weight: 600; color: #6b7280; font-size: .72rem; text-transform: uppercase; padding-bottom: .4rem; border-bottom: 1px solid #e5e7eb; }
        .receipt-items-table td { padding: .4rem 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .receipt-items-table td.num, .receipt-items-table th.num { text-align: right; white-space: nowrap; }
        .receipt-total-row { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.15rem; margin-top: 1rem; }
        .receipt-discount-row { display: flex; justify-content: space-between; font-size: .85rem; color: #dc3545; margin-top: .3rem; }
        .receipt-footer-note { text-align: center; color: #9ca3af; font-size: .75rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="receipt-wrap">
        <div class="receipt-brand">
            <span class="receipt-brand-mark" aria-hidden="true">6&amp;7</span>
            <span class="receipt-brand-word">SixSeven</span>
        </div>
        <div class="receipt-card">
            <div class="receipt-body">
                <div class="receipt-tenant-name"><?= htmlspecialchars($sale['tenant_name']) ?></div>
                <div class="receipt-tenant-meta">
                    <?php if (!empty($sale['tenant_address'])): ?><?= htmlspecialchars($sale['tenant_address']) ?><br><?php endif; ?>
                    <?php if (!empty($sale['tenant_phone'])): ?><?= htmlspecialchars($sale['tenant_phone']) ?><?php endif; ?>
                    <?php if (!empty($sale['tenant_tax_id'])): ?><br>CUIT: <?= htmlspecialchars($sale['tenant_tax_id']) ?><?php endif; ?>
                </div>
                <?php if ($isCancelled): ?>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis receipt-cancelled-badge">Venta anulada</span>
                <?php endif; ?>

                <div class="receipt-divider"></div>

                <div class="receipt-meta-row">
                    <span>Venta</span>
                    <span class="fw-semibold">#<?= (int) $sale['id'] ?></span>
                </div>
                <div class="receipt-meta-row">
                    <span>Fecha</span>
                    <span><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span>
                </div>
                <?php if (!empty($sale['customer_name'])): ?>
                    <div class="receipt-meta-row">
                        <span>Cliente</span>
                        <span><?= htmlspecialchars($sale['customer_name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="receipt-meta-row">
                    <span>Vendedor</span>
                    <span><?= htmlspecialchars($sale['seller_name']) ?></span>
                </div>
                <div class="receipt-meta-row">
                    <span>Medio de pago</span>
                    <span><?= htmlspecialchars($paymentLabel) ?></span>
                </div>

                <div class="receipt-divider"></div>

                <table class="receipt-items-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="num">Cant.</th>
                            <th class="num">P. unit.</th>
                            <th class="num">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="num"><?= Product::formatQuantity($item['quantity']) ?></td>
                                <td class="num">$<?= number_format((float) $item['unit_price'], 2) ?></td>
                                <td class="num">$<?= number_format((float) $item['subtotal'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ((float) $sale['discount_amount'] > 0): ?>
                    <div class="receipt-discount-row">
                        <span>Descuento</span>
                        <span>- $<?= number_format((float) $sale['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="receipt-total-row">
                    <span>Total</span>
                    <span>$<?= number_format((float) $sale['total'], 2) ?></span>
                </div>
            </div>
        </div>
        <p class="receipt-footer-note">Comprobante digital generado con SixSeven (6&amp;7)</p>
    </div>
</body>
</html>
