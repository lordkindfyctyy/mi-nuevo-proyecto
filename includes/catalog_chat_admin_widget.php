<?php
/**
 * Floating chat widget for the merchant side, visually identical to the
 * customer-facing widget in includes/catalog_chat_widget.php, but showing
 * the tenant's inbox of catalog-chat conversations instead of an identify
 * form. Scoped to currentTenantId() via the JSON api/catalog_chat_admin.php
 * endpoint.
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
    .catalog-chat-btn-badge {
        position: absolute; top: -.3rem; right: -.3rem; min-width: 1.4rem; height: 1.4rem; padding: 0 .3rem;
        border-radius: 999px; background: #ef4444; color: #fff; font-size: .74rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 2px #fff;
    }

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

    .catalog-chat-conv-list { display: flex; flex-direction: column; gap: .55rem; }
    .catalog-chat-conv-empty { text-align: center; color: #9aa3af; font-size: .85rem; padding: 2rem 1rem; }
    .catalog-chat-conv-item {
        background: #fff; border: 1px solid #e9ebee; border-radius: 1rem; padding: .7rem .85rem;
        cursor: pointer; transition: box-shadow .15s ease, border-color .15s ease; box-shadow: 0 .1rem .3rem rgba(15, 23, 42, .04);
    }
    .catalog-chat-conv-item:hover { box-shadow: 0 .3rem .6rem rgba(15, 23, 42, .08); border-color: #d7dbe0; }
    .catalog-chat-conv-item.unread { border-color: #00b28f; background: rgba(0, 178, 143, .05); }
    .catalog-chat-conv-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .catalog-chat-conv-name { font-weight: 600; font-size: .87rem; color: #1f2937; }
    .catalog-chat-conv-badge {
        background: #00b28f; color: #fff; border-radius: 999px; font-size: .68rem; font-weight: 700;
        padding: .08rem .45rem; flex-shrink: 0;
    }
    .catalog-chat-conv-preview { font-size: .8rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .15rem; }

    .catalog-chat-thread-header { display: flex; align-items: center; gap: .6rem; padding-bottom: .8rem; margin-bottom: .6rem; border-bottom: 1px solid #eceef1; }
    .catalog-chat-back-btn { border: none; background: transparent; color: #6b7280; font-size: 1.15rem; padding: .2rem; cursor: pointer; flex-shrink: 0; line-height: 1; }
    .catalog-chat-back-btn:hover { color: #1f2937; }
    .catalog-chat-thread-name { font-weight: 600; font-size: .9rem; color: #1f2937; }
    .catalog-chat-thread-contact { font-size: .76rem; color: #9aa3af; }

    .catalog-chat-messages { display: flex; flex-direction: column; gap: .55rem; max-height: 280px; overflow-y: auto; margin-bottom: .75rem; padding-right: .15rem; }
    .catalog-chat-bubble {
        max-width: 84%; padding: .55rem .8rem; border-radius: 1.05rem; font-size: .87rem; line-height: 1.4;
        box-shadow: 0 .1rem .3rem rgba(15, 23, 42, .05); word-wrap: break-word;
    }
    .catalog-chat-bubble time { display: block; margin-top: .25rem; font-size: .68rem; opacity: .72; }
    .catalog-chat-bubble-visitor {
        align-self: flex-start; background: #fff; color: #1f2937; border: 1px solid #e9ebee;
        border-bottom-left-radius: .3rem;
    }
    .catalog-chat-bubble-admin {
        align-self: flex-end; background: linear-gradient(135deg, #00cfa8, #00997e); color: #fff;
        border-bottom-right-radius: .3rem;
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
    <button type="button" id="adminChatToggle" class="catalog-chat-btn" aria-label="Chat con clientes del catálogo" aria-expanded="false" aria-controls="adminChatPanel">
        <i class="bi bi-chat-dots-fill"></i>
        <span id="adminChatBadge" class="catalog-chat-btn-badge" hidden>0</span>
    </button>

    <div id="adminChatPanel" class="catalog-chat-panel" hidden>
        <div class="catalog-chat-header">
            <div class="catalog-chat-avatar"><i class="bi bi-shop"></i></div>
            <div class="catalog-chat-header-text">
                <h2 class="fw-bold mb-1">Chat con Clientes</h2>
                <p class="catalog-chat-status"><span class="catalog-chat-dot"></span>Mensajes del catálogo</p>
            </div>
            <button type="button" id="adminChatClose" class="catalog-chat-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="catalog-chat-body">
            <div id="adminChatList">
                <p id="adminChatListEmpty" class="catalog-chat-conv-empty" hidden>Todavía no recibiste consultas desde tu catálogo.</p>
                <div id="adminChatListItems" class="catalog-chat-conv-list"></div>
            </div>

            <div id="adminChatThread" hidden>
                <div class="catalog-chat-thread-header">
                    <button type="button" id="adminChatBack" class="catalog-chat-back-btn" aria-label="Volver a la lista"><i class="bi bi-arrow-left"></i></button>
                    <div>
                        <div id="adminChatThreadName" class="catalog-chat-thread-name"></div>
                        <div id="adminChatThreadContact" class="catalog-chat-thread-contact"></div>
                    </div>
                </div>
                <div id="adminChatMessages" class="catalog-chat-messages"></div>
                <form id="adminChatReplyForm" class="catalog-chat-input-row">
                    <textarea id="adminChatReplyInput" rows="1" placeholder="Escribí tu respuesta..." required></textarea>
                    <button type="submit" class="catalog-chat-send-btn" aria-label="Enviar"><i class="bi bi-send-fill"></i></button>
                </form>
            </div>

            <div id="adminChatFeedback" class="catalog-chat-feedback" hidden></div>
            <p class="catalog-chat-footer">Chat seguro · Powered by SixSeven</p>
        </div>
    </div>
</div>

<script>
(function () {
    var POLL_MS = 8000;

    var toggle = document.getElementById('adminChatToggle');
    var badge = document.getElementById('adminChatBadge');
    var panel = document.getElementById('adminChatPanel');
    var closeBtn = document.getElementById('adminChatClose');
    var listView = document.getElementById('adminChatList');
    var listEmpty = document.getElementById('adminChatListEmpty');
    var listItems = document.getElementById('adminChatListItems');
    var threadView = document.getElementById('adminChatThread');
    var threadName = document.getElementById('adminChatThreadName');
    var threadContact = document.getElementById('adminChatThreadContact');
    var messagesEl = document.getElementById('adminChatMessages');
    var backBtn = document.getElementById('adminChatBack');
    var replyForm = document.getElementById('adminChatReplyForm');
    var replyInput = document.getElementById('adminChatReplyInput');
    var feedback = document.getElementById('adminChatFeedback');

    var activeEmail = null;
    var lastMessageId = 0;
    var pollTimer = null;

    function api(params) {
        var qs = new URLSearchParams(params).toString();
        return fetch('<?= BASE_URL ?>/api/catalog_chat_admin.php?' + qs).then(function (r) { return r.json(); });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function formatTime(value) {
        var d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d.getTime()) ? '' : d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function showFeedback(text, isError) {
        feedback.hidden = false;
        feedback.className = 'catalog-chat-feedback ' + (isError ? 'catalog-chat-feedback-error' : 'catalog-chat-feedback-success');
        feedback.textContent = text;
    }

    function refreshBadge() {
        api({ action: 'unread_count' }).then(function (data) {
            if (!data.ok) return;
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.hidden = false;
            } else {
                badge.hidden = true;
            }
        }).catch(function () { /* silent */ });
    }

    function renderList(conversations) {
        listItems.innerHTML = '';
        listEmpty.hidden = conversations.length > 0;

        conversations.forEach(function (conv) {
            var item = document.createElement('div');
            item.className = 'catalog-chat-conv-item' + (conv.unread_count > 0 ? ' unread' : '');
            var prefix = conv.last_sender === 'admin' ? 'Vos: ' : '';
            item.innerHTML =
                '<div class="catalog-chat-conv-top">' +
                    '<span class="catalog-chat-conv-name">' + escapeHtml(conv.name) + '</span>' +
                    (conv.unread_count > 0 ? '<span class="catalog-chat-conv-badge">' + conv.unread_count + '</span>' : '') +
                '</div>' +
                '<div class="catalog-chat-conv-preview">' + escapeHtml(prefix + conv.last_message) + '</div>';
            item.addEventListener('click', function () { openThread(conv.email, conv.name, conv.phone); });
            listItems.appendChild(item);
        });
    }

    function loadList() {
        api({ action: 'list' }).then(function (data) {
            if (data.ok) renderList(data.conversations);
        }).catch(function () { /* silent */ });
    }

    function renderMessages(messages, append) {
        if (!append) messagesEl.innerHTML = '';
        messages.forEach(function (m) {
            var bubble = document.createElement('div');
            bubble.className = 'catalog-chat-bubble catalog-chat-bubble-' + (m.sender === 'admin' ? 'admin' : 'visitor');
            var text = document.createElement('div');
            text.textContent = m.message;
            var time = document.createElement('time');
            time.textContent = formatTime(m.created_at);
            bubble.appendChild(text);
            bubble.appendChild(time);
            messagesEl.appendChild(bubble);
            lastMessageId = Math.max(lastMessageId, m.id);
        });
        if (messages.length) messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function openThread(email, name, phone) {
        activeEmail = email;
        lastMessageId = 0;
        threadName.textContent = name || email;
        threadContact.textContent = email + (phone ? ' · ' + phone : '');
        listView.hidden = true;
        threadView.hidden = false;

        api({ action: 'thread', email: email }).then(function (data) {
            if (data.ok) renderMessages(data.messages, false);
        }).finally(function () {
            refreshBadge();
            replyInput.focus();
        });
    }

    function backToList() {
        activeEmail = null;
        threadView.hidden = true;
        listView.hidden = false;
        loadList();
    }

    function pollActive() {
        if (activeEmail) {
            api({ action: 'thread', email: activeEmail, after_id: lastMessageId }).then(function (data) {
                if (data.ok && data.messages.length) renderMessages(data.messages, true);
            }).catch(function () { /* silent */ });
        } else {
            loadList();
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(pollActive, POLL_MS);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        backToList();
        startPolling();
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        stopPolling();
        refreshBadge();
    }

    toggle.addEventListener('click', function () {
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    closeBtn.addEventListener('click', closePanel);
    backBtn.addEventListener('click', backToList);

    replyInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            replyForm.requestSubmit();
        }
    });

    replyForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = replyInput.value.trim();
        if (!message || !activeEmail) return;

        var submitBtn = replyForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        var body = new URLSearchParams();
        body.set('action', 'reply');
        body.set('email', activeEmail);
        body.set('message', message);

        fetch('<?= BASE_URL ?>/api/catalog_chat_admin.php', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    replyInput.value = '';
                    pollActive();
                } else {
                    showFeedback(data.error || 'No se pudo enviar la respuesta.', true);
                }
            })
            .catch(function () {
                showFeedback('No se pudo enviar la respuesta. Revisá tu conexión.', true);
            })
            .finally(function () {
                submitBtn.disabled = false;
            });
    });

    refreshBadge();
    setInterval(refreshBadge, 20000);
})();
</script>
