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

$brands = array_values(array_unique(array_filter(array_map(fn($p) => $p['brand'], $products))));
sort($brands);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Productos</h1>
    <?php if (!$editing): ?>
        <button type="button" class="btn btn-primary rounded-pill px-4" id="openCreateProductBtn">
            <i class="bi bi-plus-lg"></i> Crear producto
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php if ($_GET['success'] === 'deleted'): ?>
            Producto eliminado.
        <?php elseif ($_GET['success'] === 'variants'): ?>
            Se <?= ((int) ($_GET['count'] ?? 0)) === 1 ? 'creó 1 presentación' : 'crearon ' . (int) ($_GET['count'] ?? 0) . ' presentaciones' ?> correctamente.
        <?php else: ?>
            Producto guardado correctamente.
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">
        <?php
        $productErrors = [
            'size' => 'La imagen supera el tamaño máximo permitido (5 MB).',
            'type' => 'El archivo debe ser una imagen válida (JPG, PNG, GIF o WEBP).',
            'upload' => 'Ocurrió un error al subir la imagen. Intenta de nuevo.',
        ];
        echo $productErrors[$_GET['error']] ?? 'No se pudo guardar el producto. Revisa los datos e intenta de nuevo.';
        ?>
    </div>
<?php endif; ?>

<?php if (!$tenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado todavía. Ejecuta <code>database/seed.php</code> o crea un tenant primero.</div>
<?php else: ?>

<?php if (!$editing): ?>
<div class="modal fade" id="createProductChoiceModal" tabindex="-1" aria-labelledby="createProductChoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createProductChoiceModalLabel">¿Este producto viene en varias tallas, colores o presentaciones?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-secondary text-start py-3" id="chooseSimpleProductBtn">
                    <div class="fw-semibold">No, es un solo producto</div>
                    <div class="small text-secondary">Un formulario simple, con un único precio y stock.</div>
                </button>
                <button type="button" class="btn btn-outline-primary text-start py-3" id="chooseVariantProductBtn">
                    <div class="fw-semibold">Sí, viene en varias</div>
                    <div class="small text-secondary">Ej: Royal Canin Mini Adulto en 1kg, 3kg, 7.5kg, 15kg.</div>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" id="simpleProductCard" <?= $editing ? '' : 'hidden' ?>>
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3"><?= $editing ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <form method="POST" action="<?= BASE_URL ?>/process/product_process.php" enctype="multipart/form-data" class="row g-3">
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
                <input type="number" step="0.001" min="0" id="stock_quantity" name="stock_quantity" class="form-control" value="<?= htmlspecialchars((string) ($editing['stock_quantity'] ?? '0')) ?>">
            </div>
            <div class="col-md-2">
                <label for="sale_unit" class="form-label">Se vende por</label>
                <select id="sale_unit" name="sale_unit" class="form-select">
                    <option value="unit" <?= ($editing['sale_unit'] ?? 'unit') === 'unit' ? 'selected' : '' ?>>Unidad</option>
                    <option value="weight" <?= ($editing['sale_unit'] ?? 'unit') === 'weight' ? 'selected' : '' ?>>Peso / fracción (ej. kg)</option>
                </select>
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
            <div class="col-md-4">
                <label for="brand" class="form-label">Marca</label>
                <input type="text" id="brand" name="brand" class="form-control" list="brand-suggestions" placeholder="Ej: Royal Canin" value="<?= htmlspecialchars($editing['brand'] ?? '') ?>">
                <datalist id="brand-suggestions">
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= htmlspecialchars($brand) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-8">
                <label for="imagen" class="form-label">Foto del producto</label>
                <div class="d-flex align-items-center gap-3">
                    <?php $currentImage = $editing ? Product::imageUrl($editing) : null; ?>
                    <?php if ($currentImage): ?>
                        <img src="<?= htmlspecialchars($currentImage) ?>" alt="" class="rounded border" style="width:56px;height:56px;object-fit:cover;flex-shrink:0;">
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <input type="file" id="imagen" name="imagen" class="form-control" accept="image/*">
                        <?php if ($currentImage): ?>
                            <div class="form-check mt-1">
                                <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image" value="1">
                                <label class="form-check-label small text-secondary" for="remove_image">Quitar imagen actual</label>
                            </div>
                        <?php else: ?>
                            <div class="form-text">JPG, PNG, GIF o WEBP. Máximo 5 MB.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Descripción corta</label>
                <textarea id="description" name="description" class="form-control" rows="2"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4"><?= $editing ? 'Guardar cambios' : 'Agregar producto' ?></button>
                <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/productos.php" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="cancelSimpleProductBtn">Cancelar</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!$editing): ?>
<div class="card border-0 shadow-sm mb-4" id="variantProductCard" hidden>
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3">Nuevo producto con variantes</h2>
        <form method="POST" action="<?= BASE_URL ?>/process/product_variants_process.php" enctype="multipart/form-data" id="variantProductForm">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="variant_general_name" class="form-label">Nombre general</label>
                    <input type="text" id="variant_general_name" name="name" class="form-control" placeholder="Ej: Royal Canin Mini Adulto" required>
                </div>
                <div class="col-md-6">
                    <label for="variant_category" class="form-label">Categoría</label>
                    <input type="text" id="variant_category" name="category" class="form-control" list="category-suggestions" placeholder="Ej: Alimentos, Accesorios">
                </div>
                <div class="col-md-6">
                    <label for="variant_imagen" class="form-label">Imagen principal</label>
                    <input type="file" id="variant_imagen" name="imagen" class="form-control" accept="image/*">
                    <div class="form-text">Se usa para todas las presentaciones. JPG, PNG, GIF o WEBP. Máximo 5 MB.</div>
                </div>
                <div class="col-md-6">
                    <label for="variant_description" class="form-label">Descripción</label>
                    <textarea id="variant_description" name="description" class="form-control" rows="1"></textarea>
                </div>
            </div>

            <label class="form-label">Tipo de variante</label>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary variant-type-chip" data-type="weight">+ Presentación / Peso</button>
                <button type="button" class="btn btn-sm btn-outline-primary variant-type-chip" data-type="flavor">+ Sabor</button>
                <button type="button" class="btn btn-sm btn-outline-primary variant-type-chip" data-type="size">+ Talla / Tamaño</button>
                <button type="button" class="btn btn-sm btn-outline-primary variant-type-chip" data-type="color">+ Color</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-type-chip" data-type="custom">+ Crear otra característica</button>
            </div>

            <div id="weightPresetRow" class="d-none flex-wrap align-items-center gap-2 mb-3">
                <span class="small text-secondary">Agregar presentación:</span>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="Suelto">Suelto</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="1kg">1kg</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="3kg">3kg</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="7.5kg">7.5kg</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="15kg">15kg</button>
            </div>

            <div id="customVariantRow" class="d-none align-items-center gap-2 mb-3">
                <input type="text" id="customVariantInput" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Ej: Rojo, Grande, Pollo...">
                <button type="button" class="btn btn-sm btn-primary" id="addCustomVariantBtn">Agregar</button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-sm" id="variantTable">
                    <thead>
                        <tr>
                            <th>Nombre de la variante</th>
                            <th>Precio</th>
                            <th>Costo</th>
                            <th>Stock inicial</th>
                            <th>SKU / código de barras</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="variantTableBody"></tbody>
                </table>
                <p id="variantTableEmpty" class="text-secondary text-center py-3">Elegí un tipo de variante arriba para empezar a agregar presentaciones.</p>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary rounded-pill px-4" id="submitVariantProductBtn" disabled>Guardar producto con variantes</button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" id="cancelVariantProductBtn">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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
                <th>Foto</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th>Categoría</th>
                <th>Marca</th>
                <th class="text-end">Precio</th>
                <th class="text-end">Costo</th>
                <th class="text-end">Stock</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody id="product-table-body">
            <?php foreach ($products as $product): ?>
                <tr data-name="<?= htmlspecialchars(mb_strtolower($product['name'])) ?>" data-sku="<?= htmlspecialchars(mb_strtolower($product['sku'] ?? '')) ?>">
                    <td>
                        <?php $thumb = Product::imageUrl($product); ?>
                        <?php if ($thumb): ?>
                            <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="rounded border" style="width:40px;height:40px;object-fit:cover;">
                        <?php else: ?>
                            <span class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-secondary" style="width:40px;height:40px;">
                                <i class="bi bi-image"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($product['category'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($product['brand'] ?? '—') ?></td>
                    <td class="text-end">$<?= number_format((float) $product['price'], 2) ?></td>
                    <td class="text-end">$<?= number_format((float) $product['cost'], 2) ?></td>
                    <td class="text-end">
                        <span class="badge <?= (float) $product['stock_quantity'] <= 5 ? 'bg-danger' : 'bg-success-subtle text-success-emphasis' ?>">
                            <?= Product::formatQuantity($product['stock_quantity']) ?>
                        </span>
                    </td>
                    <td><?= ($product['sale_unit'] ?? 'unit') === 'weight' ? 'Peso' : 'Unidad' ?></td>
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
                <tr><td colspan="11" class="text-center text-secondary py-4">No hay productos registrados todavía.</td></tr>
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

<?php if (!$editing): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const openBtn = document.getElementById('openCreateProductBtn');
    const choiceModalEl = document.getElementById('createProductChoiceModal');
    const choiceModal = new bootstrap.Modal(choiceModalEl);
    const simpleCard = document.getElementById('simpleProductCard');
    const variantCard = document.getElementById('variantProductCard');

    openBtn.addEventListener('click', () => choiceModal.show());

    document.getElementById('chooseSimpleProductBtn').addEventListener('click', () => {
        choiceModal.hide();
        variantCard.hidden = true;
        simpleCard.hidden = false;
        simpleCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('chooseVariantProductBtn').addEventListener('click', () => {
        choiceModal.hide();
        simpleCard.hidden = true;
        variantCard.hidden = false;
        variantCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    document.getElementById('cancelSimpleProductBtn').addEventListener('click', () => {
        simpleCard.hidden = true;
    });

    document.getElementById('cancelVariantProductBtn').addEventListener('click', () => {
        variantCard.hidden = true;
    });

    // --- Formulario de producto con variantes ---
    const weightPresetRow = document.getElementById('weightPresetRow');
    const customVariantRow = document.getElementById('customVariantRow');
    const customVariantInput = document.getElementById('customVariantInput');
    const variantTableBody = document.getElementById('variantTableBody');
    const variantTableEmpty = document.getElementById('variantTableEmpty');
    const submitVariantBtn = document.getElementById('submitVariantProductBtn');
    let variantRowCount = 0;

    function updateVariantTableState() {
        const hasRows = variantTableBody.children.length > 0;
        variantTableEmpty.hidden = hasRows;
        submitVariantBtn.disabled = !hasRows;
    }

    function addVariantRow(prefillName) {
        variantRowCount++;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="variant_name[]" class="form-control form-control-sm" value="${prefillName ? prefillName.replace(/"/g, '&quot;') : ''}" placeholder="Ej: 15 kilos" required></td>
            <td><input type="number" step="0.01" min="0" name="variant_price[]" class="form-control form-control-sm" placeholder="0.00" required style="max-width: 110px;"></td>
            <td><input type="number" step="0.01" min="0" name="variant_cost[]" class="form-control form-control-sm" placeholder="0.00" style="max-width: 110px;"></td>
            <td><input type="number" step="0.001" min="0" name="variant_stock[]" class="form-control form-control-sm" value="0" style="max-width: 100px;"></td>
            <td><input type="text" name="variant_sku[]" class="form-control form-control-sm" placeholder="Opcional"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-variant-row" aria-label="Quitar"><i class="bi bi-trash"></i></button></td>
        `;
        row.querySelector('.remove-variant-row').addEventListener('click', () => {
            row.remove();
            updateVariantTableState();
        });
        variantTableBody.appendChild(row);
        updateVariantTableState();
        row.querySelector('input[name="variant_price[]"]').focus();
    }

    function toggleRow(el, show) {
        el.classList.toggle('d-none', !show);
        el.classList.toggle('d-flex', show);
    }

    document.querySelectorAll('.variant-type-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            const type = chip.dataset.type;
            toggleRow(weightPresetRow, type === 'weight');
            toggleRow(customVariantRow, type !== 'weight');
            if (type !== 'weight') {
                customVariantInput.value = '';
                customVariantInput.placeholder = {
                    flavor: 'Ej: Pollo, Carne, Salmón...',
                    size: 'Ej: Chico, Mediano, Grande...',
                    color: 'Ej: Negro, Blanco, Rojo...',
                    custom: 'Ej: Rojo, Grande, Pollo...',
                }[type] || 'Nombre de la variante';
                customVariantInput.focus();
            }
        });
    });

    document.querySelectorAll('.variant-preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => addVariantRow(btn.dataset.label));
    });

    document.getElementById('addCustomVariantBtn').addEventListener('click', () => {
        const label = customVariantInput.value.trim();
        if (!label) return;
        addVariantRow(label);
        customVariantInput.value = '';
        customVariantInput.focus();
    });

    customVariantInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('addCustomVariantBtn').click();
        }
    });
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
