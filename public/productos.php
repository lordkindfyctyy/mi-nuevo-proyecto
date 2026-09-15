<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/Product.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$products = $tenantId ? Product::allByTenant($tenantId) : [];

$editing = null;
if (isset($_GET['edit']) && $tenantId) {
    $editing = Product::findForTenant((int) $_GET['edit'], $tenantId);
}

$categories = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'], $products))));
sort($categories);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Productos</h1>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= $_GET['success'] === 'deleted' ? 'Producto eliminado.' : 'Producto guardado correctamente.' ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">No se pudo guardar el producto. Revisa los datos e intenta de nuevo.</div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> o crea un tenant primero.</div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3"><?= $editing ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <form method="POST" action="<?= BASE_URL ?>/process/product_process.php" class="row g-3">
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="sku" class="form-label">SKU</label>
                <input type="text" id="sku" name="sku" class="form-control" value="<?= htmlspecialchars($editing['sku'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="price" class="form-label">Precio</label>
                <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" required value="<?= htmlspecialchars((string) ($editing['price'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label for="cost" class="form-label">Costo</label>
                <input type="number" step="0.01" min="0" id="cost" name="cost" class="form-control" value="<?= htmlspecialchars((string) ($editing['cost'] ?? '0')) ?>">
            </div>
            <div class="col-md-2">
                <label for="stock_quantity" class="form-label">Stock</label>
                <input type="number" min="0" id="stock_quantity" name="stock_quantity" class="form-control" value="<?= htmlspecialchars((string) ($editing['stock_quantity'] ?? '0')) ?>">
            </div>
            <div class="col-md-4">
                <label for="category" class="form-label">Categoría</label>
                <input type="text" id="category" name="category" class="form-control" list="category-suggestions" placeholder="Ej: Alimentos, Accesorios" value="<?= htmlspecialchars($editing['category'] ?? '') ?>">
                <datalist id="category-suggestions">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-8">
                <label for="image_url" class="form-label">URL de imagen (opcional)</label>
                <input type="text" id="image_url" name="image_url" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($editing['image_url'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Descripción corta</label>
                <textarea id="description" name="description" class="form-control" rows="2"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $editing ? 'Guardar cambios' : 'Agregar producto' ?></button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/productos.php" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($products): ?>
<div class="mb-3">
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input type="search" id="product-search" class="form-control" placeholder="Buscar producto por nombre o SKU..." autocomplete="off">
    </div>
</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Categoría</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Costo</th>
                <th class="text-end">Stock</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody id="product-table-body">
            <?php foreach ($products as $product): ?>
                <tr data-name="<?= htmlspecialchars(mb_strtolower($product['name'])) ?>" data-sku="<?= htmlspecialchars(mb_strtolower($product['sku'] ?? '')) ?>">
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($product['category'] ?? '—') ?></td>
                    <td class="text-end">$<?= number_format((float) $product['price'], 2) ?></td>
                    <td class="text-end">$<?= number_format((float) $product['cost'], 2) ?></td>
                    <td class="text-end">
                        <span class="badge <?= (int) $product['stock_quantity'] <= 5 ? 'bg-danger' : 'bg-success-subtle text-success-emphasis' ?>">
                            <?= (int) $product['stock_quantity'] ?>
                        </span>
                    </td>
                    <td><?= $product['status'] === 'active' ? 'Activo' : 'Inactivo' ?></td>
                    <td class="text-end">
                        <a href="<?= BASE_URL ?>/productos.php?edit=<?= (int) $product['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="POST" action="<?= BASE_URL ?>/process/product_process.php" class="d-inline" onsubmit="return confirm('¿Eliminar este producto?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No hay productos registrados todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <p id="product-search-empty" class="text-center text-secondary py-4" hidden>No se encontraron productos con ese criterio.</p>
</div>

<?php if ($products): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('product-search');
    const rows = document.querySelectorAll('#product-table-body tr[data-name]');
    const emptyMessage = document.getElementById('product-search-empty');

    searchInput.addEventListener('input', () => {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const matches = !term || row.dataset.name.includes(term) || row.dataset.sku.includes(term);
            row.hidden = !matches;
            if (matches) visibleCount++;
        });

        emptyMessage.hidden = visibleCount > 0;
    });
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
