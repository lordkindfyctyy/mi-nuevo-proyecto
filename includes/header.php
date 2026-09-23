<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/tenant_context.php';

$unreadMessages = 0;
if (isLoggedIn()) {
    require_once __DIR__ . '/../src/models/ContactMessage.php';
    require_once __DIR__ . '/../src/models/User.php';
    $headerCurrentUser = User::find(currentUserId());
    $unreadMessages = ContactMessage::countUnread(
        currentTenantId(),
        $headerCurrentUser['email'] ?? null,
        !empty($headerCurrentUser['is_support_admin'])
    );
}
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../public/assets/css/style.css') ?: time() ?>">
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
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $currentPage === 'mensajes.php' ? 'active fw-semibold' : '' ?>" href="<?= BASE_URL ?>/mensajes.php">
                                    Mensajes
                                    <?php if ($unreadMessages > 0): ?>
                                        <span class="badge rounded-pill text-bg-primary"><?= $unreadMessages ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (isLoggedIn()): ?>
                        <div class="navbar-text text-center me-lg-3 lh-sm">
                            <?php if (currentTenantName()): ?>
                                <div class="fw-bold text-dark tenant-name-display"><?= htmlspecialchars(currentTenantName()) ?></div>
                            <?php endif; ?>
                            <div class="text-secondary small"><?= htmlspecialchars(currentUserName() ?? '') ?></div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary rounded-circle settings-gear-btn me-lg-2" data-bs-toggle="modal" data-bs-target="#settingsModal" title="Ajustes" aria-label="Ajustes">
                            <i class="bi bi-gear"></i>
                        </button>
                        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-secondary rounded-pill px-4">Cerrar sesión</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary rounded-pill px-4 ms-lg-3">Iniciar sesión</a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary rounded-pill px-4 ms-lg-2">Regístrate</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    <?php if (isLoggedIn()): ?>
        <?php require_once __DIR__ . '/settings_modal.php'; ?>
    <?php endif; ?>
    <main class="container py-4">
