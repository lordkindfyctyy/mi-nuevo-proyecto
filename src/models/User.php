<?php

require_once __DIR__ . '/../../config/database.php';

class User
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(int $tenantId, string $name, string $email, string $password, string $role = 'employee'): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO users (tenant_id, name, email, password_hash, role)
             VALUES (:tenant_id, :name, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id AND email = :email');
        $stmt->execute(['tenant_id' => $tenantId, 'email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByEmailGlobal(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findByPhoneGlobal(string $phone): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE phone = :phone LIMIT 1');
        $stmt->execute(['phone' => $phone]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * The account(s) that see and answer every business's "Soporte
     * técnico" thread and the public contact form (users.is_support_admin).
     */
    public static function allSupportAdmins(): array
    {
        $stmt = self::db()->query("SELECT * FROM users WHERE is_support_admin = 1 AND status = 'active'");

        return $stmt->fetchAll();
    }

    public static function allByTenant(int $tenantId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE tenant_id = :tenant_id ORDER BY name');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function verifyPassword(array $user, string $password): bool
    {
        return password_verify($password, $user['password_hash']);
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'phone', 'role', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (array_key_exists('password', $data)) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($fields === []) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';

        return self::db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM users WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
