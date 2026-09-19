<?php

require_once __DIR__ . '/../../config/database.php';

class ContactMessage
{
    private static function db(): PDO
    {
        return getConnection();
    }

    public static function create(string $name, ?string $email, string $message, string $source = 'contact_form'): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO contact_messages (name, email, message, source) VALUES (:name, :email, :message, :source)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'source' => $source,
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
    }

    public static function countUnread(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM contact_messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function markRead(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE contact_messages SET status = 'read' WHERE id = :id");

        return $stmt->execute(['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM contact_messages WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
