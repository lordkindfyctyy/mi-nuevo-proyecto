<?php

require_once __DIR__ . '/../../config/database.php';

class Product
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(int $tenantId, string $name, float $price, array $data = []): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO products (tenant_id, name, sku, description, category, brand, image_url, imagen, price, cost, sale_unit, stock_quantity, show_in_catalog)
             VALUES (:tenant_id, :name, :sku, :description, :category, :brand, :image_url, :imagen, :price, :cost, :sale_unit, :stock_quantity, :show_in_catalog)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'brand' => $data['brand'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'imagen' => $data['imagen'] ?? null,
            'price' => $price,
            'cost' => $data['cost'] ?? 0,
            'sale_unit' => ($data['sale_unit'] ?? 'unit') === 'weight' ? 'weight' : 'unit',
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'show_in_catalog' => array_key_exists('show_in_catalog', $data) ? (int) (bool) $data['show_in_catalog'] : 1,
        ]);

        return (int) self::db()->lastInsertId();
    }

    /**
     * Nombre base de un producto, sacándole el peso/presentación del final
     * si lo tiene (ej. "Royal Canin Mini Adulto 3k" -> "Royal Canin Mini
     * Adulto"). Es la misma regla con la que Vender y el catálogo agrupan
     * presentaciones por nombre — así un producto nuevo creado como
     * "{base} {presentación}" cae en el mismo grupo que el original.
     */
    public static function baseName(string $name): string
    {
        $trimmed = trim($name);
        $pattern = '/^(.*?)[\s-]+((?:x\s*)?\d+(?:[.,]\d+)?\s*(?:x\s*\d+(?:[.,]\d+)?\s*)?(?:kgs?|kilos?|k|grs?|gramos?|g|mls?|ml|lts?|litros?|l)\.?|suelto|a\s*granel)$/i';

        if (preg_match($pattern, $trimmed, $matches) && mb_strlen(trim($matches[1])) >= 3) {
            return trim($matches[1]);
        }

        return $trimmed;
    }

    /**
     * "Suelto"/"a granel" son las únicas presentaciones que se venden
     * fraccionadas (por peso); el resto son paquetes/bolsas cerradas que
     * solo se venden en unidades enteras.
     */
    public static function saleUnitForVariantLabel(string $label): string
    {
        return preg_match('/^\s*(suelto|a\s*granel)\s*$/i', $label) ? 'weight' : 'unit';
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Like find(), but scoped to a tenant so one negocio can never fetch
     * another negocio's product by guessing/tampering with its id.
     */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM products WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM products WHERE tenant_id = :tenant_id ORDER BY name');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'sku', 'description', 'category', 'brand', 'image_url', 'imagen', 'price', 'cost', 'sale_unit', 'stock_quantity', 'status', 'show_in_catalog'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $field === 'sale_unit' ? (($data[$field] ?? 'unit') === 'weight' ? 'weight' : 'unit') : $data[$field];
                $fields[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function adjustStock(int $id, int|float $delta): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE products SET stock_quantity = stock_quantity + :delta WHERE id = :id'
        );

        return $stmt->execute(['delta' => $delta, 'id' => $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM products WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Resolves the display URL for a product's photo: an uploaded file
     * (`imagen`, stored relative to /public) takes priority over a legacy
     * external `image_url`. Returns null when neither is set.
     */
    public static function imageUrl(array $product): ?string
    {
        if (!empty($product['imagen'])) {
            return BASE_URL . '/' . ltrim($product['imagen'], '/');
        }

        if (!empty($product['image_url'])) {
            return $product['image_url'];
        }

        return null;
    }

    /**
     * Formats a quantity (stock, sale quantity, etc.) for display, using a
     * comma as decimal separator and trimming trailing zeros so a
     * unit-based "50.000" shows as "50" while a weight-based "0.250"
     * shows as "0,25".
     */
    public static function formatQuantity(int|float|string $qty): string
    {
        $formatted = number_format((float) $qty, 3, ',', '.');
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, ',');

        return $formatted === '' ? '0' : $formatted;
    }
}
