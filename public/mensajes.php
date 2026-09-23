<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/ContactMessage.php';
require_once __DIR__ . '/../src/models/User.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$tenantId = currentTenantId();
$currentUser = User::find(currentUserId());
$currentUserEmail = $currentUser['email'] ?? '';
$catalogConversations = ContactMessage::conversationsBySourceByToken('catalog_chat', $tenantId);
// live_chat (soporte técnico) no tiene tenant_id: cada usuario solo ve su
// propia conversación con soporte, nunca la de otro negocio.
$supportConversations = $currentUserEmail !== ''
    ? ContactMessage::conversationsBySource('live_chat', null, $currentUserEmail)
    : [];

// catalog_chat conversations are keyed by an anonymous per-browser token (no
// email is collected anymore), so the merchant identifies them by name/phone
// and the "open chat" link carries that token; live_chat (tech support) is
// still keyed by the logged-in staff member's email.
function renderConversationsTable(array $conversations, string $source): void
{
    $keyedByToken = $source === 'catalog_chat';
    ?>
    <div class="table-responsive mb-5">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th></th>
                    <th>Nombre</th>
                    <?php if ($keyedByToken): ?>
                        <th>Celular / WhatsApp</th>
                    <?php else: ?>
                        <th>Email</th>
                        <th>Celular</th>
                    <?php endif; ?>
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
                        <?php if ($keyedByToken): ?>
                            <td><?= htmlspecialchars($conv['phone'] ?? 'Sin teléfono') ?></td>
                        <?php else: ?>
                            <td><?= htmlspecialchars($conv['email']) ?></td>
                            <td><?= htmlspecialchars($conv['phone'] ?? '—') ?></td>
                        <?php endif; ?>
                        <td style="max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= $conv['last_sender'] === 'admin' ? '<span class="text-secondary">Vos:</span> ' : '' ?><?= htmlspecialchars($conv['last_message']) ?>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($conv['last_created_at']))) ?></td>
                        <td class="text-end">
                            <?php $key = $keyedByToken ? 'token=' . urlencode($conv['token']) : 'email=' . urlencode($conv['email']); ?>
                            <a href="<?= BASE_URL ?>/mensaje_chat.php?source=<?= urlencode($source) ?>&<?= $key ?>" class="btn btn-sm btn-primary">Abrir chat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$conversations): ?>
                    <tr><td colspan="<?= $keyedByToken ? 6 : 7 ?>" class="text-center text-secondary py-4">No hay conversaciones todavía.</td></tr>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
