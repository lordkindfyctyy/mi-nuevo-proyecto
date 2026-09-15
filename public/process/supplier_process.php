<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Supplier.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/proveedores.php');
    exit;
}

$tenantId = currentTenantId();
$action = $_POST['action'] ?? '';

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/proveedores.php?error=1');
    exit;
}

try {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            header('Location: ' . BASE_URL . '/proveedores.php?error=1');
            exit;
        }

        Supplier::create($tenantId, $name, [
            'contact_name' => trim($_POST['contact_name'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
        ]);

        header('Location: ' . BASE_URL . '/proveedores.php?success=created');
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $supplier = Supplier::findForTenant($id, $tenantId);

        if (!$supplier) {
            header('Location: ' . BASE_URL . '/proveedores.php?error=1');
            exit;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            header('Location: ' . BASE_URL . '/proveedores.php?error=1');
            exit;
        }

        Supplier::update($id, [
            'name' => $name,
            'contact_name' => trim($_POST['contact_name'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
        ]);

        header('Location: ' . BASE_URL . '/proveedores.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $supplier = Supplier::findForTenant($id, $tenantId);

        if ($supplier) {
            Supplier::delete($id);
        }

        header('Location: ' . BASE_URL . '/proveedores.php?success=deleted');
        exit;
    }

    header('Location: ' . BASE_URL . '/proveedores.php');
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/proveedores.php?error=1');
    exit;
}
