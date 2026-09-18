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
$userName = trim($_POST['user_name'] ?? '');

if ($tenantName === '' || $userName === '') {
    header('Location: ' . BASE_URL . $redirectPath . '?settings_error=1');
    exit;
}

try {
    Tenant::update($tenantId, [
        'name' => $tenantName,
        'phone' => $tenantPhone !== '' ? $tenantPhone : null,
    ]);
    User::update($userId, ['name' => $userName]);

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
