<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Product.php';

class Sale
{
    private static function db(): PDO
    {
        return getConnection();
    }

    /**
     * @param array<int, array{product_id:int, quantity:int, unit_price:float}> $items
     */
    public static function create(int $tenantId, int $userId, array $items, ?string $customerName = null, string $paymentMethod = 'cash'): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['unit_price'] * $item['quantity'];
            }

            $stmt = $db->prepare(
                'INSERT INTO sales (tenant_id, user_id, customer_name, total, payment_method)
                 VALUES (:tenant_id, :user_id, :customer_name, :total, :payment_method)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_name' => $customerName,
                'total' => $total,
                'payment_method' => $paymentMethod,
            ]);
            $saleId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
                 VALUES (:sale_id, :product_id, :quantity, :unit_price, :subtotal)'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'sale_id' => $saleId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['unit_price'] * $item['quantity'],
                ]);

                Product::adjustStock($item['product_id'], -$item['quantity']);
            }

            $db->commit();

            return $saleId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM sales WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Like find(), but scoped to a tenant so one negocio can never fetch
     * another negocio's sale by guessing/tampering with its id.
     */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM sales WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function itemsFor(int $saleId): array
    {
        $stmt = self::db()->prepare(
            'SELECT si.*, p.name AS product_name
             FROM sale_items si
             JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = :sale_id'
        );
        $stmt->execute(['sale_id' => $saleId]);

        return $stmt->fetchAll();
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.name AS seller_name
             FROM sales s
             JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = :tenant_id
             ORDER BY s.created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function cancel(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE sales SET status = 'cancelled' WHERE id = :id");

        return $stmt->execute(['id' => $id]);
    }
}
