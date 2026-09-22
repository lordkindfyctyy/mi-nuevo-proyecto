function initApp() {
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
}

// Este script se carga al final de <body>, así que en la mayoría de los
// casos el DOM ya está listo y "DOMContentLoaded" ya disparó antes de que
// este listener llegue a registrarse (algunas extensiones del navegador
// retrasan la ejecución del script lo suficiente como para que esto pase
// incluso en páginas simples) — sin este chequeo, el evento nunca vuelve a
// dispararse y toda la inicialización queda muerta.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
