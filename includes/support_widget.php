<div class="support-widget">
    <button type="button" id="supportWidgetToggle" class="support-widget-btn" aria-label="Abrir Asistente SixSeven" aria-expanded="false" aria-controls="supportWidgetPanel">
        <i class="bi bi-headset"></i>
    </button>

    <div id="supportWidgetPanel" class="support-widget-panel" hidden>
        <div class="support-widget-header">
            <div>
                <h2 class="h6 fw-bold mb-1">Asistente SixSeven</h2>
                <p class="support-widget-status mb-0"><span class="support-widget-dot"></span>Soporte técnico · Respondemos en minutos</p>
            </div>
            <button type="button" id="supportWidgetClose" class="btn-close btn-close-white" aria-label="Cerrar"></button>
        </div>

        <div class="support-widget-body">
            <p class="support-widget-greeting">¡Hola! 👋 Contanos si tenés dudas sobre cómo usar el sistema o si encontraste un error en la web.</p>

            <div id="supportWidgetMessages" class="support-widget-messages"></div>
            <form id="supportWidgetForm">
                <textarea id="supportWidgetMessage" name="message" rows="2" class="form-control mb-2" placeholder="Escribí tu consulta o reportá un error..." required></textarea>
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Enviar consulta</button>
            </form>

            <div id="supportWidgetFeedback" class="support-widget-feedback" hidden></div>
        </div>
    </div>
</div>

<script>
(function () {
    var POLL_MS = 8000;

    var toggle = document.getElementById('supportWidgetToggle');
    var panel = document.getElementById('supportWidgetPanel');
    var closeBtn = document.getElementById('supportWidgetClose');
    var messagesEl = document.getElementById('supportWidgetMessages');
    var form = document.getElementById('supportWidgetForm');
    var messageInput = document.getElementById('supportWidgetMessage');
    var feedback = document.getElementById('supportWidgetFeedback');

    var pollTimer = null;
    var lastMessageId = 0;
    var loaded = false;

    function showFeedback(text, isError) {
        feedback.hidden = false;
        feedback.className = 'support-widget-feedback ' + (isError ? 'support-widget-feedback-error' : 'support-widget-feedback-success');
        feedback.textContent = text;
    }

    function renderMessages(messages, append) {
        if (!append) messagesEl.innerHTML = '';
        messages.forEach(function (m) {
            var bubble = document.createElement('div');
            bubble.className = 'support-widget-bubble support-widget-bubble-' + (m.sender === 'admin' ? 'admin' : 'visitor');
            var text = document.createElement('div');
            text.textContent = m.message;
            var time = document.createElement('time');
            var d = new Date(m.created_at.replace(' ', 'T'));
            time.textContent = isNaN(d.getTime()) ? '' : d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
            bubble.appendChild(text);
            bubble.appendChild(time);
            messagesEl.appendChild(bubble);
            lastMessageId = Math.max(lastMessageId, m.id);
        });
        if (messages.length) messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function fetchQuery(params) {
        var qs = new URLSearchParams(params).toString();
        return fetch('<?= BASE_URL ?>/api/support_widget.php?' + qs).then(function (res) { return res.json(); });
    }

    function pollForReplies() {
        fetchQuery({ after_id: lastMessageId }).then(function (data) {
            if (data.ok && data.messages && data.messages.length) {
                renderMessages(data.messages, true);
            }
        }).catch(function () { /* silent: next poll will retry */ });
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(pollForReplies, POLL_MS);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function loadConversation() {
        fetchQuery({}).then(function (data) {
            if (data.ok) renderMessages(data.messages, false);
        }).catch(function () { /* keep the empty chat, user can still send a message */ });
    }

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        if (!loaded) {
            loadConversation();
            loaded = true;
        }
        startPolling();
        messageInput.focus();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        stopPolling();
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
                if (data.ok) {
                    form.reset();
                    showFeedback('¡Mensaje enviado! Te responderemos a la brevedad.', false);
                    pollForReplies();
                } else {
                    showFeedback(data.error || 'No se pudo enviar tu consulta. Intentá de nuevo.', true);
                }
            })
            .catch(function () {
                showFeedback('No se pudo enviar tu consulta. Revisá tu conexión e intentá de nuevo.', true);
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
})();
</script>
