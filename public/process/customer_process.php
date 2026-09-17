<?php
require_once __DIR__ . '/../../includes/tenant_context.php';
require_once __DIR__ . '/../../includes/image_upload.php';
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

        $upload = process_uploaded_image('imagen', 'cust');
        if ($upload['error']) {
            header('Location: ' . BASE_URL . '/clientes.php?error=' . $upload['error']);
            exit;
        }

        Customer::create($tenantId, $name, [
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'imagen' => $upload['path'],
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

        $upload = process_uploaded_image('imagen', 'cust');
        if ($upload['error']) {
            header('Location: ' . BASE_URL . '/clientes.php?error=' . $upload['error']);
            exit;
        }

        $imagen = $customer['imagen'];
        if ($upload['provided'] && $upload['path']) {
            delete_uploaded_image_file($customer['imagen']);
            $imagen = $upload['path'];
        } elseif (isset($_POST['remove_image'])) {
            delete_uploaded_image_file($customer['imagen']);
            $imagen = null;
        }

        Customer::update($id, [
            'name' => $name,
            'phone' => trim($_POST['phone'] ?? '') ?: null,
            'email' => trim($_POST['email'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'imagen' => $imagen,
        ]);

        header('Location: ' . BASE_URL . '/clientes.php?success=updated');
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $customer = Customer::findForTenant($id, $tenantId);

        if ($customer) {
            delete_uploaded_image_file($customer['imagen']);
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
