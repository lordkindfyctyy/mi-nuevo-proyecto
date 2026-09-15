<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Report.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$period = Report::normalizePeriod($_GET['period'] ?? null);
$periodLabel = Report::periodLabel($period);

$totalVendido = $tenantId ? Report::totalSold($tenantId, $period) : 0.0;
$topProduct = $tenantId ? Report::topProduct($tenantId, $period) : null;
$topProducts = $tenantId ? Report::topProducts($tenantId, $period, 5) : [];
$margin = $tenantId ? Report::margin($tenantId, $period) : ['revenue' => 0, 'cost' => 0, 'margin' => 0, 'margin_pct' => 0];
$trend = $tenantId ? Report::salesTrend($tenantId, $period) : [];

$trendLabels = json_encode(array_map(fn($d) => $d['label'], $trend));
$trendTotals = json_encode(array_map(fn($d) => round($d['total'], 2), $trend));
$topProductsLabels = json_encode(array_map(fn($p) => $p['name'], $topProducts), JSON_UNESCAPED_UNICODE);
$topProductsQuantities = json_encode(array_map(fn($p) => (int) $p['quantity_sold'], $topProducts));

$periodOptions = [
    'day' => 'Día',
    'week' => 'Semana',
    'month' => 'Mes',
    'year' => 'Año',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h1 class="h3 fw-bold mb-0">Reportes</h1>
    <div class="btn-group" role="group" aria-label="Filtrar por período">
        <?php foreach ($periodOptions as $value => $label): ?>
            <a href="<?= BASE_URL ?>/reportes.php?period=<?= $value ?>"
               class="btn btn-sm <?= $period === $value ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía.</div>
<?php else: ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total vendido</div>
                <div class="h3 fw-bold mb-0">$<?= number_format($totalVendido, 2) ?></div>
                <div class="text-secondary small mt-1"><?= htmlspecialchars($periodLabel) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Producto más vendido</div>
                <?php if ($topProduct): ?>
                    <div class="h3 fw-bold mb-0 text-truncate" title="<?= htmlspecialchars($topProduct['name']) ?>"><?= htmlspecialchars($topProduct['name']) ?></div>
                    <div class="text-secondary small mt-1"><?= (int) $topProduct['quantity_sold'] ?> unidades · <?= htmlspecialchars($periodLabel) ?></div>
                <?php else: ?>
                    <div class="h5 fw-semibold mb-0 text-secondary">Sin ventas</div>
                    <div class="text-secondary small mt-1"><?= htmlspecialchars($periodLabel) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Margen de ganancia</div>
                <div class="h3 fw-bold mb-0 <?= $margin['margin'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    $<?= number_format($margin['margin'], 2) ?>
                </div>
                <div class="text-secondary small mt-1">
                    <?= number_format($margin['margin_pct'], 1) ?>% sobre $<?= number_format($margin['revenue'], 2) ?> · <?= htmlspecialchars($periodLabel) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-3">Ventas · <?= htmlspecialchars($periodLabel) ?></h2>
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
                <h2 class="h6 fw-semibold mb-3">Top 5 productos más vendidos · <?= htmlspecialchars($periodLabel) ?></h2>
                <?php if ($topProducts): ?>
                    <canvas id="top-products-chart" height="220"></canvas>
                <?php else: ?>
                    <p class="text-secondary text-center py-4 mb-0">Sin ventas en este período.</p>
                <?php endif; ?>
            </div>
        </div>
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

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
