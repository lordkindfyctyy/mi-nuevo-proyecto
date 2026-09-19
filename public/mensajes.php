<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/ContactMessage.php';
requireLogin();
require_once __DIR__ . '/../includes/header.php';

$messages = ContactMessage::all();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold mb-0">Mensajes recibidos</h1>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success"><?= $_GET['success'] === 'deleted' ? 'Mensaje eliminado.' : 'Mensaje marcado como leído.' ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Estado</th>
                <th>Origen</th>
                <th>Nombre</th>
                <th>Email</th>
                <th>Mensaje</th>
                <th>Fecha</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($messages as $msg): ?>
                <tr class="<?= $msg['status'] === 'unread' ? 'fw-semibold' : '' ?>">
                    <td>
                        <?php if ($msg['status'] === 'unread'): ?>
                            <span class="badge text-bg-primary">No leído</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Leído</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($msg['source'] ?? 'contact_form') === 'live_chat'): ?>
                            <span class="badge text-bg-success"><i class="bi bi-headset"></i> Soporte en vivo</span>
                        <?php else: ?>
                            <span class="badge text-bg-light border">Formulario web</span>
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
            <?php if (!$messages): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No hay mensajes recibidos todavía.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
