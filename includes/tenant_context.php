<?php

require_once __DIR__ . '/../config/database.php';

function currentTenantId(): ?int
{
    return isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null;
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

function currentTenantName(): ?string
{
    return $_SESSION['tenant_name'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUserId() !== null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
