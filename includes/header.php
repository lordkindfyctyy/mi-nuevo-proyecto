<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/tenant_context.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
            <div class="container">
                <a class="navbar-brand brand-logo" href="<?= BASE_URL ?>/index.php">
                    <span class="brand-mark" aria-hidden="true">6&amp;7</span>
                    <span class="brand-word"><?= APP_NAME ?></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Alternar navegación">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <?php $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/index.php">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'productos.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/productos.php">Productos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'vender.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/vender.php">Vender</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'ventas.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/ventas.php">Ventas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'clientes.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/clientes.php">Clientes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'proveedores.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/proveedores.php">Proveedores</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($currentPage, ['compras.php', 'comprar.php'], true) ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/compras.php">Compras</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'reportes.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/reportes.php">Reportes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'about.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/about.php">Acerca de</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'contact.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/contact.php">Contacto</a>
                        </li>
                    </ul>
                    <?php if (isLoggedIn()): ?>
                        <span class="navbar-text text-secondary small me-lg-3">
                            <?= htmlspecialchars(currentUserName() ?? '') ?>
                            <?php if (currentTenantName()): ?> · <?= htmlspecialchars(currentTenantName()) ?><?php endif; ?>
                        </span>
                        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-secondary rounded-pill px-4">Cerrar sesión</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary rounded-pill px-4 ms-lg-3">Iniciar sesión</a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary rounded-pill px-4 ms-lg-2">Regístrate</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    <main class="container py-4">
