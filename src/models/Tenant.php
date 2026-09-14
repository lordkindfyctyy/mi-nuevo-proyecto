<?php

require_once __DIR__ . '/../../config/database.php';

class Tenant
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(string $name, string $email, ?string $phone = null): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO tenants (name, email, phone) VALUES (:name, :email, :phone)'
        );
        $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone]);

        return (int) self::db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM tenants WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM tenants ORDER BY name')->fetchAll();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'phone', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE tenants SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM tenants WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
