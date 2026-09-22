document.addEventListener('DOMContentLoaded', () => {
    console.log('Sistema web iniciado.');

    // Ícono de "ojito" para mostrar/ocultar contraseña: se aplica a
    // cualquier <button class="password-toggle-btn"> ubicado justo después
    // del <input> que controla, dentro de un .input-group (login, registro,
    // ajustes, restablecer contraseña).
    document.querySelectorAll('.password-toggle-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = btn.previousElementSibling;
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const icon = btn.querySelector('i');
            if (icon) icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
            btn.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
});
