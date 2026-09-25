<?php
/**
 * Self-contained catalog sales chat widget. Expects $catalogToken (the
 * catalog's public ?t= token) to already be set by the including page.
 */
?>
<style>
    .catalog-chat-btn {
        position: fixed; left: 1.25rem; bottom: 1.25rem; z-index: 20;
        width: 3.75rem; height: 3.75rem; border-radius: 50%; border: none;
        background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 1.55rem;
        box-shadow: 0 .6rem 1.5rem rgba(0, 153, 126, .38); cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .catalog-chat-btn:hover { transform: translateY(-2px) scale(1.04); box-shadow: 0 .85rem 1.75rem rgba(0, 153, 126, .45); }
    .catalog-chat-btn:active { transform: translateY(0) scale(.98); }

    .catalog-chat-panel {
        position: fixed; left: 1.25rem; bottom: 5.5rem; z-index: 21;
        width: 350px; max-width: calc(100vw - 2rem); max-height: calc(100vh - 8rem);
        background: #fff; border-radius: 1.25rem; box-shadow: 0 1.75rem 4rem rgba(15, 23, 42, .22);
        overflow: hidden; display: flex; flex-direction: column;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    @keyframes catalog-chat-pop-in {
        from { opacity: 0; transform: translateY(14px) scale(.96); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .catalog-chat-panel:not([hidden]) { animation: catalog-chat-pop-in .2s cubic-bezier(.2, .8, .3, 1); }

    .catalog-chat-header {
        background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff;
        padding: 1.1rem 1.25rem; display: flex; align-items: center; gap: .75rem;
    }
    .catalog-chat-avatar {
        width: 2.5rem; height: 2.5rem; border-radius: 50%; flex-shrink: 0;
        background: rgba(255, 255, 255, .18); border: 1.5px solid rgba(255, 255, 255, .55);
        display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
    }
    .catalog-chat-header-text { flex: 1; min-width: 0; }
    .catalog-chat-header-text h2 { font-size: .98rem; letter-spacing: -.01em; }
    .catalog-chat-status { font-size: .78rem; color: rgba(255, 255, 255, .92); display: flex; align-items: center; gap: .4rem; margin: 0; }
    .catalog-chat-dot {
        width: .45rem; height: .45rem; border-radius: 50%; background: #baffde; display: inline-block;
        box-shadow: 0 0 0 rgba(186, 255, 222, .6); animation: catalog-chat-pulse 2s infinite;
    }
    @keyframes catalog-chat-pulse {
        0% { box-shadow: 0 0 0 0 rgba(186, 255, 222, .55); }
        70% { box-shadow: 0 0 0 .4rem rgba(186, 255, 222, 0); }
        100% { box-shadow: 0 0 0 0 rgba(186, 255, 222, 0); }
    }
    .catalog-chat-close {
        margin-left: auto; flex-shrink: 0; width: 1.9rem; height: 1.9rem; border-radius: 50%; border: none;
        background: rgba(255, 255, 255, .16); color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: .95rem; cursor: pointer; transition: background .15s ease;
    }
    .catalog-chat-close:hover { background: rgba(255, 255, 255, .28); }

    .catalog-chat-body { padding: 1.1rem 1.1rem .9rem; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; background: #fafbfc; }

    .catalog-chat-greeting-bubble {
        align-self: flex-start; max-width: 92%; background: #fff; border: 1px solid #e9ebee;
        border-radius: 1rem; border-bottom-left-radius: .3rem; padding: .65rem .85rem;
        font-size: .86rem; line-height: 1.45; color: #374151; box-shadow: 0 .1rem .35rem rgba(15, 23, 42, .04);
        margin-bottom: .9rem;
    }

    .catalog-chat-identify-form { display: flex; flex-direction: column; gap: .6rem; }
    .catalog-chat-field { position: relative; }
    .catalog-chat-field i {
        position: absolute; left: .8rem; top: 50%; transform: translateY(-50%);
        color: #9aa3af; font-size: .9rem; pointer-events: none;
    }
    .catalog-chat-field input {
        width: 100%; padding: .6rem .75rem .6rem 2.15rem; border: 1px solid #e0e3e8; border-radius: .7rem;
        font-size: .88rem; background: #fff; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .catalog-chat-field input:focus {
        outline: none; border-color: #00b28f; box-shadow: 0 0 0 .18rem rgba(0, 178, 143, .14);
    }
    .catalog-chat-identify-submit {
        margin-top: .2rem; border: none; border-radius: .75rem; padding: .65rem 1rem;
        background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff; font-weight: 600; font-size: .9rem;
        cursor: pointer; transition: filter .15s ease;
    }
    .catalog-chat-identify-submit:hover { filter: brightness(1.05); }

    .catalog-chat-messages { display: flex; flex-direction: column; gap: .55rem; max-height: 280px; overflow-y: auto; margin-bottom: .75rem; padding-right: .15rem; }
    .catalog-chat-bubble {
        max-width: 84%; padding: .55rem .8rem; border-radius: 1.05rem; font-size: .87rem; line-height: 1.4;
        box-shadow: 0 .1rem .3rem rgba(15, 23, 42, .05); word-wrap: break-word;
    }
    .catalog-chat-bubble time { display: block; margin-top: .25rem; font-size: .68rem; opacity: .72; }
    .catalog-chat-bubble-visitor {
        align-self: flex-end; background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff;
        border-bottom-right-radius: .3rem;
    }
    .catalog-chat-bubble-admin {
        align-self: flex-start; background: #fff; color: #1f2937; border: 1px solid #e9ebee;
        border-bottom-left-radius: .3rem;
    }
    .catalog-chat-bubble-visitor.catalog-chat-bubble-pending { opacity: .6; }

    .catalog-chat-typing { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem 0; }
    .catalog-chat-typing span {
        width: .4rem; height: .4rem; border-radius: 50%; background: #b7bcc4;
        animation: catalog-chat-typing-bounce 1.2s infinite ease-in-out;
    }
    .catalog-chat-typing span:nth-child(2) { animation-delay: .15s; }
    .catalog-chat-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes catalog-chat-typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: .5; }
        30% { transform: translateY(-.2rem); opacity: 1; }
    }

    .catalog-chat-input-row {
        display: flex; align-items: flex-end; gap: .5rem; background: #fff; border: 1px solid #e0e3e8;
        border-radius: 1.25rem; padding: .35rem .4rem .35rem .9rem; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .catalog-chat-input-row:focus-within { border-color: #00b28f; box-shadow: 0 0 0 .18rem rgba(0, 178, 143, .14); }
    .catalog-chat-input-row textarea {
        flex: 1; border: none; outline: none; resize: none; font: inherit; font-size: .87rem;
        padding: .4rem 0; max-height: 4.5rem; background: transparent;
    }
    .catalog-chat-send-btn {
        flex-shrink: 0; width: 2.15rem; height: 2.15rem; border-radius: 50%; border: none;
        background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff; display: flex; align-items: center;
        justify-content: center; font-size: .95rem; cursor: pointer; transition: filter .15s ease, transform .1s ease;
    }
    .catalog-chat-send-btn:hover { filter: brightness(1.05); }
    .catalog-chat-send-btn:active { transform: scale(.92); }
    .catalog-chat-send-btn:disabled { opacity: .55; cursor: default; }

    .catalog-chat-feedback { margin-top: .7rem; font-size: .82rem; border-radius: .6rem; padding: .55rem .75rem; }
    .catalog-chat-feedback-success { background: rgba(0, 178, 143, .12); color: #00806a; }
    .catalog-chat-feedback-error { background: rgba(220, 53, 69, .12); color: #b02a37; }

    .catalog-chat-footer { text-align: center; font-size: .68rem; color: #9aa3af; padding: .55rem 0 .1rem; }

    @media (max-width: 420px) {
        .catalog-chat-panel { left: .75rem; right: .75rem; width: auto; bottom: 5.25rem; max-height: calc(100vh - 7rem); }
        .catalog-chat-btn { left: 1rem; bottom: 1rem; }
    }
</style>

<div class="catalog-chat-widget">
    <button type="button" id="catalogChatToggle" class="catalog-chat-btn" aria-label="Escribirle al vendedor" aria-expanded="false" aria-controls="catalogChatPanel">
        <i class="bi bi-chat-dots-fill"></i>
    </button>

    <div id="catalogChatPanel" class="catalog-chat-panel" hidden>
        <div class="catalog-chat-header">
            <div class="catalog-chat-avatar"><i class="bi bi-shop"></i></div>
            <div class="catalog-chat-header-text">
                <h2 class="fw-bold mb-1">Hablá con el vendedor</h2>
                <p class="catalog-chat-status"><span class="catalog-chat-dot"></span>Respondemos en minutos</p>
            </div>
            <button type="button" id="catalogChatClose" class="catalog-chat-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="catalog-chat-body">
            <div class="catalog-chat-greeting-bubble">¡Hola! 👋 ¿Tenés dudas sobre algún producto o querés hacer un pedido especial? Escribinos.</div>

            <form id="catalogChatIdentifyForm" class="catalog-chat-identify-form">
                <div class="catalog-chat-field">
                    <i class="bi bi-person"></i>
                    <input type="text" id="catalogChatName" placeholder="Nombre" required>
                </div>
                <div class="catalog-chat-field">
                    <i class="bi bi-whatsapp"></i>
                    <input type="tel" id="catalogChatPhone" placeholder="Teléfono / WhatsApp (opcional)">
                </div>
                <button type="submit" class="catalog-chat-identify-submit">Comenzar chat</button>
            </form>

            <div id="catalogChatConversation" hidden>
                <div id="catalogChatMessages" class="catalog-chat-messages"></div>
                <form id="catalogChatForm" class="catalog-chat-input-row">
                    <textarea id="catalogChatMessage" rows="1" placeholder="Escribí tu mensaje..." required></textarea>
                    <button type="submit" class="catalog-chat-send-btn" aria-label="Enviar"><i class="bi bi-send-fill"></i></button>
                </form>
            </div>

            <div id="catalogChatFeedback" class="catalog-chat-feedback" hidden></div>
            <p class="catalog-chat-footer">Chat seguro · Powered by SixSeven</p>
        </div>
    </div>
</div>

<script>
(function () {
    var CATALOG_TOKEN = <?= json_encode($catalogToken ?? '') ?>;
    var AI_ENABLED = <?= !empty($tenant['ai_assistant_enabled']) ? 'true' : 'false' ?>;
    var STORAGE_KEY = 'sixseven_catalog_chat_identity';
    var POLL_MS = 8000;
    var TYPING_TIMEOUT_MS = 20000;

    var toggle = document.getElementById('catalogChatToggle');
    var panel = document.getElementById('catalogChatPanel');
    var closeBtn = document.getElementById('catalogChatClose');
    var identifyForm = document.getElementById('catalogChatIdentifyForm');
    var conversation = document.getElementById('catalogChatConversation');
    var messagesEl = document.getElementById('catalogChatMessages');
    var form = document.getElementById('catalogChatForm');
    var messageInput = document.getElementById('catalogChatMessage');
    var feedback = document.getElementById('catalogChatFeedback');

    var pollTimer = null;
    var lastMessageId = 0;
    var loaded = false;
    var typingEl = null;
    var typingTimeoutTimer = null;
    var renderedIds = {};
    var pollInFlight = false;

    function generateToken() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '');
        var bytes = new Uint8Array(16);
        (window.crypto || {}).getRandomValues ? crypto.getRandomValues(bytes) : bytes.forEach(function (_, i) { bytes[i] = Math.floor(Math.random() * 256); });
        return Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }

    function getIdentity() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function saveIdentity(identity) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(identity));
        } catch (e) { /* private browsing / storage blocked: chat still works this pageview */ }
    }

    function showFeedback(text, isError) {
        feedback.hidden = false;
        feedback.className = 'catalog-chat-feedback ' + (isError ? 'catalog-chat-feedback-error' : 'catalog-chat-feedback-success');
        feedback.textContent = text;
    }

    function renderMessages(messages, append) {
        if (!append) { messagesEl.innerHTML = ''; renderedIds = {}; }
        messages.forEach(function (m) {
            // Dos polls pueden solaparse (el del intervalo de 8s y el que se
            // dispara justo al mandar un mensaje) y devolver el mismo
            // mensaje dos veces — sin esto, se veía duplicado en el chat.
            if (renderedIds[m.id]) {
                lastMessageId = Math.max(lastMessageId, m.id);
                return;
            }
            renderedIds[m.id] = true;

            var bubble = document.createElement('div');
            bubble.className = 'catalog-chat-bubble catalog-chat-bubble-' + (m.sender === 'admin' ? 'admin' : 'visitor');
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

    // Feedback instantáneo al enviar: el propio mensaje aparece al toque
    // (atenuado, como "enviando...") en vez de quedar la pantalla como
    // congelada hasta que vuelva la respuesta. Se saca en cuanto el poll
    // trae la versión real del mismo mensaje.
    function appendPendingMessage(text) {
        var bubble = document.createElement('div');
        bubble.className = 'catalog-chat-bubble catalog-chat-bubble-visitor catalog-chat-bubble-pending';
        bubble.dataset.pending = 'true';
        var textEl = document.createElement('div');
        textEl.textContent = text;
        var time = document.createElement('time');
        time.textContent = new Date().toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        bubble.appendChild(textEl);
        bubble.appendChild(time);
        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function clearPendingMessages() {
        messagesEl.querySelectorAll('[data-pending="true"]').forEach(function (el) { el.remove(); });
    }

    // Solo tiene sentido mostrar "escribiendo..." cuando el negocio tiene el
    // asistente de IA activado (si no, nadie va a responder automático y el
    // indicador quedaría girando para siempre).
    function showTyping() {
        if (!AI_ENABLED) return;
        hideTyping();
        typingEl = document.createElement('div');
        typingEl.className = 'catalog-chat-bubble catalog-chat-bubble-admin';
        typingEl.innerHTML = '<div class="catalog-chat-typing"><span></span><span></span><span></span></div>';
        messagesEl.appendChild(typingEl);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        typingTimeoutTimer = setTimeout(hideTyping, TYPING_TIMEOUT_MS);
    }

    function hideTyping() {
        if (typingTimeoutTimer) {
            clearTimeout(typingTimeoutTimer);
            typingTimeoutTimer = null;
        }
        if (typingEl) {
            typingEl.remove();
            typingEl = null;
        }
    }

    function fetchQuery(params) {
        params.t = CATALOG_TOKEN;
        var qs = new URLSearchParams(params).toString();
        return fetch('/api/catalog_chat.php?' + qs).then(function (res) { return res.json(); });
    }

    function pollForReplies() {
        var identity = getIdentity();
        if (!identity || !identity.token) return;
        // El intervalo de 8s y el poll extra al mandar un mensaje pueden
        // superponerse; con esto el segundo simplemente no arranca en vez
        // de pisarse con el primero.
        if (pollInFlight) return;
        pollInFlight = true;

        fetchQuery({ after_id: lastMessageId, token: identity.token }).then(function (data) {
            if (data.ok && data.messages && data.messages.length) {
                clearPendingMessages();
                // Este poll puede traer solo la confirmación del propio
                // mensaje (sin respuesta todavía) — el indicador de
                // "escribiendo..." se saca recién cuando llega algo del
                // vendedor/asistente, no antes.
                var hasReply = data.messages.some(function (m) { return m.sender === 'admin'; });
                if (hasReply) hideTyping();
                renderMessages(data.messages, true);
            }
        }).catch(function () { /* silent: next poll will retry */ })
            .finally(function () { pollInFlight = false; });
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

    function showConversation() {
        identifyForm.hidden = true;
        conversation.hidden = false;
    }

    function loadConversation() {
        var identity = getIdentity();
        if (!identity) {
            identifyForm.hidden = false;
            conversation.hidden = true;
            return;
        }

        showConversation();

        fetchQuery({ token: identity.token }).then(function (data) {
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

    messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    identifyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = document.getElementById('catalogChatName').value.trim();
        var phone = document.getElementById('catalogChatPhone').value.trim();
        if (!name) return;

        saveIdentity({ name: name, phone: phone || null, token: generateToken() });
        showConversation();
        renderMessages([], false);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = messageInput.value.trim();
        if (!message) return;

        var identity = getIdentity();
        if (!identity) return;

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        feedback.hidden = true;

        // Se muestra al toque, sin esperar la ida y vuelta al servidor (que
        // puede tardar unos segundos si el asistente de IA está pensando la
        // respuesta) — así la conversación se siente fluida en vez de
        // congelada.
        appendPendingMessage(message);
        showTyping();
        form.reset();

        var body = new URLSearchParams();
        body.set('t', CATALOG_TOKEN);
        body.set('message', message);
        body.set('name', identity.name);
        body.set('phone', identity.phone || '');
        body.set('token', identity.token);

        fetch('/api/catalog_chat.php', { method: 'POST', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    pollForReplies();
                } else {
                    hideTyping();
                    clearPendingMessages();
                    showFeedback(data.error || 'No se pudo enviar tu consulta. Intentá de nuevo.', true);
                }
            })
            .catch(function () {
                hideTyping();
                clearPendingMessages();
                showFeedback('No se pudo enviar tu consulta. Revisá tu conexión e intentá de nuevo.', true);
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });
})();
</script>
