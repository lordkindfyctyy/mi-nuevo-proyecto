<?php
require_once __DIR__ . '/../includes/tenant_context.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$errors = [
    'tenant_exists' => 'Ya existe un negocio registrado con ese correo.',
    'user_exists' => 'Ya existe una cuenta con ese correo electrónico.',
    'password_mismatch' => 'Las contraseñas no coinciden.',
    'password_short' => 'La contraseña debe tener al menos 6 caracteres.',
    'invalid' => 'Revisa los datos del formulario e intenta de nuevo.',
];
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-4 text-center">Crea tu negocio</h1>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?= $errors[$_GET['error']] ?? $errors['invalid'] ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/process/register_process.php">
                    <h2 class="h6 fw-semibold text-secondary text-uppercase mb-3">Tu negocio</h2>
                    <div class="mb-3">
                        <label for="business_name" class="form-label">Nombre del negocio</label>
                        <input type="text" id="business_name" name="business_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>">
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="business_email" class="form-label">Correo del negocio</label>
                            <input type="email" id="business_email" name="business_email" class="form-control" required
                                   value="<?= htmlspecialchars($_POST['business_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="business_phone" class="form-label">Teléfono (opcional)</label>
                            <input type="text" id="business_phone" name="business_phone" class="form-control"
                                   value="<?= htmlspecialchars($_POST['business_phone'] ?? '') ?>">
                        </div>
                    </div>

                    <h2 class="h6 fw-semibold text-secondary text-uppercase mb-3">Tu cuenta</h2>
                    <div class="mb-3">
                        <label for="owner_name" class="form-label">Tu nombre</label>
                        <input type="text" id="owner_name" name="owner_name" class="form-control" required
                               value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="owner_email" class="form-label">Correo electrónico</label>
                        <input type="email" id="owner_email" name="owner_email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['owner_email'] ?? '') ?>">
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label">Confirmar contraseña</label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Crear cuenta</button>
                </form>

                <p class="text-center text-secondary small mt-3 mb-0">
                    ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/login.php">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
