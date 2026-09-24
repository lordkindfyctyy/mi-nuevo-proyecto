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

    public static function findByPublicToken(string $token): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM tenants WHERE public_token = :token AND status = 'active'");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Returns the tenant's public catalog token, generating and persisting
     * one on first use (existing tenants were created before this feature).
     */
    public static function getOrCreatePublicToken(int $tenantId): string
    {
        $tenant = self::find($tenantId);
        if ($tenant && !empty($tenant['public_token'])) {
            return $tenant['public_token'];
        }

        $token = bin2hex(random_bytes(16));
        self::update($tenantId, ['public_token' => $token]);

        return $token;
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM tenants ORDER BY name')->fetchAll();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'phone', 'address', 'tax_id', 'whatsapp_phone', 'ai_assistant_enabled', 'public_token', 'status'] as $field) {
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

    /**
     * Marca que el negocio pidió activar el asistente de IA (add-on pago),
     * para no repetirle el cartel de "ya lo pediste" cada vez que abre el
     * modal de compartir catálogo.
     */
    public static function markAiAssistantRequested(int $id): void
    {
        self::db()->prepare('UPDATE tenants SET ai_assistant_requested_at = NOW() WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM tenants WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
