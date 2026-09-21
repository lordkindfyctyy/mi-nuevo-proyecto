<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/ContactMessage.php';
requireLogin();

$source = $_GET['source'] ?? 'live_chat';

if (!in_array($source, ['live_chat', 'catalog_chat'], true)) {
    $source = 'live_chat';
}

// catalog_chat conversations are keyed by an anonymous per-browser token (no
// email is collected); live_chat (tech support) is still keyed by email and
// is global, not scoped to a tenant.
$keyedByToken = $source === 'catalog_chat';
$key = trim($keyedByToken ? ($_GET['token'] ?? '') : ($_GET['email'] ?? ''));
$scopeTenantId = $keyedByToken ? currentTenantId() : null;

if ($key === '') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

if ($keyedByToken) {
    ContactMessage::markConversationReadByToken($source, $key, $scopeTenantId);
    $messages = ContactMessage::conversationByToken($source, $key, $scopeTenantId);
} else {
    ContactMessage::markConversationRead($source, $key, $scopeTenantId);
    $messages = ContactMessage::conversationByEmail($source, $key, $scopeTenantId);
}

require_once __DIR__ . '/../includes/header.php';

// Use the visitor's own latest message for the header's name/phone, not
// whichever message came last (which could be the admin's own reply).
$visitorMessages = array_values(array_filter($messages, fn ($m) => $m['sender'] === 'visitor'));
$latest = $visitorMessages ? end($visitorMessages) : null;
$title = $keyedByToken ? 'Chat de catálogo con' : 'Chat de soporte con';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?= $title ?> <?= htmlspecialchars($latest['name'] ?? ($keyedByToken ? 'Cliente del catálogo' : $key)) ?></h1>
        <p class="text-secondary mb-0">
            <?php if ($keyedByToken): ?>
                <?= htmlspecialchars($latest['phone'] ?? 'Sin teléfono') ?>
            <?php else: ?>
                <?= htmlspecialchars($key) ?>
                <?php if (!empty($latest['phone'])): ?> · <?= htmlspecialchars($latest['phone']) ?><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/mensajes.php" class="btn btn-outline-secondary rounded-pill px-4">Volver</a>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">No se pudo enviar la respuesta. Intentá de nuevo.</div>
<?php endif; ?>

<?php if ($keyedByToken && !$messages && !$scopeTenantId): ?>
    <div class="alert alert-warning">No hay ningún negocio registrado en tu sesión todavía.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="chat-thread mb-3">
            <?php foreach ($messages as $msg): ?>
                <div class="chat-bubble chat-bubble-<?= $msg['sender'] ?>">
                    <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <time><?= htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at']))) ?></time>
                </div>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
                <p class="text-secondary text-center py-4 mb-0">No hay mensajes en esta conversación.</p>
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/process/contact_reply_process.php" id="chatReplyForm">
            <input type="hidden" name="<?= $keyedByToken ? 'token' : 'email' ?>" value="<?= htmlspecialchars($key) ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
            <textarea name="message" id="chatReplyMessage" rows="3" class="form-control mb-2" placeholder="Escribí tu respuesta..." required></textarea>
            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Responder</button>
        </form>
    </div>
</div>

<style>
.chat-thread { display: flex; flex-direction: column; gap: .6rem; max-height: 55vh; overflow-y: auto; }
.chat-bubble { max-width: 70%; padding: .6rem .9rem; border-radius: 1rem; }
.chat-bubble time { display: block; margin-top: .25rem; font-size: .75rem; opacity: .7; }
.chat-bubble-visitor { align-self: flex-start; background: var(--color-bg); border: 1px solid var(--color-border); border-bottom-left-radius: .25rem; }
.chat-bubble-admin { align-self: flex-end; background: var(--color-primary); color: #fff; border-bottom-right-radius: .25rem; }
</style>

<script>
document.getElementById('chatReplyMessage').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('chatReplyForm').requestSubmit();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
