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
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#importProductsModal">
                <i class="bi bi-file-earmark-excel"></i> Importar Excel
            </button>
            <button type="button" class="btn btn-primary rounded-pill px-4" id="openCreateProductBtn">
                <i class="bi bi-plus-lg"></i> Crear producto
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php if ($_GET['success'] === 'deleted'): ?>
            Producto eliminado.
        <?php elseif ($_GET['success'] === 'variants'): ?>
            Se <?= ((int) ($_GET['count'] ?? 0)) === 1 ? 'creó 1 presentación' : 'crearon ' . (int) ($_GET['count'] ?? 0) . ' presentaciones' ?> correctamente.
        <?php elseif ($_GET['success'] === 'import'): ?>
            Se importaron <?= (int) ($_GET['count'] ?? 0) ?> producto<?= ((int) ($_GET['count'] ?? 0)) === 1 ? '' : 's' ?> desde el Excel.
            <?php if ((int) ($_GET['skipped'] ?? 0) > 0): ?>
                Se omitieron <?= (int) $_GET['skipped'] ?> fila<?= ((int) $_GET['skipped']) === 1 ? '' : 's' ?> con errores.
            <?php endif; ?>
        <?php else: ?>
            Producto guardado correctamente.
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['import_error'])): ?>
    <div class="alert alert-danger">
        <?php
        $importErrors = [
            'no_file' => 'Elegí un archivo .xlsx antes de importar.',
            'bad_format' => 'No pudimos leer ese archivo. Asegurate de que sea un .xlsx válido (podés usar la plantilla que descargaste).',
            'empty' => 'El archivo no tiene filas de datos para importar.',
            'all_failed' => 'Ninguna fila se pudo importar. Revisá que "Nombre" y "Precio" estén completos y que el precio sea un número.',
        ];
        echo $importErrors[$_GET['import_error']] ?? 'No se pudo importar el archivo. Intentá de nuevo.';
        ?>
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

<div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/process/product_import_process.php" enctype="multipart/form-data" id="importProductsForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="importProductsModalLabel"><i class="bi bi-file-earmark-excel me-2"></i>Importar productos desde Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">
                        Subí un archivo <strong>.xlsx</strong> con una fila por producto. La primera fila tiene que
                        ser el encabezado, igual que en la plantilla.
                    </p>
                    <p class="mb-3">
                        <a href="<?= BASE_URL ?>/product_import_template.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download"></i> Descargar plantilla de ejemplo
                        </a>
                    </p>
                    <div class="mb-3">
                        <label for="import_file" class="form-label">Archivo Excel (.xlsx)</label>
                        <input type="file" id="import_file" name="import_file" class="form-control" accept=".xlsx" required>
                    </div>
                    <div class="small text-secondary">
                        Columnas: <strong>Nombre</strong> y <strong>Precio</strong> son obligatorias. SKU, Categoría,
                        Marca, Costo, Stock y Descripción son opcionales. Las filas sin Nombre o Precio válido se
                        omiten (no frenan el resto de la importación).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" id="simpleProductCard" <?= $editing ? '' : 'hidden' ?>>
    <div class="card-body">
        <h2 class="h5 fw-semibold mb-3"><?= $editing ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <form method="POST" action="<?= BASE_URL ?>/process/product_process.php" enctype="multipart/form-data" class="row g-3" id="simpleProductForm">
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
                            <div class="form-text">JPG, PNG, GIF o WEBP. Se optimiza sola antes de subirla, aunque sea una foto pesada de celular.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Descripción corta</label>
                <textarea id="description" name="description" class="form-control" rows="2"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="show_in_catalog" name="show_in_catalog" value="1" <?= ($editing === null || !empty($editing['show_in_catalog'])) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="show_in_catalog">Mostrar en el catálogo online</label>
                    <div class="form-text">Desactivalo si es un producto que solo vendés por mostrador y no querés que tus clientes lo vean en el catálogo.</div>
                </div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Las fotos que salen directo de la cámara de un celular pueden pesar
    // varios MB: subirlas así de pesadas es lo que hacía sentir "trabada"
    // la pantalla en conexiones lentas. Las comprimimos en el navegador
    // antes de enviarlas, así el POST real pesa una fracción de eso.
    window.setupImageCompression = function setupImageCompression(inputId, maxDimension = 1600, quality = 0.82) {
        const input = document.getElementById(inputId);
        if (!input) return;

        input.addEventListener('change', async () => {
            const file = input.files[0];
            if (!file || !file.type.startsWith('image/') || file.type === 'image/gif') return;
            if (file.size <= 350 * 1024) return; // ya es chica, no vale la pena tocarla

            try {
                const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                let { width, height } = bitmap;
                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = Math.round(height * (maxDimension / width));
                        width = maxDimension;
                    } else {
                        width = Math.round(width * (maxDimension / height));
                        height = maxDimension;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height);
                bitmap.close();

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (blob && blob.size < file.size) {
                    const compressed = new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    input.files = dt.files;
                }
            } catch (e) {
                // Si el navegador no soporta algo acá, seguimos con el archivo
                // original tal cual lo eligió el usuario: no rompemos el flujo.
                console.warn('No se pudo optimizar la imagen, se sube tal cual.', e);
            }
        });
    };

    // Feedback claro mientras se sube el formulario, para que no parezca
    // trabado (y no se tiente a salir de la pantalla a mitad de la subida).
    window.setupFormLoadingState = function setupFormLoadingState(formId, loadingText) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + loadingText;
        });
    };

    setupImageCompression('imagen');
    setupFormLoadingState('simpleProductForm', 'Guardando...');
});
</script>

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
                    <div class="form-text">Se usa para todas las presentaciones. JPG, PNG, GIF o WEBP. Se optimiza sola antes de subirla.</div>
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
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="20kg">20kg</button>
                <button type="button" class="btn btn-sm btn-outline-secondary variant-preset-btn" data-label="22kg">22kg</button>
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

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="variant_show_in_catalog" name="show_in_catalog" value="1" checked>
                <label class="form-check-label" for="variant_show_in_catalog">Mostrar en el catálogo online</label>
                <div class="form-text">Aplica a todas las presentaciones. Desactivalo si son productos que solo vendés por mostrador.</div>
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
                <th class="text-end">Ganancia</th>
                <th class="text-end">Stock</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Catálogo</th>
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
                    <?php
                        $profitAmount = (float) $product['price'] - (float) $product['cost'];
                        $profitMargin = (float) $product['price'] > 0 ? ($profitAmount / (float) $product['price']) * 100 : 0;
                    ?>
                    <td class="text-end">
                        <span class="<?= $profitAmount < 0 ? 'text-danger' : 'text-success' ?> fw-semibold">$<?= number_format($profitAmount, 2) ?></span>
                        <div class="text-secondary small"><?= number_format($profitMargin, 0) ?>%</div>
                    </td>
                    <td class="text-end">
                        <span class="badge <?= (float) $product['stock_quantity'] <= 5 ? 'bg-danger' : 'bg-success-subtle text-success-emphasis' ?>">
                            <?= Product::formatQuantity($product['stock_quantity']) ?>
                        </span>
                    </td>
                    <td><?= ($product['sale_unit'] ?? 'unit') === 'weight' ? 'Peso' : 'Unidad' ?></td>
                    <td><?= $product['status'] === 'active' ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <?php if (!empty($product['show_in_catalog'])): ?>
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-eye"></i> Visible</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-eye-slash"></i> Solo mostrador</span>
                        <?php endif; ?>
                    </td>
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
                <tr><td colspan="13" class="text-center text-secondary py-4">No hay productos registrados todavía.</td></tr>
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
    setupImageCompression('variant_imagen');
    setupFormLoadingState('variantProductForm', 'Guardando...');
    setupFormLoadingState('importProductsForm', 'Importando...');

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
