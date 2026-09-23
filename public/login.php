<?php
require_once __DIR__ . '/../includes/tenant_context.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row align-items-center g-4 g-lg-5 auth-page">
    <div class="col-lg-6 auth-hero">
        <span class="auth-hero-eyebrow"><?= APP_NAME ?></span>
        <h1 class="auth-hero-title">¡Bienvenido de nuevo!</h1>
        <p class="auth-hero-subtitle">Entrá a tu cuenta y seguí vendiendo con todo bajo control: inventario, ventas y clientes en un solo lugar.</p>
        <p class="mb-0">
            <span class="fw-semibold d-block mb-2">¿Todavía no tenés cuenta?</span>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-primary rounded-pill auth-cta-btn">Probar gratis</a>
        </p>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <h2 class="h4 fw-bold mb-4 text-center">Iniciar sesión</h2>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">Correo o contraseña incorrectos.</div>
                <?php endif; ?>
                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success">Sesión cerrada correctamente.</div>
                <?php endif; ?>
                <?php if (isset($_GET['reset'])): ?>
                    <div class="alert alert-success">Contraseña actualizada. Ya podés iniciar sesión.</div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/process/login_process.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <p class="text-end mb-3">
                        <a href="<?= BASE_URL ?>/forgot_password.php" class="small">¿Olvidaste tu usuario o contraseña?</a>
                    </p>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Entrar</button>
                </form>

                <p class="text-center text-secondary small mt-3 mb-0">
                    ¿No tienes cuenta? <a href="<?= BASE_URL ?>/register.php">Crea tu negocio</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
