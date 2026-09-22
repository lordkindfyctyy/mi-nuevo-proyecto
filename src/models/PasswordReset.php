<?php

require_once __DIR__ . '/../../config/database.php';

class PasswordReset
{
    private const TTL_SECONDS = 3600;

    private static function db(): PDO
    {
        return getConnection();
    }

    /**
     * Genera un token de un solo uso para el usuario, invalidando cualquier
     * token sin usar que ya existiera. Devuelve el token en texto plano
     * (solo se guarda su hash en la base) para incluirlo en el enlace del
     * mail.
     */
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        $stmt = self::db()->prepare('DELETE FROM password_resets WHERE user_id = :user_id AND used_at IS NULL');
        $stmt->execute(['user_id' => $userId]);

        $stmt = self::db()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);

        return $token;
    }

    public static function findValidByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $stmt = self::db()->prepare(
            'SELECT * FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function markUsed(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
