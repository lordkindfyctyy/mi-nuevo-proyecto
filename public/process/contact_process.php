<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/models/ContactMessage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/contact.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_URL . '/contact.php?error=1');
    exit;
}

try {
    ContactMessage::create($name, $email, $message);
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/contact.php?error=1');
    exit;
}

header('Location: ' . BASE_URL . '/contact.php?success=1');
exit;
