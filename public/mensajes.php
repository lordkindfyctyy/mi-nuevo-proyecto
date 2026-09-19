<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/ContactMessage.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$catalogConversations = ContactMessage::conversationsBySource('catalog_chat', $tenantId);
$supportConversations = ContactMessage::conversationsBySource('live_chat');
$contactMessages = ContactMessage::allBySource('contact_form');

function renderConversationsTable(array $conversations, string $source): void
{
    ?>
    <div class="table-responsive mb-5">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th></th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Celular</th>
                    <th>Último mensaje</th>
                    <th>Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conv): ?>
                    <tr class="<?= $conv['unread_count'] > 0 ? 'fw-semibold' : '' ?>">
                        <td>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="badge rounded-pill text-bg-primary"><?= (int) $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($conv['name']) ?></td>
                        <td><?= htmlspecialchars($conv['email']) ?></td>
                        <td><?= htmlspecialchars($conv['phone'] ?? '—') ?></td>
                        <td style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= $conv['last_sender'] === 'admin' ? '<span class="text-secondary">Vos:</span> ' : '' ?><?= htmlspecialchars($conv['last_message']) ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($conv['last_created_at']))) ?></td>
                        <td class="text-end">
                            <a href="<?= BASE_URL ?>/mensaje_chat.php?source=<?= urlencode($source) ?>&email=<?= urlencode($conv['email']) ?>" class="btn btn-sm btn-primary">Abrir chat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$conversations): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No hay conversaciones todavía.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Mensajes recibidos</h1>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= $_GET['success'] === 'deleted' ? 'Mensaje eliminado.' : 'Mensaje marcado como leído.' ?></div>
<?php endif; ?>

<h2 class="h5 fw-semibold mb-3"><i class="bi bi-chat-dots-fill"></i> Chat del catálogo (ventas)</h2>
<?php renderConversationsTable($catalogConversations, 'catalog_chat'); ?>

<h2 class="h5 fw-semibold mb-3"><i class="bi bi-headset"></i> Soporte técnico (Asistente SixSeven)</h2>
<?php renderConversationsTable($supportConversations, 'live_chat'); ?>

<h2 class="h5 fw-semibold mb-3">Formulario de contacto</h2>
<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Estado</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Mensaje</th>
                <th>Fecha</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contactMessages as $msg): ?>
                <tr class="<?= $msg['status'] === 'unread' ? 'fw-semibold' : '' ?>">
                    <td>
                        <?php if ($msg['status'] === 'unread'): ?>
                            <span class="badge text-bg-primary">No leído</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Leído</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($msg['name']) ?></td>
                    <td>
                        <?php if (!empty($msg['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>
                        <?php else: ?>
                            <span class="text-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 360px; white-space: pre-wrap;"><?= htmlspecialchars($msg['message']) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at']))) ?></td>
                    <td class="text-end">
                        <?php if ($msg['status'] === 'unread'): ?>
                            <form method="POST" action="<?= BASE_URL ?>/process/contact_message_process.php" class="d-inline">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Marcar leído</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= BASE_URL ?>/process/contact_message_process.php" class="d-inline" onsubmit="return confirm('¿Eliminar este mensaje?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$contactMessages): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">No hay mensajes recibidos todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
