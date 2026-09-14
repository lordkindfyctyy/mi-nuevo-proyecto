<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../src/models/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}

$businessName = trim($_POST['business_name'] ?? '');
$businessEmail = trim($_POST['business_email'] ?? '');
$businessPhone = trim($_POST['business_phone'] ?? '') ?: null;
$ownerName = trim($_POST['owner_name'] ?? '');
$ownerEmail = trim($_POST['owner_email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($businessName === '' || $ownerName === ''
    || !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)
    || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)
) {
    header('Location: ' . BASE_URL . '/register.php?error=invalid');
    exit;
}

if (strlen($password) < 6) {
    header('Location: ' . BASE_URL . '/register.php?error=password_short');
    exit;
}

if ($password !== $passwordConfirm) {
    header('Location: ' . BASE_URL . '/register.php?error=password_mismatch');
    exit;
}

if (Tenant::findByEmail($businessEmail)) {
    header('Location: ' . BASE_URL . '/register.php?error=tenant_exists');
    exit;
}

if (User::findByEmailGlobal($ownerEmail)) {
    header('Location: ' . BASE_URL . '/register.php?error=user_exists');
    exit;
}

$db = getConnection();
$db->beginTransaction();

try {
    $tenantId = Tenant::create($businessName, $businessEmail, $businessPhone);
    $userId = User::create($tenantId, $ownerName, $ownerEmail, $password, 'owner');
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    header('Location: ' . BASE_URL . '/register.php?error=invalid');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['tenant_id'] = $tenantId;
$_SESSION['user_name'] = $ownerName;
$_SESSION['tenant_name'] = $businessName;

header('Location: ' . BASE_URL . '/vender.php');
exit;
