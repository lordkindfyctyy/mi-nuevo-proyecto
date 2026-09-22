<?php
require_once __DIR__ . '/tenant_context.php';
require_once __DIR__ . '/../src/models/Tenant.php';
require_once __DIR__ . '/../src/models/User.php';

$settingsTenantId = currentTenantId();
$settingsUserId = currentUserId();
$settingsTenant = $settingsTenantId ? Tenant::find($settingsTenantId) : null;
$settingsUser = $settingsUserId ? User::find($settingsUserId) : null;
$settingsRedirectPath = parse_url($_SERVER['REQUEST_URI'] ?? '/vender.php', PHP_URL_PATH) ?: '/vender.php';
?>
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel"><i class="bi bi-gear me-2"></i>Ajustes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/process/settings_process.php">
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($settingsRedirectPath) ?>">
                <div class="modal-body">
                    <?php if (isset($_GET['settings_error'])): ?>
                        <div class="alert alert-danger py-2 small mb-3">
                            <?php
                            $settingsErrorMessages = [
                                'password_current' => 'La contraseña actual no es correcta.',
                                'password_short' => 'La nueva contraseña debe tener al menos 6 caracteres.',
                                'password_mismatch' => 'La confirmación de la nueva contraseña no coincide.',
                            ];
                            echo $settingsErrorMessages[$_GET['settings_error']]
                                ?? 'No se pudieron guardar los cambios. Verificá que el nombre del comercio y tu nombre de usuario no estén vacíos.';
                            ?>
                        </div>
                    <?php elseif (isset($_GET['settings_success'])): ?>
                        <div class="alert alert-success py-2 small mb-3">Ajustes guardados correctamente.</div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="settings-tenant-name" class="form-label">Nombre del comercio</label>
                        <input type="text" id="settings-tenant-name" name="tenant_name" class="form-control" required value="<?= htmlspecialchars($settingsTenant['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="settings-tenant-phone" class="form-label">Teléfono de contacto</label>
                        <input type="text" id="settings-tenant-phone" name="tenant_phone" class="form-control" value="<?= htmlspecialchars($settingsTenant['phone'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="settings-tenant-address" class="form-label">Dirección</label>
                        <input type="text" id="settings-tenant-address" name="tenant_address" class="form-control" value="<?= htmlspecialchars($settingsTenant['address'] ?? '') ?>">
                        <div class="form-text">Aparece en el remito web y en el comprobante impreso.</div>
                    </div>
                    <div class="mb-3">
                        <label for="settings-tenant-tax-id" class="form-label">Identificación fiscal (CUIT, opcional)</label>
                        <input type="text" id="settings-tenant-tax-id" name="tenant_tax_id" class="form-control" value="<?= htmlspecialchars($settingsTenant['tax_id'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="settings-user-name" class="form-label">Mi nombre de usuario</label>
                        <input type="text" id="settings-user-name" name="user_name" class="form-control" required value="<?= htmlspecialchars($settingsUser['name'] ?? '') ?>">
                    </div>
                    <div class="mb-0">
                        <label for="settings-user-phone" class="form-label">Mi teléfono (para recuperar el acceso)</label>
                        <input type="text" id="settings-user-phone" name="user_phone" class="form-control" value="<?= htmlspecialchars($settingsUser['phone'] ?? '') ?>">
                    </div>

                    <hr class="my-4">

                    <h2 class="h6 fw-semibold mb-3">Cambiar contraseña</h2>
                    <p class="text-secondary small mb-3">Dejá estos campos vacíos si no querés cambiar tu contraseña.</p>
                    <div class="mb-3">
                        <label for="settings-current-password" class="form-label">Contraseña actual</label>
                        <div class="input-group">
                            <input type="password" id="settings-current-password" name="current_password" class="form-control" autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="settings-new-password" class="form-label">Nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" id="settings-new-password" name="new_password" class="form-control" minlength="6" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="settings-new-password-confirm" class="form-label">Confirmar nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" id="settings-new-password-confirm" name="new_password_confirm" class="form-control" minlength="6" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary password-toggle-btn" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('settingsModal');
    if (!modalEl || !window.bootstrap) return;
    const params = new URLSearchParams(window.location.search);
    if (params.has('settings_success') || params.has('settings_error')) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
});
</script>
