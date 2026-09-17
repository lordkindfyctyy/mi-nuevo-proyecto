<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Customer.php';
require_once __DIR__ . '/../src/models/Sale.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$customers = $tenantId ? Customer::allByTenant($tenantId) : [];
$stats = $tenantId ? Sale::statsByCustomer($tenantId) : [];

$editing = null;
if (isset($_GET['edit']) && $tenantId) {
    $editing = Customer::findForTenant((int) $_GET['edit'], $tenantId);
}

$paymentLabels = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'other' => 'Otro',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Clientes</h1>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= $_GET['success'] === 'deleted' ? 'Cliente eliminado.' : 'Cliente guardado correctamente.' ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">No se pudo guardar el cliente. Revisa los datos e intenta de nuevo.</div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> o crea un tenant primero.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3"><?= $editing ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
        <form method="POST" action="<?= BASE_URL ?>/process/customer_process.php" class="row g-3">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($editing['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="address" class="form-label">Dirección</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($editing['address'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="notes" class="form-label">Notas</label>
                <input type="text" id="notes" name="notes" class="form-control" placeholder="Ej: prefiere entrega los sábados" value="<?= htmlspecialchars($editing['notes'] ?? '') ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $editing ? 'Guardar cambios' : 'Agregar cliente' ?></button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/clientes.php" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($customers): ?>
<div class="mb-3">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="search" id="customer-search" class="form-control" placeholder="Buscar cliente por nombre, teléfono o email..." autocomplete="off">
    </div>
</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th class="text-end">Compras</th>
                <th class="text-end">Total gastado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody id="customer-table-body">
            <?php foreach ($customers as $customer): ?>
                <?php $customerStats = $stats[(int) $customer['id']] ?? ['count' => 0, 'total' => 0.0]; ?>
                <tr data-name="<?= htmlspecialchars(mb_strtolower($customer['name'])) ?>" data-phone="<?= htmlspecialchars(mb_strtolower($customer['phone'] ?? '')) ?>" data-email="<?= htmlspecialchars(mb_strtolower($customer['email'] ?? '')) ?>">
                    <td><?= htmlspecialchars($customer['name']) ?></td>
                    <td><?= htmlspecialchars($customer['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($customer['email'] ?? '—') ?></td>
                    <td class="text-end">
                        <?php if ($customerStats['count'] > 0): ?>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#customer-history-<?= (int) $customer['id'] ?>">
                                <?= $customerStats['count'] ?>
                            </button>
                        <?php else: ?>
                            <span class="text-secondary">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">$<?= number_format($customerStats['total'], 2) ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/clientes.php?edit=<?= (int) $customer['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="POST" action="<?= BASE_URL ?>/process/customer_process.php" class="d-inline" onsubmit="return confirm('¿Eliminar este cliente? Sus ventas anteriores se conservan, solo se desvincula el cliente.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php if ($customerStats['count'] > 0): ?>
                    <tr class="collapse" id="customer-history-<?= (int) $customer['id'] ?>">
                        <td colspan="6" class="bg-light">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Vendedor</th>
                                        <th>Pago</th>
                                        <th class="text-end">Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (Sale::findByCustomerForTenant((int) $customer['id'], $tenantId) as $sale): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($sale['seller_name']) ?></td>
                                            <td><?= $paymentLabels[$sale['payment_method']] ?? $sale['payment_method'] ?></td>
                                            <td class="text-end">$<?= number_format((float) $sale['total'], 2) ?></td>
                                            <td>
                                                <span class="badge <?= $sale['status'] === 'completed' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                                    <?= $sale['status'] === 'completed' ? 'Completada' : 'Cancelada' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No hay clientes registrados todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <p id="customer-search-empty" class="text-center text-secondary py-4" hidden>No se encontraron clientes con ese criterio.</p>
</div>

<?php if ($customers): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('customer-search');
    const rows = document.querySelectorAll('#customer-table-body tr[data-name]');
    const emptyMessage = document.getElementById('customer-search-empty');

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const matches = !term
                || row.dataset.name.includes(term)
                || row.dataset.phone.includes(term)
                || row.dataset.email.includes(term);
            row.hidden = !matches;
            const historyRow = row.nextElementSibling;
            if (historyRow && historyRow.classList.contains('collapse')) {
                historyRow.hidden = !matches;
            }
            if (matches) visibleCount++;
        });

        emptyMessage.hidden = visibleCount > 0;
    });
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
