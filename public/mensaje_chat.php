<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/ContactMessage.php';
requireLogin();

$email = trim($_GET['email'] ?? '');

if ($email === '') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

ContactMessage::markConversationRead($email);
$messages = ContactMessage::conversationByEmail($email);

require_once __DIR__ . '/../includes/header.php';

$latest = $messages ? end($messages) : null;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Chat con <?= htmlspecialchars($latest['name'] ?? $email) ?></h1>
        <p class="text-secondary mb-0">
            <?= htmlspecialchars($email) ?>
            <?php if (!empty($latest['phone'])): ?> · <?= htmlspecialchars($latest['phone']) ?><?php endif; ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/mensajes.php" class="btn btn-outline-secondary rounded-pill px-4">Volver</a>
</div>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger">No se pudo enviar la respuesta. Intentá de nuevo.</div>
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

        <form method="POST" action="<?= BASE_URL ?>/process/contact_reply_process.php">
            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
            <textarea name="message" rows="3" class="form-control mb-2" placeholder="Escribí tu respuesta..." required></textarea>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
