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
    public static function create(int $tenantId, int $userId, array $items, ?string $customerName = null, string $paymentMethod = 'cash', ?int $customerId = null, float $discountAmount = 0.0): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += $item['unit_price'] * $item['quantity'];
            }

            // El descuento nunca puede superar el subtotal ni ser negativo:
            // el total guardado siempre refleja el importe real cobrado, que
            // es lo que alimenta el balance de caja y los reportes.
            $discountAmount = max(0.0, min($discountAmount, $subtotal));
            $total = $subtotal - $discountAmount;

            $stmt = $db->prepare(
                'INSERT INTO sales (tenant_id, user_id, customer_id, customer_name, total, discount_amount, payment_method)
                 VALUES (:tenant_id, :user_id, :customer_id, :customer_name, :total, :discount_amount, :payment_method)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'total' => $total,
                'discount_amount' => $discountAmount,
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

    /**
     * Like allByTenant(), but scoped to a "YYYY-MM-DD HH:MM:SS" datetime
     * range (inclusive on both ends) for the Balance page's period filter.
     */
    public static function findByDateRangeForTenant(int $tenantId, string $start, string $end): array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.name AS seller_name
             FROM sales s
             JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = :tenant_id AND s.created_at BETWEEN :start AND :end
             ORDER BY s.created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'start' => $start, 'end' => $end]);

        return $stmt->fetchAll();
    }

    public static function cancel(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE sales SET status = 'cancelled' WHERE id = :id");

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Sale count and total spent per customer, for the "resumen de compras"
     * shown in the customers screen. Keyed by customer_id.
     *
     * @return array<int, array{count: int, total: float}>
     */
    public static function statsByCustomer(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            "SELECT customer_id, COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS total_spent
             FROM sales
             WHERE tenant_id = :tenant_id AND status = 'completed' AND customer_id IS NOT NULL
             GROUP BY customer_id"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        $stats = [];
        foreach ($stmt->fetchAll() as $row) {
            $stats[(int) $row['customer_id']] = [
                'count' => (int) $row['sales_count'],
                'total' => (float) $row['total_spent'],
            ];
        }

        return $stats;
    }

    /**
     * Like allByTenant(), but scoped to a single customer for the
     * per-customer purchase history.
     */
    public static function findByCustomerForTenant(int $customerId, int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.name AS seller_name
             FROM sales s
             JOIN users u ON u.id = s.user_id
             WHERE s.tenant_id = :tenant_id AND s.customer_id = :customer_id
             ORDER BY s.created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'customer_id' => $customerId]);

        return $stmt->fetchAll();
    }
}
