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
            'INSERT INTO products (tenant_id, name, sku, description, category, image_url, imagen, price, cost, stock_quantity)
             VALUES (:tenant_id, :name, :sku, :description, :category, :image_url, :imagen, :price, :cost, :stock_quantity)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'imagen' => $data['imagen'] ?? null,
            'price' => $price,
            'cost' => $data['cost'] ?? 0,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
        ]);

        return (int) self::db()->lastInsertId();
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

        foreach (['name', 'sku', 'description', 'category', 'image_url', 'imagen', 'price', 'cost', 'stock_quantity', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function adjustStock(int $id, int $delta): bool
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
}
