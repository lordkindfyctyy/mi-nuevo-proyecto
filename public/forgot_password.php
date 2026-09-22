<?php
require_once __DIR__ . '/../includes/tenant_context.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/vender.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$errors = [
    'empty' => 'Ingresá tu correo o tu teléfono registrado.',
];
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold mb-2 text-center">Recuperar acceso</h1>
                <p class="text-secondary small text-center mb-4">
                    Ingresá el correo o el teléfono con el que te registraste. Si coincide con una cuenta, te
                    enviamos un correo con tu nombre de usuario y un enlace para elegir una nueva contraseña.
                </p>

                <?php if (isset($_GET['sent'])): ?>
                    <div class="alert alert-success">
                        Si los datos coinciden con una cuenta registrada, te enviamos un correo con las
                        instrucciones. Revisá también la carpeta de spam.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><?= $errors[$_GET['error']] ?? $errors['empty'] ?></div>
                <?php endif; ?>

                <?php if (!isset($_GET['sent'])): ?>
                <form method="POST" action="<?= BASE_URL ?>/process/forgot_password_process.php">
                    <div class="mb-3">
                        <label for="identifier" class="form-label">Correo electrónico o teléfono</label>
                        <input type="text" id="identifier" name="identifier" class="form-control" required autofocus
                               placeholder="ejemplo@correo.com o tu teléfono">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Enviar instrucciones</button>
                </form>
                <?php endif; ?>

                <p class="text-center text-secondary small mt-3 mb-0">
                    <a href="<?= BASE_URL ?>/login.php">Volver a iniciar sesión</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
