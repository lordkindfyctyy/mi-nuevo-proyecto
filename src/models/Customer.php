<?php

require_once __DIR__ . '/../../config/database.php';

class Customer
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(int $tenantId, string $name, array $data = []): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO customers (tenant_id, name, phone, email, address, notes)
             VALUES (:tenant_id, :name, :phone, :email, :address, :notes)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int) self::db()->lastInsertId();
    }

    /**
     * Like find(), but scoped to a tenant so one negocio can never fetch
     * another negocio's customer by guessing/tampering with its id.
     */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM customers WHERE tenant_id = :tenant_id ORDER BY name');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'phone', 'email', 'address', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM customers WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
