<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../includes/debug_log.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/PasswordReset.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$reset = $token !== '' ? PasswordReset::findValidByToken($token) : null;

if (!$reset) {
    header('Location: ' . BASE_URL . '/forgot_password.php?error=empty');
    exit;
}

if (strlen($password) < 6) {
    header('Location: ' . BASE_URL . '/reset_password.php?token=' . urlencode($token) . '&error=password_short');
    exit;
}

if ($password !== $passwordConfirm) {
    header('Location: ' . BASE_URL . '/reset_password.php?token=' . urlencode($token) . '&error=password_mismatch');
    exit;
}

try {
    User::update((int) $reset['user_id'], ['password' => $password]);
    PasswordReset::markUsed((int) $reset['id']);
} catch (Throwable $e) {
    app_debug_log('[reset_password] ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/reset_password.php?token=' . urlencode($token) . '&error=save_failed');
    exit;
}

header('Location: ' . BASE_URL . '/login.php?reset=success');
exit;
