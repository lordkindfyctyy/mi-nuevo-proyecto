<div class="offcanvas offcanvas-end catalog-chat-drawer" tabindex="-1" id="catalogChatDrawer" aria-labelledby="catalogChatDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="catalogChatDrawerLabel">
            <i class="bi bi-chat-dots-fill text-primary"></i> Chat con Clientes del Catálogo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div id="catalogChatDrawerList" class="flex-grow-1 overflow-auto">
            <p id="catalogChatDrawerListEmpty" class="text-center text-secondary py-5 px-3" hidden>Todavía no recibiste consultas desde tu catálogo público.</p>
            <div id="catalogChatDrawerListItems" class="list-group list-group-flush"></div>
        </div>

        <div id="catalogChatDrawerThread" class="d-flex flex-column flex-grow-1 overflow-hidden" hidden>
            <div class="p-2 border-bottom d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-link text-secondary p-1" id="catalogChatDrawerBack" aria-label="Volver a la lista">
                    <i class="bi bi-arrow-left fs-5"></i>
                </button>
                <div class="flex-grow-1 text-truncate">
                    <div class="fw-semibold text-truncate" id="catalogChatDrawerThreadName"></div>
                    <div class="small text-secondary text-truncate" id="catalogChatDrawerThreadMeta"></div>
                </div>
            </div>
            <div id="catalogChatDrawerMessages" class="flex-grow-1 overflow-auto p-3 d-flex flex-column gap-2"></div>
            <form id="catalogChatDrawerReplyForm" class="p-2 border-top d-flex gap-2">
                <textarea id="catalogChatDrawerReplyInput" class="form-control" rows="1" placeholder="Escribí tu respuesta..." required></textarea>
                <button type="submit" class="btn btn-primary flex-shrink-0"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>
    </div>
</div>

<style>
    .catalog-chat-drawer { width: 380px; max-width: 92vw; }
    .catalog-chat-drawer-item { cursor: pointer; }
    .catalog-chat-drawer-item.unread { background: rgba(0, 178, 143, .06); }
    .catalog-chat-drawer-item .item-name { font-weight: 600; }
    .catalog-chat-drawer-item .item-preview { font-size: .82rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .catalog-chat-drawer-bubble { max-width: 80%; padding: .5rem .75rem; border-radius: .9rem; font-size: .88rem; line-height: 1.35; }
    .catalog-chat-drawer-bubble time { display: block; margin-top: .2rem; font-size: .7rem; opacity: .7; }
    .catalog-chat-drawer-bubble-visitor { align-self: flex-start; background: var(--color-bg, #f9fafb); border: 1px solid var(--color-border, #e5e7eb); border-bottom-left-radius: .25rem; }
    .catalog-chat-drawer-bubble-admin { align-self: flex-end; background: var(--color-primary, #00b28f); color: #fff; border-bottom-right-radius: .25rem; }
</style>

<script>
(function () {
    var POLL_MS = 8000;
    var drawerEl = document.getElementById('catalogChatDrawer');
    var listView = document.getElementById('catalogChatDrawerList');
    var listEmpty = document.getElementById('catalogChatDrawerListEmpty');
    var listItems = document.getElementById('catalogChatDrawerListItems');
    var threadView = document.getElementById('catalogChatDrawerThread');
    var threadName = document.getElementById('catalogChatDrawerThreadName');
    var threadMeta = document.getElementById('catalogChatDrawerThreadMeta');
    var messagesEl = document.getElementById('catalogChatDrawerMessages');
    var backBtn = document.getElementById('catalogChatDrawerBack');
    var replyForm = document.getElementById('catalogChatDrawerReplyForm');
    var replyInput = document.getElementById('catalogChatDrawerReplyInput');
    var navBadges = document.querySelectorAll('.catalog-chat-nav-badge');

    var activeEmail = null;
    var lastMessageId = 0;
    var drawerOpen = false;
    var drawerPollTimer = null;

    function api(params) {
        var qs = new URLSearchParams(params).toString();
        return fetch('<?= BASE_URL ?>/api/catalog_chat_admin.php?' + qs).then(function (r) { return r.json(); });
    }

    function formatTime(value) {
        var d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d.getTime()) ? '' : d.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function refreshBadge() {
        api({ action: 'unread_count' }).then(function (data) {
            if (!data.ok) return;
            navBadges.forEach(function (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.hidden = false;
                } else {
                    badge.hidden = true;
                }
            });
        }).catch(function () { /* silent */ });
    }

    function renderList(conversations) {
        listItems.innerHTML = '';
        listEmpty.hidden = conversations.length > 0;

        conversations.forEach(function (conv) {
            var item = document.createElement('div');
            item.className = 'list-group-item catalog-chat-drawer-item' + (conv.unread_count > 0 ? ' unread' : '');
            var prefix = conv.last_sender === 'admin' ? 'Vos: ' : '';
            item.innerHTML =
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div class="min-width-0">' +
                        '<div class="item-name">' + escapeHtml(conv.name) + '</div>' +
                        '<div class="item-preview">' + escapeHtml(prefix + conv.last_message) + '</div>' +
                    '</div>' +
                    (conv.unread_count > 0 ? '<span class="badge rounded-pill text-bg-primary flex-shrink-0">' + conv.unread_count + '</span>' : '') +
                '</div>';
            item.addEventListener('click', function () { openThread(conv.email, conv.name, conv.phone); });
            listItems.appendChild(item);
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
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
            bubble.className = 'catalog-chat-drawer-bubble catalog-chat-drawer-bubble-' + (m.sender === 'admin' ? 'admin' : 'visitor');
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
        threadMeta.textContent = email + (phone ? ' · ' + phone : '');
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

    function startDrawerPolling() {
        stopDrawerPolling();
        drawerPollTimer = setInterval(pollActive, POLL_MS);
    }

    function stopDrawerPolling() {
        if (drawerPollTimer) {
            clearInterval(drawerPollTimer);
            drawerPollTimer = null;
        }
    }

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
                    alert(data.error || 'No se pudo enviar la respuesta.');
                }
            })
            .catch(function () { alert('No se pudo enviar la respuesta. Revisá tu conexión.'); })
            .finally(function () { submitBtn.disabled = false; });
    });

    drawerEl.addEventListener('show.bs.offcanvas', function () {
        drawerOpen = true;
        backToList();
        startDrawerPolling();
    });

    drawerEl.addEventListener('hidden.bs.offcanvas', function () {
        drawerOpen = false;
        stopDrawerPolling();
        refreshBadge();
    });

    refreshBadge();
    setInterval(refreshBadge, 20000);
})();
</script>
