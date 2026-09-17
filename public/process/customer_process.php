<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../src/models/Customer.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/clientes.php');
    exit;
}

$tenantId = currentTenantId();
$action = $_POST['action'] ?? '';

if (!$tenantId) {
    header('Location: ' . BASE_URL . '/clientes.php?error=1');
    exit;
}

try {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            header('Location: ' . BASE_URL . '/clientes.php?error=1');
            exit;
        }

        Customer::create($tenantId, $name, [
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ]);

        header('Location: ' . BASE_URL . '/clientes.php?success=created');
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $customer = Customer::findForTenant($id, $tenantId);

        if (!$customer) {
            header('Location: ' . BASE_URL . '/clientes.php?error=1');
            exit;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            header('Location: ' . BASE_URL . '/clientes.php?error=1');
            exit;
        }

        Customer::update($id, [
            'name' => $name,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
        ]);

        header('Location: ' . BASE_URL . '/clientes.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $customer = Customer::findForTenant($id, $tenantId);

        if ($customer) {
            Customer::delete($id);
        }

        header('Location: ' . BASE_URL . '/clientes.php?success=deleted');
        exit;
    }

    header('Location: ' . BASE_URL . '/clientes.php');
    exit;
} catch (Throwable $e) {
    header('Location: ' . BASE_URL . '/clientes.php?error=1');
    exit;
}
