<?php

require_once __DIR__ . '/../../config/database.php';

class ContactMessage
{
    private static function db(): PDO
    {
        return getConnection();
    }

    /**
     * @param array{name:string,email?:?string,phone?:?string,message:string,source?:string,sender?:string,widget_token?:?string} $data
     */
    public static function create(array $data): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO contact_messages (name, email, phone, message, source, sender, widget_token)
             VALUES (:name, :email, :phone, :message, :source, :sender, :widget_token)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
            'source' => $data['source'] ?? 'contact_form',
            'sender' => $data['sender'] ?? 'visitor',
            'widget_token' => $data['widget_token'] ?? null,
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
    }

    public static function allBySource(string $source): array
    {
        $stmt = self::db()->prepare('SELECT * FROM contact_messages WHERE source = :source ORDER BY created_at DESC');
        $stmt->execute(['source' => $source]);

        return $stmt->fetchAll();
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

    /**
     * Full live-chat conversation for one email, oldest first.
     */
    public static function conversationByEmail(string $email): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM contact_messages
             WHERE source = 'live_chat' AND email = :email
             ORDER BY created_at ASC"
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetchAll();
    }

    /**
     * Only the visitor messages newer than $afterId, for lightweight polling.
     */
    public static function conversationByEmailAfter(string $email, int $afterId): array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM contact_messages
             WHERE source = 'live_chat' AND email = :email AND id > :after_id
             ORDER BY created_at ASC"
        );
        $stmt->execute(['email' => $email, 'after_id' => $afterId]);

        return $stmt->fetchAll();
    }

    public static function countByEmail(string $email): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM contact_messages WHERE source = 'live_chat' AND email = :email");
        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * True if a visitor message from this email exists with this widget_token,
     * i.e. the caller actually owns this conversation.
     */
    public static function tokenMatchesEmail(string $email, string $token): bool
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM contact_messages
             WHERE source = 'live_chat' AND email = :email AND widget_token = :token AND sender = 'visitor'"
        );
        $stmt->execute(['email' => $email, 'token' => $token]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function markConversationRead(string $email): bool
    {
        $stmt = self::db()->prepare(
            "UPDATE contact_messages SET status = 'read'
             WHERE source = 'live_chat' AND email = :email AND sender = 'visitor'"
        );

        return $stmt->execute(['email' => $email]);
    }

    /**
     * One row per live-chat conversation (grouped by email), newest first,
     * for the admin inbox list.
     */
    public static function liveChatConversations(): array
    {
        return self::db()->query(
            "SELECT
                cm.email,
                (SELECT name FROM contact_messages WHERE source = 'live_chat' AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS name,
                (SELECT phone FROM contact_messages WHERE source = 'live_chat' AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS phone,
                (SELECT message FROM contact_messages WHERE source = 'live_chat' AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS last_message,
                (SELECT sender FROM contact_messages WHERE source = 'live_chat' AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS last_sender,
                MAX(cm.created_at) AS last_created_at,
                SUM(CASE WHEN cm.sender = 'visitor' AND cm.status = 'unread' THEN 1 ELSE 0 END) AS unread_count
             FROM contact_messages cm
             WHERE cm.source = 'live_chat' AND cm.email IS NOT NULL AND cm.email <> ''
             GROUP BY cm.email
             ORDER BY last_created_at DESC"
        )->fetchAll();
    }
}
