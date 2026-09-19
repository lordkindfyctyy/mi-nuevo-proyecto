<div class="support-widget">
    <button type="button" id="supportWidgetToggle" class="support-widget-btn" aria-label="Abrir Asistente SixSeven" aria-expanded="false" aria-controls="supportWidgetPanel">
        <i class="bi bi-headset"></i>
    </button>

    <div id="supportWidgetPanel" class="support-widget-panel" hidden>
        <div class="support-widget-header">
            <div>
                <h2 class="h6 fw-bold mb-1">Asistente SixSeven</h2>
                <p class="support-widget-status mb-0"><span class="support-widget-dot"></span>Estamos en línea · Respondemos en minutos</p>
            </div>
            <button type="button" id="supportWidgetClose" class="btn-close btn-close-white" aria-label="Cerrar"></button>
        </div>
        <div class="support-widget-body">
            <p class="support-widget-greeting">¡Hola! 👋 ¿En qué te podemos ayudar hoy?</p>
            <form id="supportWidgetForm">
                <textarea id="supportWidgetMessage" name="message" rows="3" class="form-control mb-2" placeholder="Escribí tu consulta..." required></textarea>
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Enviar consulta</button>
            </form>
            <div id="supportWidgetFeedback" class="support-widget-feedback" hidden></div>
        </div>
    </div>
</div>

<script>
(function () {
    var toggle = document.getElementById('supportWidgetToggle');
    var panel = document.getElementById('supportWidgetPanel');
    var closeBtn = document.getElementById('supportWidgetClose');
    var form = document.getElementById('supportWidgetForm');
    var messageInput = document.getElementById('supportWidgetMessage');
    var feedback = document.getElementById('supportWidgetFeedback');

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        messageInput.focus();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    closeBtn.addEventListener('click', closePanel);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = messageInput.value.trim();
        if (!message) return;

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        var body = new URLSearchParams();
        body.set('message', message);

        fetch('<?= BASE_URL ?>/api/support_widget.php', { method: 'POST', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                feedback.hidden = false;
                if (data.ok) {
                    feedback.className = 'support-widget-feedback support-widget-feedback-success';
                    feedback.textContent = '¡Mensaje enviado! Te responderemos a la brevedad.';
                    form.reset();
                } else {
                    feedback.className = 'support-widget-feedback support-widget-feedback-error';
                    feedback.textContent = data.error || 'No se pudo enviar tu consulta. Intentá de nuevo.';
                }
            })
            .catch(function () {
                feedback.hidden = false;
                feedback.className = 'support-widget-feedback support-widget-feedback-error';
                feedback.textContent = 'No se pudo enviar tu consulta. Revisá tu conexión e intentá de nuevo.';
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
})();
</script>
