<?php

require_once __DIR__ . '/../../config/database.php';

class Supplier
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(int $tenantId, string $name, array $data = []): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO suppliers (tenant_id, name, contact_name, email, phone, address)
             VALUES (:tenant_id, :name, :contact_name, :email, :phone, :address)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return (int) self::db()->lastInsertId();
    }

    /**
     * Like find(), but scoped to a tenant so one negocio can never fetch
     * another negocio's supplier by guessing/tampering with its id.
     */
    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM suppliers WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM suppliers WHERE tenant_id = :tenant_id ORDER BY name');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'contact_name', 'email', 'phone', 'address', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE suppliers SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM suppliers WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
