<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/mensajes.php');
    exit;
}

$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if ($action === 'mark_read' && ContactMessage::find($id)) {
    ContactMessage::markRead($id);
    header('Location: ' . BASE_URL . '/mensajes.php?success=read');
    exit;
}

if ($action === 'delete' && ContactMessage::find($id)) {
    ContactMessage::delete($id);
    header('Location: ' . BASE_URL . '/mensajes.php?success=deleted');
    exit;
}

header('Location: ' . BASE_URL . '/mensajes.php');
exit;
