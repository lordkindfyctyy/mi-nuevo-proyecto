<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Supplier.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$suppliers = $tenantId ? Supplier::allByTenant($tenantId) : [];

$editing = null;
if (isset($_GET['edit']) && $tenantId) {
    $editing = Supplier::findForTenant((int) $_GET['edit'], $tenantId);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Proveedores</h1>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= $_GET['success'] === 'deleted' ? 'Proveedor eliminado.' : 'Proveedor guardado correctamente.' ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">No se pudo guardar el proveedor. Revisa los datos e intenta de nuevo.</div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> o crea un tenant primero.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3"><?= $editing ? 'Editar proveedor' : 'Nuevo proveedor' ?></h2>
        <form method="POST" action="<?= BASE_URL ?>/process/supplier_process.php" class="row g-3">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="contact_name" class="form-label">Contacto</label>
                <input type="text" id="contact_name" name="contact_name" class="form-control" value="<?= htmlspecialchars($editing['contact_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($editing['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="address" class="form-label">Dirección</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($editing['address'] ?? '') ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $editing ? 'Guardar cambios' : 'Agregar proveedor' ?></button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/proveedores.php" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Contacto</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= htmlspecialchars($supplier['name']) ?></td>
                    <td><?= htmlspecialchars($supplier['contact_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($supplier['phone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($supplier['email'] ?? '—') ?></td>
                    <td><?= $supplier['status'] === 'active' ? 'Activo' : 'Inactivo' ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/proveedores.php?edit=<?= (int) $supplier['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="POST" action="<?= BASE_URL ?>/process/supplier_process.php" class="d-inline" onsubmit="return confirm('¿Eliminar este proveedor?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$suppliers): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No hay proveedores registrados todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
