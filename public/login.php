<?php
require_once __DIR__ . '/../includes/tenant_context.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-4 text-center">Iniciar sesión</h1>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">Correo o contraseña incorrectos.</div>
                <?php endif; ?>
                <?php if (isset($_GET['logout'])): ?>
                    <div class="alert alert-success">Sesión cerrada correctamente.</div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/process/login_process.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
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
