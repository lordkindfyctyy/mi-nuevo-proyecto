<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Product.php';

class Purchase
{
    private static function db(): PDO
    {
        return getConnection();
    }

    /**
     * Registers a purchase from a supplier and increases stock (and updates
     * cost) for every product included, all inside a single transaction.
     *
     * @param array<int, array{product_id:int, quantity:int, unit_cost:float}> $items
     */
    public static function create(int $tenantId, int $supplierId, int $userId, array $items): int
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['unit_cost'] * $item['quantity'];
            }

            $stmt = $db->prepare(
                'INSERT INTO purchases (tenant_id, supplier_id, user_id, total)
                 VALUES (:tenant_id, :supplier_id, :user_id, :total)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'supplier_id' => $supplierId,
                'user_id' => $userId,
                'total' => $total,
            ]);
            $purchaseId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, subtotal)
                 VALUES (:purchase_id, :product_id, :quantity, :unit_cost, :subtotal)'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'purchase_id' => $purchaseId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $item['unit_cost'] * $item['quantity'],
                ]);

                Product::adjustStock($item['product_id'], $item['quantity']);
                Product::update($item['product_id'], ['cost' => $item['unit_cost']]);
            }

            $db->commit();

            return $purchaseId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Like find(), but scoped to a tenant so one negocio can never fetch
     * another negocio's purchase by guessing/tampering with its id.
     */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM purchases WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function itemsFor(int $purchaseId): array
    {
        $stmt = self::db()->prepare(
            'SELECT pi.*, p.name AS product_name
             FROM purchase_items pi
             JOIN products p ON p.id = pi.product_id
             WHERE pi.purchase_id = :purchase_id'
        );
        $stmt->execute(['purchase_id' => $purchaseId]);

        return $stmt->fetchAll();
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare(
            'SELECT pu.*, u.name AS buyer_name, s.name AS supplier_name
             FROM purchases pu
             JOIN users u ON u.id = pu.user_id
             JOIN suppliers s ON s.id = pu.supplier_id
             WHERE pu.tenant_id = :tenant_id
             ORDER BY pu.created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }
}
