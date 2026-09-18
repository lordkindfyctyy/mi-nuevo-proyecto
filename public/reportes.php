<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Report.php';
require_once __DIR__ . '/../src/models/Product.php';
require_once __DIR__ . '/../src/models/Sale.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$period = Report::normalizePeriod($_GET['period'] ?? null);
$range = Report::resolveRange($period, $_GET['start'] ?? null, $_GET['end'] ?? null);
$period = $range['period'];

$summary = $tenantId ? Report::summary($tenantId, $range) : ['count' => 0, 'total' => 0.0, 'average' => 0.0];
$topProduct = $tenantId ? Report::topProduct($tenantId, $range) : null;
$topProducts = $tenantId ? Report::topProducts($tenantId, $range, 5) : [];
$margin = $tenantId ? Report::margin($tenantId, $range) : ['revenue' => 0, 'cost' => 0, 'margin' => 0, 'margin_pct' => 0];
$trend = $tenantId ? Report::salesTrend($tenantId, $range) : [];
$sales = $tenantId ? Sale::findByDateRangeForTenant($tenantId, $range['start'], $range['end']) : [];

$trendLabels = json_encode(array_map(fn($d) => $d['label'], $trend));
$trendTotals = json_encode(array_map(fn($d) => round($d['total'], 2), $trend));
$topProductsLabels = json_encode(array_map(fn($p) => $p['name'], $topProducts), JSON_UNESCAPED_UNICODE);
$topProductsQuantities = json_encode(array_map(fn($p) => (float) $p['quantity_sold'], $topProducts));

$periodOptions = [
    'day' => 'Hoy',
    'week' => 'Esta semana',
    'month' => 'Este mes',
    'year' => 'Este año',
];

$paymentLabels = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR',
    'other' => 'Otro',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <h1 class="h3 fw-bold mb-0">Balance</h1>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group" role="group" aria-label="Filtrar por período">
            <?php foreach ($periodOptions as $value => $label): ?>
                <a href="<?= BASE_URL ?>/reportes.php?period=<?= $value ?>"
                   class="btn btn-sm <?= $period === $value ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <form method="GET" action="<?= BASE_URL ?>/reportes.php" class="d-flex align-items-center gap-1">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="start" class="form-control form-control-sm" value="<?= htmlspecialchars($range['startInput']) ?>" required>
            <span class="text-secondary small">a</span>
            <input type="date" name="end" class="form-control form-control-sm" value="<?= htmlspecialchars($range['endInput']) ?>" required>
            <button type="submit" class="btn btn-sm <?= $period === 'custom' ? 'btn-primary' : 'btn-outline-secondary' ?>">Aplicar</button>
        </form>
    </div>
</div>

<?php if (isset($_GET['edit_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Venta actualizada correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif (isset($_GET['edit_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">No se pudo actualizar la venta<?= $_GET['edit_error'] === 'stock' ? ' (no hay suficiente stock para uno o más productos)' : '' ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_GET['cancel_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">Venta anulada y stock restituido.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php elseif (isset($_GET['cancel_error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">No se pudo anular la venta.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía.</div>
<?php else: ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total facturado</div>
                <div class="h3 fw-bold mb-0">$<?= number_format($summary['total'], 2) ?></div>
                <div class="text-secondary small mt-1"><?= htmlspecialchars($range['label']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Cantidad de ventas</div>
                <div class="h3 fw-bold mb-0"><?= $summary['count'] ?></div>
                <div class="text-secondary small mt-1"><?= htmlspecialchars($range['label']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Promedio de ticket</div>
                <div class="h3 fw-bold mb-0">$<?= number_format($summary['average'], 2) ?></div>
                <div class="text-secondary small mt-1"><?= htmlspecialchars($range['label']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Producto más vendido</div>
                <?php if ($topProduct): ?>
                    <div class="h5 fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($topProduct['name']) ?>"><?= htmlspecialchars($topProduct['name']) ?></div>
                    <div class="text-secondary small mt-1"><?= Product::formatQuantity($topProduct['quantity_sold']) ?> unidades · <?= htmlspecialchars($range['label']) ?></div>
                <?php else: ?>
                    <div class="h5 fw-semibold mb-0 text-secondary">Sin ventas</div>
                    <div class="text-secondary small mt-1"><?= htmlspecialchars($range['label']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Margen de ganancia</div>
                <div class="h5 fw-bold mb-0 <?= $margin['margin'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    $<?= number_format($margin['margin'], 2) ?>
                </div>
                <div class="text-secondary small mt-1">
                    <?= number_format($margin['margin_pct'], 1) ?>% sobre $<?= number_format($margin['revenue'], 2) ?> · <?= htmlspecialchars($range['label']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-3">Ventas · <?= htmlspecialchars($range['label']) ?></h2>
                <?php if ($trend): ?>
                    <canvas id="sales-chart" height="220"></canvas>
                <?php else: ?>
                    <p class="text-secondary text-center py-4 mb-0">Sin ventas en este período.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-3">Top 5 productos más vendidos · <?= htmlspecialchars($range['label']) ?></h2>
                <?php if ($topProducts): ?>
                    <canvas id="top-products-chart" height="220"></canvas>
                <?php else: ?>
                    <p class="text-secondary text-center py-4 mb-0">Sin ventas en este período.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h6 fw-semibold mb-3">Ventas detalladas · <?= htmlspecialchars($range['label']) ?></h2>
        <?php if (!$sales): ?>
            <p class="text-secondary text-center py-4 mb-0">No hay ventas registradas en este período.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Pago</th>
                        <th class="text-end">Descuento</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr class="sale-row" data-sale-id="<?= (int) $sale['id'] ?>">
                            <td>#<?= (int) $sale['id'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($sale['seller_name']) ?></td>
                            <td><?= $paymentLabels[$sale['payment_method']] ?? $sale['payment_method'] ?></td>
                            <td class="text-end"><?= (float) $sale['discount_amount'] > 0 ? '- $' . number_format((float) $sale['discount_amount'], 2) : '—' ?></td>
                            <td class="text-end">$<?= number_format((float) $sale['total'], 2) ?></td>
                            <td>
                                <span class="badge <?= $sale['status'] === 'completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                    <?= $sale['status'] === 'completed' ? 'Completada' : 'Cancelada' ?>
                                </span>
                            </td>
                            <td class="text-end text-secondary"><i class="bi bi-chevron-right"></i></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($trend): ?>
    new Chart(document.getElementById('sales-chart'), {
        type: 'bar',
        data: {
            labels: <?= $trendLabels ?>,
            datasets: [{
                label: 'Total vendido',
                data: <?= $trendTotals ?>,
                backgroundColor: '#00b28f',
                borderRadius: 4,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
    <?php endif; ?>

    <?php if ($topProducts): ?>
    new Chart(document.getElementById('top-products-chart'), {
        type: 'bar',
        data: {
            labels: <?= $topProductsLabels ?>,
            datasets: [{
                label: 'Unidades vendidas',
                data: <?= $topProductsQuantities ?>,
                backgroundColor: '#009677',
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/sale_edit_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/sale_detail_drawer.php'; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
