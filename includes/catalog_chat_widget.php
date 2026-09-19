<?php
/**
 * Self-contained catalog sales chat widget. Expects $catalogToken (the
 * catalog's public ?t= token) to already be set by the including page.
 */
?>
<style>
    .catalog-chat-btn {
        position: fixed; left: 1.25rem; bottom: 1.25rem; z-index: 20;
        width: 3.5rem; height: 3.5rem; border-radius: 50%; border: none;
        background: linear-gradient(135deg, #00b28f, #009677); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.2); cursor: pointer;
    }
    .catalog-chat-panel {
        position: fixed; left: 1.25rem; bottom: 5.25rem; z-index: 21;
        width: 320px; max-width: calc(100vw - 2rem); max-height: calc(100vh - 8rem);
        background: #fff; border-radius: 1rem; box-shadow: 0 1.5rem 3rem rgba(0,0,0,.18);
        overflow: hidden; display: flex; flex-direction: column;
    }
    .catalog-chat-header {
        background: linear-gradient(135deg, #00b28f, #009677); color: #fff;
        padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem;
    }
    .catalog-chat-status { font-size: .8rem; color: rgba(255,255,255,.9); display: flex; align-items: center; gap: .4rem; margin: 0; }
    .catalog-chat-dot { width: .5rem; height: .5rem; border-radius: 50%; background: #4ade80; display: inline-block; }
    .catalog-chat-body { padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; min-height: 0; }
    .catalog-chat-greeting { font-size: .9rem; margin-bottom: 1rem; }
    .catalog-chat-messages { display: flex; flex-direction: column; gap: .5rem; max-height: 260px; overflow-y: auto; margin-bottom: .75rem; padding-right: .25rem; }
    .catalog-chat-bubble { max-width: 85%; padding: .5rem .75rem; border-radius: .9rem; font-size: .85rem; line-height: 1.35; }
    .catalog-chat-bubble time { display: block; margin-top: .2rem; font-size: .7rem; opacity: .7; }
    .catalog-chat-bubble-visitor { align-self: flex-end; background: #00b28f; color: #fff; border-bottom-right-radius: .25rem; }
    .catalog-chat-bubble-admin { align-self: flex-start; background: #f1f2f4; color: #1f2937; border: 1px solid #e5e7eb; border-bottom-left-radius: .25rem; }
    .catalog-chat-feedback { margin-top: .75rem; font-size: .85rem; border-radius: .5rem; padding: .6rem .75rem; }
    .catalog-chat-feedback-success { background: rgba(0,178,143,.12); color: #009677; }
    .catalog-chat-feedback-error { background: rgba(220,53,69,.12); color: #b02a37; }
    @media (max-width: 420px) {
        .catalog-chat-panel { left: 1rem; right: 1rem; width: auto; bottom: 5rem; }
        .catalog-chat-btn { left: 1rem; bottom: 1rem; }
    }
</style>

<div class="catalog-chat-widget">
    <button type="button" id="catalogChatToggle" class="catalog-chat-btn" aria-label="Escribirle al vendedor" aria-expanded="false" aria-controls="catalogChatPanel">
        <i class="bi bi-chat-dots-fill"></i>
    </button>

    <div id="catalogChatPanel" class="catalog-chat-panel" hidden>
        <div class="catalog-chat-header">
            <div>
                <h2 class="h6 fw-bold mb-1">Hablá con el vendedor</h2>
                <p class="catalog-chat-status"><span class="catalog-chat-dot"></span>Respondemos en minutos</p>
            </div>
            <button type="button" id="catalogChatClose" class="btn-close btn-close-white" aria-label="Cerrar"></button>
        </div>

        <div class="catalog-chat-body">
            <p class="catalog-chat-greeting">¡Hola! 👋 ¿Tenés dudas sobre algún producto o querés hacer un pedido especial? Escribinos.</p>

            <form id="catalogChatIdentifyForm">
                <input type="text" id="catalogChatName" class="form-control mb-2" placeholder="Nombre" required>
                <input type="email" id="catalogChatEmail" class="form-control mb-2" placeholder="Email" required>
                <input type="tel" id="catalogChatPhone" class="form-control mb-2" placeholder="Celular" required>
                <button type="submit" class="btn btn-primary w-100 fw-semibold">Comenzar chat</button>
            </form>

            <div id="catalogChatConversation" hidden>
                <div id="catalogChatMessages" class="catalog-chat-messages"></div>
                <form id="catalogChatForm">
                    <textarea id="catalogChatMessage" rows="2" class="form-control mb-2" placeholder="Escribí tu consulta..." required></textarea>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">Enviar consulta</button>
                </form>
            </div>

            <div id="catalogChatFeedback" class="catalog-chat-feedback" hidden></div>
        </div>
    </div>
</div>

<script>
(function () {
    var CATALOG_TOKEN = <?= json_encode($catalogToken ?? '') ?>;
    var STORAGE_KEY = 'sixseven_catalog_chat_identity';
    var POLL_MS = 8000;

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
        if (!append) messagesEl.innerHTML = '';
        messages.forEach(function (m) {
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

    function fetchQuery(params) {
        params.t = CATALOG_TOKEN;
        var qs = new URLSearchParams(params).toString();
        return fetch('/api/catalog_chat.php?' + qs).then(function (res) { return res.json(); });
    }

    function pollForReplies() {
        var identity = getIdentity();
        if (!identity || !identity.email) return;

        fetchQuery({ after_id: lastMessageId, email: identity.email, token: identity.token || '' }).then(function (data) {
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

        fetchQuery({ email: identity.email, token: identity.token || '' }).then(function (data) {
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

    identifyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = document.getElementById('catalogChatName').value.trim();
        var email = document.getElementById('catalogChatEmail').value.trim();
        var phone = document.getElementById('catalogChatPhone').value.trim();
        if (!name || !email || !phone) return;

        saveIdentity({ name: name, email: email, phone: phone, token: null });
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

        var body = new URLSearchParams();
        body.set('t', CATALOG_TOKEN);
        body.set('message', message);
        body.set('name', identity.name);
        body.set('email', identity.email);
        body.set('phone', identity.phone);
        body.set('token', identity.token || '');

        fetch('/api/catalog_chat.php', { method: 'POST', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.ok) {
                    if (data.token) {
                        identity.token = data.token;
                        saveIdentity(identity);
                    }
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
