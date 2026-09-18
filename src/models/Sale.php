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

    /**
     * Returns the sale's public receipt token, generating and persisting
     * one on first use (existing sales were created before this feature) —
     * same pattern as Tenant::getOrCreatePublicToken().
     */
    public static function getOrCreatePublicToken(int $id): string
    {
        $sale = self::find($id);
        if ($sale && !empty($sale['public_token'])) {
            return $sale['public_token'];
        }

        $token = bin2hex(random_bytes(16));
        self::db()->prepare('UPDATE sales SET public_token = :token WHERE id = :id')
            ->execute(['token' => $token, 'id' => $id]);

        return $token;
    }

    /**
     * Looks up a sale by its public receipt token, for the no-login
     * public/remito.php page. Joined with the seller and tenant info the
     * receipt needs to display, since that page has no session/tenant
     * context of its own — the unguessable token is what authorizes access,
     * the same trust model as Tenant::findByPublicToken() for the catalog.
     */
    public static function findByPublicToken(string $token): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.name AS seller_name, t.name AS tenant_name, t.address AS tenant_address,
                    t.phone AS tenant_phone, t.tax_id AS tenant_tax_id
             FROM sales s
             JOIN users u ON u.id = s.user_id
             JOIN tenants t ON t.id = s.tenant_id
             WHERE s.public_token = :token'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function itemsFor(int $saleId): array
    {
        $stmt = self::db()->prepare(
            'SELECT si.*, p.name AS product_name, p.cost AS product_cost, p.sale_unit AS product_sale_unit,
                    p.imagen AS product_imagen, p.image_url AS product_image_url
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

    /**
     * Anula una venta y devuelve al stock la cantidad de cada producto
     * vendido. Es idempotente: si la venta ya estaba anulada, no vuelve a
     * restituir el stock (evita duplicar la restitución con un doble click
     * o una petición repetida).
     */
    public static function cancelAndRestoreStock(int $tenantId, int $id): void
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $sale = self::findForTenant($id, $tenantId);
            if (!$sale) {
                throw new RuntimeException('Venta no encontrada.');
            }

            if ($sale['status'] !== 'cancelled') {
                foreach (self::itemsFor($id) as $item) {
                    Product::adjustStock((int) $item['product_id'], (float) $item['quantity']);
                }
                $db->prepare("UPDATE sales SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Reemplaza los ítems de una venta activa por una nueva lista: restaura
     * el stock de los ítems viejos, valida que haya suficiente stock para
     * los nuevos (contando lo que esta misma edición libera), inserta los
     * ítems nuevos y recalcula el total. El descuento existente se
     * mantiene, recortado si ya no cabe en el nuevo subtotal.
     *
     * @param array<int, array{product_id:int, quantity:float, unit_price:float}> $newItems
     */
    public static function updateItems(int $tenantId, int $id, array $newItems): void
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $sale = self::findForTenant($id, $tenantId);
            if (!$sale || $sale['status'] !== 'completed') {
                throw new RuntimeException('Venta no encontrada o ya anulada.');
            }

            $oldItems = self::itemsFor($id);
            $restoredQuantity = [];
            foreach ($oldItems as $item) {
                $pid = (int) $item['product_id'];
                $restoredQuantity[$pid] = ($restoredQuantity[$pid] ?? 0) + (float) $item['quantity'];
            }

            foreach ($newItems as $item) {
                $product = Product::findForTenant($item['product_id'], $tenantId);
                if (!$product) {
                    throw new RuntimeException('Uno de los productos ya no existe.');
                }
                $available = (float) $product['stock_quantity'] + ($restoredQuantity[$item['product_id']] ?? 0);
                if ($item['quantity'] > $available) {
                    throw new RuntimeException('No hay suficiente stock de "' . $product['name'] . '".');
                }
            }

            foreach ($oldItems as $item) {
                Product::adjustStock((int) $item['product_id'], (float) $item['quantity']);
            }
            $db->prepare('DELETE FROM sale_items WHERE sale_id = :sale_id')->execute(['sale_id' => $id]);

            $subtotal = 0.0;
            $itemStmt = $db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal)
                 VALUES (:sale_id, :product_id, :quantity, :unit_price, :subtotal)'
            );
            foreach ($newItems as $item) {
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $itemSubtotal;
                $itemStmt->execute([
                    'sale_id' => $id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal,
                ]);
                Product::adjustStock($item['product_id'], -$item['quantity']);
            }

            $discountAmount = min((float) $sale['discount_amount'], $subtotal);
            $total = $subtotal - $discountAmount;

            $db->prepare('UPDATE sales SET total = :total, discount_amount = :discount_amount WHERE id = :id')
                ->execute(['total' => $total, 'discount_amount' => $discountAmount, 'id' => $id]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
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
