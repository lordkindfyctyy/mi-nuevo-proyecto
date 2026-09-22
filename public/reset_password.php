<?php
require_once __DIR__ . '/../includes/tenant_context.php';
require_once __DIR__ . '/../src/models/PasswordReset.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

$token = $_GET['token'] ?? '';
$reset = $token !== '' ? PasswordReset::findValidByToken($token) : null;

require_once __DIR__ . '/../includes/header.php';

$errors = [
    'password_short' => 'La contraseña debe tener al menos 6 caracteres.',
    'password_mismatch' => 'Las contraseñas no coinciden.',
];
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-4 text-center">Elegir nueva contraseña</h1>

                <?php if (!$reset): ?>
                    <div class="alert alert-danger">
                        Este enlace de recuperación no es válido o ya venció. Pedí uno nuevo.
                    </div>
                    <p class="text-center mb-0">
                        <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-primary rounded-pill px-4">Solicitar nuevo enlace</a>
                    </p>
                <?php else: ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= $errors[$_GET['error']] ?? 'Revisá los datos e intentá de nuevo.' ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/process/reset_password_process.php">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contraseña</label>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control" required minlength="6" autofocus>
                                <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Confirmar contraseña</label>
                            <div class="input-group">
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="6">
                                <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Guardar nueva contraseña</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
