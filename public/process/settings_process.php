<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Tenant.php';
require_once __DIR__ . '/../../src/models/User.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

$redirectPath = $_POST['redirect_to'] ?? '/vender.php';
if (!is_string($redirectPath) || $redirectPath === '' || $redirectPath[0] !== '/') {
    $redirectPath = '/vender.php';
}

$tenantId = currentTenantId();
$userId = currentUserId();

if (!$tenantId || !$userId) {
    header('Location: ' . BASE_URL . $redirectPath . '?settings_error=1');
    exit;
}

$tenantName = trim($_POST['tenant_name'] ?? '');
$tenantPhone = trim($_POST['tenant_phone'] ?? '');
$tenantAddress = trim($_POST['tenant_address'] ?? '');
$tenantTaxId = trim($_POST['tenant_tax_id'] ?? '');
$userName = trim($_POST['user_name'] ?? '');
$userPhone = trim($_POST['user_phone'] ?? '');

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
$wantsPasswordChange = $currentPassword !== '' || $newPassword !== '' || $newPasswordConfirm !== '';

if ($tenantName === '' || $userName === '') {
    header('Location: ' . BASE_URL . $redirectPath . '?settings_error=1');
    exit;
}

if ($wantsPasswordChange) {
    $user = User::find($userId);
    if (!$user || !User::verifyPassword($user, $currentPassword)) {
        header('Location: ' . BASE_URL . $redirectPath . '?settings_error=password_current');
        exit;
    }
    if (strlen($newPassword) < 6) {
        header('Location: ' . BASE_URL . $redirectPath . '?settings_error=password_short');
        exit;
    }
    if ($newPassword !== $newPasswordConfirm) {
        header('Location: ' . BASE_URL . $redirectPath . '?settings_error=password_mismatch');
        exit;
    }
}

try {
    Tenant::update($tenantId, [
        'name' => $tenantName,
        'phone' => $tenantPhone !== '' ? $tenantPhone : null,
        'address' => $tenantAddress !== '' ? $tenantAddress : null,
        'tax_id' => $tenantTaxId !== '' ? $tenantTaxId : null,
    ]);

    $userUpdate = ['name' => $userName, 'phone' => $userPhone !== '' ? $userPhone : null];
    if ($wantsPasswordChange) {
        $userUpdate['password'] = $newPassword;
    }
    User::update($userId, $userUpdate);

    // El sidebar/navbar muestran el nombre del comercio y del usuario desde
    // la sesión (no los vuelven a leer de la base en cada request), así que
    // hay que refrescarlos acá para que el cambio se vea sin re-loguearse.
    $_SESSION['tenant_name'] = $tenantName;
    $_SESSION['user_name'] = $userName;

    header('Location: ' . BASE_URL . $redirectPath . '?settings_success=1');
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . $redirectPath . '?settings_error=1');
    exit;
}
