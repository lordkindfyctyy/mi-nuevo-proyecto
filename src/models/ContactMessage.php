<?php

require_once __DIR__ . '/../../config/database.php';

class ContactMessage
{
    private static function db(): PDO
    {
        return getConnection();
    }

    /**
     * @param array{tenant_id?:?int,name:string,email?:?string,phone?:?string,message:string,source?:string,sender?:string,widget_token?:?string} $data
     */
    public static function create(array $data): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO contact_messages (tenant_id, name, email, phone, message, source, sender, widget_token)
             VALUES (:tenant_id, :name, :email, :phone, :message, :source, :sender, :widget_token)'
        );
        $stmt->execute([
            'tenant_id' => $data['tenant_id'] ?? null,
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

    public static function countUnreadBySource(string $source, ?int $tenantId = null): int
    {
        [$where, $params] = self::whereClause($source, $tenantId);

        $stmt = self::db()->prepare("SELECT COUNT(*) FROM contact_messages WHERE $where AND sender = 'visitor' AND status = 'unread'");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
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

    private static function whereClause(string $source, ?int $tenantId): array
    {
        $where = 'source = :source';
        $params = ['source' => $source];

        if ($tenantId !== null) {
            $where .= ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        return [$where, $params];
    }

    /**
     * Full conversation for one email on one channel, oldest first.
     */
    public static function conversationByEmail(string $source, string $email, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['email'] = $email;

        $stmt = self::db()->prepare("SELECT * FROM contact_messages WHERE $where AND email = :email ORDER BY created_at ASC");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Only messages newer than $afterId, for lightweight polling.
     */
    public static function conversationByEmailAfter(string $source, string $email, int $afterId, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['email'] = $email;
        $params['after_id'] = $afterId;

        $stmt = self::db()->prepare("SELECT * FROM contact_messages WHERE $where AND email = :email AND id > :after_id ORDER BY created_at ASC");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Full conversation for one widget_token (the catalog chat's per-browser
     * identity, since it no longer collects email), oldest first.
     */
    public static function conversationByToken(string $source, string $token, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['token'] = $token;

        $stmt = self::db()->prepare("SELECT * FROM contact_messages WHERE $where AND widget_token = :token ORDER BY created_at ASC");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function conversationByTokenAfter(string $source, string $token, int $afterId, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['token'] = $token;
        $params['after_id'] = $afterId;

        $stmt = self::db()->prepare("SELECT * FROM contact_messages WHERE $where AND widget_token = :token AND id > :after_id ORDER BY created_at ASC");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function markConversationReadByToken(string $source, string $token, ?int $tenantId = null): bool
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['token'] = $token;

        $stmt = self::db()->prepare("UPDATE contact_messages SET status = 'read' WHERE $where AND widget_token = :token AND sender = 'visitor'");

        return $stmt->execute($params);
    }

    /**
     * One row per conversation (grouped by widget_token) on one channel,
     * newest first, for the admin inbox list.
     */
    public static function conversationsBySourceByToken(string $source, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $where .= " AND widget_token IS NOT NULL AND widget_token <> ''";

        $sql = "SELECT
                    cm.widget_token AS token,
                    (SELECT name FROM contact_messages WHERE $where AND widget_token = cm.widget_token AND sender = 'visitor' ORDER BY created_at DESC LIMIT 1) AS name,
                    (SELECT phone FROM contact_messages WHERE $where AND widget_token = cm.widget_token AND sender = 'visitor' ORDER BY created_at DESC LIMIT 1) AS phone,
                    (SELECT message FROM contact_messages WHERE $where AND widget_token = cm.widget_token ORDER BY created_at DESC LIMIT 1) AS last_message,
                    (SELECT sender FROM contact_messages WHERE $where AND widget_token = cm.widget_token ORDER BY created_at DESC LIMIT 1) AS last_sender,
                    MAX(cm.created_at) AS last_created_at,
                    SUM(CASE WHEN cm.sender = 'visitor' AND cm.status = 'unread' THEN 1 ELSE 0 END) AS unread_count
                 FROM contact_messages cm
                 WHERE $where
                 GROUP BY cm.widget_token
                 ORDER BY last_created_at DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function markConversationRead(string $source, string $email, ?int $tenantId = null): bool
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $params['email'] = $email;

        $stmt = self::db()->prepare("UPDATE contact_messages SET status = 'read' WHERE $where AND email = :email AND sender = 'visitor'");

        return $stmt->execute($params);
    }

    /**
     * One row per conversation (grouped by email) on one channel, newest
     * first, for the admin inbox list.
     */
    public static function conversationsBySource(string $source, ?int $tenantId = null): array
    {
        [$where, $params] = self::whereClause($source, $tenantId);
        $where .= " AND email IS NOT NULL AND email <> ''";

        $sql = "SELECT
                    cm.email,
                    (SELECT name FROM contact_messages WHERE $where AND email = cm.email AND sender = 'visitor' ORDER BY created_at DESC LIMIT 1) AS name,
                    (SELECT phone FROM contact_messages WHERE $where AND email = cm.email AND sender = 'visitor' ORDER BY created_at DESC LIMIT 1) AS phone,
                    (SELECT message FROM contact_messages WHERE $where AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS last_message,
                    (SELECT sender FROM contact_messages WHERE $where AND email = cm.email ORDER BY created_at DESC LIMIT 1) AS last_sender,
                    MAX(cm.created_at) AS last_created_at,
                    SUM(CASE WHEN cm.sender = 'visitor' AND cm.status = 'unread' THEN 1 ELSE 0 END) AS unread_count
                 FROM contact_messages cm
                 WHERE $where
                 GROUP BY cm.email
                 ORDER BY last_created_at DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
