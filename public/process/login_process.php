<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Tenant.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$user = User::findByEmailGlobal($email);

if (!$user || $user['status'] !== 'active' || !User::verifyPassword($user, $password)) {
    header('Location: ' . BASE_URL . '/login.php?error=1');
    exit;
}

$tenant = Tenant::find((int) $user['tenant_id']);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['tenant_id'] = (int) $user['tenant_id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['tenant_name'] = $tenant['name'] ?? '';

header('Location: ' . BASE_URL . '/vender.php');
exit;
