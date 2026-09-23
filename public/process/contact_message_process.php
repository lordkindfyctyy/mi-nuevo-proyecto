<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
require_once __DIR__ . '/../../src/models/User.php';
requireLogin();

$currentUser = User::find(currentUserId());
if (empty($currentUser['is_support_admin'])) {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$message = ContactMessage::find($id);

// Este endpoint es solo para el formulario de contacto público (no tiene
// tenant_id ni email de sesión propio para verificar dueño de otra forma);
// nos aseguramos de que el id realmente sea uno de esos, nunca un mensaje
// de catalog_chat/live_chat de otro negocio.
if ($message && $message['source'] === 'contact_form') {
    if ($action === 'mark_read') {
        ContactMessage::markRead($id);
        header('Location: ' . BASE_URL . '/mensajes.php?success=read');
        exit;
    }

    if ($action === 'delete') {
        ContactMessage::delete($id);
        header('Location: ' . BASE_URL . '/mensajes.php?success=deleted');
        exit;
    }
}

header('Location: ' . BASE_URL . '/mensajes.php');
exit;
