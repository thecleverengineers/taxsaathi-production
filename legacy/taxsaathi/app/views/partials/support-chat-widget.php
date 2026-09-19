<?php
/*
 * Floating support widget.
 * Include this partial only for client and partner users.
 */
?>
<style>
    #tsSupportWidget {
        --support-green: #006b5b;
        --support-green-dark: #00564a;
        --support-mint: #35c2a0;
        --support-bg: #f3f5f4;
        --support-text: #17211f;
        --support-muted: #72807c;
        --support-border: rgba(18, 38, 34, 0.10);
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 9998;
        font-family: Inter, Manrope, ui-sans-serif, system-ui, sans-serif;
    }

    #tsSupportWidget * { box-sizing: border-box; }

    #tsSupportLauncher {
        position: relative;
        display: inline-flex;
        width: 58px;
        height: 58px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--support-green), var(--support-green-dark));
        color: #fff;
        cursor: pointer;
        box-shadow: 0 18px 42px rgba(0, 86, 74, .28);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    #tsSupportLauncher:hover {
        transform: translateY(-3px);
        box-shadow: 0 22px 50px rgba(0, 86, 74, .34);
    }

    #tsSupportUnread {
        position: absolute;
        right: -3px;
        top: -3px;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        align-items: center;
        justify-content: center;
        border: 3px solid #fff;
        border-radius: 999px;
        background: #ff6b4a;
        color: #fff;
        font-size: 10px;
        font-weight: 900;
        display: none;
    }

    #tsSupportPanel {
        position: absolute;
        right: 0;
        bottom: 72px;
        width: min(390px, calc(100vw - 24px));
        height: min(620px, calc(100vh - 110px));
        overflow: hidden;
        border: 1px solid var(--support-border);
        border-radius: 30px;
        background: #fff;
        box-shadow: 0 28px 80px rgba(0, 37, 31, .18);
        transform: translateY(18px) scale(.96);
        transform-origin: right bottom;
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease, transform .2s ease;
    }

    #tsSupportPanel.is-open {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0) scale(1);
    }

    .ts-support-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 17px 18px;
        background: linear-gradient(135deg, var(--support-green-dark), var(--support-green));
        color: #fff;
    }

    .ts-support-agent {
        display: flex;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        background: rgba(255,255,255,.14);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.18);
    }

    .ts-support-close {
        margin-left: auto;
        display: inline-flex;
        width: 36px;
        height: 36px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 12px;
        background: rgba(255,255,255,.10);
        color: #fff;
        cursor: pointer;
    }

    .ts-support-meta {
        display: grid;
        grid-template-columns: 1fr 128px;
        gap: 8px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--support-border);
        background: #fbfdfc;
    }

    .ts-support-select {
        width: 100%;
        height: 38px;
        border: 1px solid var(--support-border);
        border-radius: 13px;
        background: #fff;
        padding: 0 11px;
        color: var(--support-text);
        font-size: 12px;
        font-weight: 750;
        outline: none;
    }

    .ts-support-messages {
        height: calc(100% - 230px);
        overflow-y: auto;
        padding: 16px;
        background:
            radial-gradient(circle at top right, rgba(53,194,160,.10), transparent 210px),
            var(--support-bg);
        scrollbar-width: thin;
        scrollbar-color: rgba(0,107,91,.38) transparent;
    }

    .ts-support-empty {
        display: flex;
        min-height: 100%;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: var(--support-muted);
        padding: 26px;
    }

    .ts-support-row {
        display: flex;
        margin-bottom: 12px;
    }

    .ts-support-row.is-requester { justify-content: flex-end; }
    .ts-support-row.is-support { justify-content: flex-start; }
    .ts-support-row.is-system { justify-content: center; }

    .ts-support-bubble {
        max-width: 82%;
        border-radius: 20px;
        padding: 10px 12px;
        font-size: 13px;
        line-height: 1.55;
        word-break: break-word;
    }

    .ts-support-row.is-requester .ts-support-bubble {
        border-bottom-right-radius: 7px;
        background: linear-gradient(135deg, var(--support-green), var(--support-green-dark));
        color: #fff;
        box-shadow: 0 10px 24px rgba(0,86,74,.14);
    }

    .ts-support-row.is-support .ts-support-bubble {
        border: 1px solid var(--support-border);
        border-bottom-left-radius: 7px;
        background: #fff;
        color: var(--support-text);
        box-shadow: 0 8px 20px rgba(0,37,31,.04);
    }

    .ts-support-row.is-system .ts-support-bubble {
        max-width: 95%;
        border-radius: 999px;
        background: rgba(114,128,124,.10);
        color: var(--support-muted);
        font-size: 11px;
        text-align: center;
    }

    .ts-support-sender {
        margin-bottom: 4px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        opacity: .72;
    }

    .ts-support-time {
        margin-top: 5px;
        font-size: 10px;
        opacity: .64;
    }

    .ts-support-file {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        padding: 8px 9px;
        border-radius: 12px;
        background: rgba(255,255,255,.12);
        color: inherit;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
    }

    .ts-support-row.is-support .ts-support-file {
        background: #eef8f5;
        color: var(--support-green);
    }

    .ts-support-composer {
        padding: 11px 12px 12px;
        border-top: 1px solid var(--support-border);
        background: #fff;
    }

    .ts-support-input-wrap {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        border: 1px solid var(--support-border);
        border-radius: 18px;
        background: #f8faf9;
        padding: 8px;
    }

    #tsSupportMessage {
        min-height: 38px;
        max-height: 110px;
        flex: 1;
        resize: none;
        border: 0;
        background: transparent;
        color: var(--support-text);
        font: inherit;
        font-size: 13px;
        line-height: 1.5;
        outline: none;
    }

    .ts-support-attach,
    .ts-support-send {
        display: inline-flex;
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 13px;
        cursor: pointer;
    }

    .ts-support-attach {
        background: #e9f5f1;
        color: var(--support-green);
    }

    .ts-support-send {
        background: var(--support-green);
        color: #fff;
    }

    .ts-support-file-name {
        min-height: 16px;
        margin: 6px 3px 0;
        color: var(--support-muted);
        font-size: 10px;
        font-weight: 700;
    }

    .ts-support-error {
        display: none;
        margin: 0 12px 8px;
        border-radius: 12px;
        background: #fff0ed;
        padding: 8px 10px;
        color: #b13a25;
        font-size: 11px;
        font-weight: 800;
    }

    html[data-theme="dark"] #tsSupportPanel,
    .dark #tsSupportPanel {
        --support-bg: #081712;
        --support-text: #f5fbf8;
        --support-muted: #a8bbb5;
        --support-border: rgba(255,255,255,.10);
        background: #0d211c;
    }

    html[data-theme="dark"] .ts-support-meta,
    html[data-theme="dark"] .ts-support-composer,
    html[data-theme="dark"] .ts-support-select,
    html[data-theme="dark"] .ts-support-row.is-support .ts-support-bubble,
    .dark .ts-support-meta,
    .dark .ts-support-composer,
    .dark .ts-support-select,
    .dark .ts-support-row.is-support .ts-support-bubble {
        background: #0d211c;
        color: var(--support-text);
    }

    html[data-theme="dark"] .ts-support-input-wrap,
    .dark .ts-support-input-wrap {
        background: rgba(255,255,255,.05);
    }

    @media (max-width: 640px) {
        #tsSupportWidget {
            right: 12px;
            bottom: 88px;
        }

        #tsSupportPanel {
            position: fixed;
            inset: 10px 10px 88px 10px;
            width: auto;
            height: auto;
            border-radius: 26px;
            transform-origin: center bottom;
        }
    }
</style>

<div id="tsSupportWidget">
    <button id="tsSupportLauncher" type="button" aria-label="Open support chat" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="25" height="25">
            <path stroke-linecap="round" d="M7 10h10M7 14h6"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-7l-5 3v-3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"/>
        </svg>
        <span id="tsSupportUnread"></span>
    </button>

    <section id="tsSupportPanel" aria-hidden="true">
        <div class="ts-support-head">
            <div class="ts-support-agent">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="22" height="22">
                    <circle cx="12" cy="8" r="4"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 20a8 8 0 0 1 16 0"/>
                </svg>
            </div>
            <div>
                <div style="font-size:14px;font-weight:900;">Tax Saathi Support</div>
                <div style="margin-top:2px;font-size:11px;color:rgba(255,255,255,.72);">
                    Admin, manager and telecaller team
                </div>
            </div>
            <button id="tsSupportClose" class="ts-support-close" type="button" aria-label="Close support chat">×</button>
        </div>

        <div class="ts-support-meta">
            <select id="tsSupportCategory" class="ts-support-select" aria-label="Support category">
                <option value="general">General Support</option>
                <option value="order">Order Support</option>
                <option value="payment">Payment Support</option>
                <option value="documents">Document Support</option>
                <option value="technical">Technical Support</option>
            </select>

            <select id="tsSupportPriority" class="ts-support-select" aria-label="Priority">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
                <option value="low">Low</option>
            </select>
        </div>

        <div id="tsSupportError" class="ts-support-error"></div>
        <div id="tsSupportMessages" class="ts-support-messages">
            <div class="ts-support-empty">
                <div>
                    <div style="font-size:34px;">👋</div>
                    <strong style="display:block;margin-top:9px;color:var(--support-text);">How can we help?</strong>
                    <span style="display:block;margin-top:6px;font-size:12px;line-height:1.65;">
                        Send a message about orders, payments, documents or any technical issue.
                    </span>
                </div>
            </div>
        </div>

        <form id="tsSupportForm" class="ts-support-composer" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input id="tsSupportConversationId" type="hidden" name="conversation_id" value="">
            <input id="tsSupportAttachment" type="file" name="attachment" hidden accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">

            <div class="ts-support-input-wrap">
                <label class="ts-support-attach" for="tsSupportAttachment" title="Attach file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="19" height="19">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8 12 5.2-5.2a3 3 0 1 1 4.24 4.24L10.82 17.7a5 5 0 0 1-7.07-7.07l6.36-6.36"/>
                    </svg>
                </label>

                <textarea id="tsSupportMessage" name="message" placeholder="Type your message..." maxlength="5000"></textarea>

                <button class="ts-support-send" type="submit" aria-label="Send message">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="19" height="19">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4 4 17 8-17 8 4-8Z"/>
                        <path stroke-linecap="round" d="M8 12h13"/>
                    </svg>
                </button>
            </div>

            <div id="tsSupportFileName" class="ts-support-file-name"></div>
        </form>
    </section>
</div>

<script>
(function () {
    const pollUrl = <?= json_encode(base_url('support/chat/poll')) ?>;
    const sendUrl = <?= json_encode(base_url('support/chat/send')) ?>;
    const readUrl = <?= json_encode(base_url('support/chat/read')) ?>;

    const launcher = document.getElementById('tsSupportLauncher');
    const panel = document.getElementById('tsSupportPanel');
    const closeBtn = document.getElementById('tsSupportClose');
    const unreadBadge = document.getElementById('tsSupportUnread');
    const messagesEl = document.getElementById('tsSupportMessages');
    const form = document.getElementById('tsSupportForm');
    const messageInput = document.getElementById('tsSupportMessage');
    const attachmentInput = document.getElementById('tsSupportAttachment');
    const fileNameEl = document.getElementById('tsSupportFileName');
    const conversationInput = document.getElementById('tsSupportConversationId');
    const categoryInput = document.getElementById('tsSupportCategory');
    const priorityInput = document.getElementById('tsSupportPriority');
    const errorEl = document.getElementById('tsSupportError');

    if (!launcher || !panel || !form) return;

    let isOpen = false;
    let conversationId = 0;
    let lastMessageId = 0;
    let sending = false;
    let initialized = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatTime(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return escapeHtml(value);
        return date.toLocaleString([], {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatSize(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return Math.round(value / 1024) + ' KB';
        return (value / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showError(message) {
        if (!message) {
            errorEl.style.display = 'none';
            errorEl.textContent = '';
            return;
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    function setUnread(count) {
        const total = Number(count || 0);
        if (total > 0) {
            unreadBadge.textContent = total > 99 ? '99+' : String(total);
            unreadBadge.style.display = 'inline-flex';
        } else {
            unreadBadge.style.display = 'none';
            unreadBadge.textContent = '';
        }
    }

    function emptyState() {
        messagesEl.innerHTML = `
            <div class="ts-support-empty">
                <div>
                    <div style="font-size:34px;">👋</div>
                    <strong style="display:block;margin-top:9px;color:var(--support-text);">How can we help?</strong>
                    <span style="display:block;margin-top:6px;font-size:12px;line-height:1.65;">
                        Send a message about orders, payments, documents or any technical issue.
                    </span>
                </div>
            </div>
        `;
    }

    function messageHtml(message) {
        const system = message.message_type === 'system';
        const side = system ? 'system' : (message.sender_side === 'support' ? 'support' : 'requester');
        const sender = side === 'support' ? 'Support Team' : (side === 'requester' ? 'You' : '');
        const text = message.message
            ? `<div>${escapeHtml(message.message).replaceAll('\n', '<br>')}</div>`
            : '';

        const attachment = message.attachment_url
            ? `<a class="ts-support-file" href="${escapeHtml(message.attachment_url)}">
                    <span>📎</span>
                    <span style="min-width:0;flex:1;">
                        <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(message.attachment_name || 'Attachment')}</span>
                        <span style="display:block;margin-top:2px;opacity:.7;">${escapeHtml(formatSize(message.attachment_size))}</span>
                    </span>
               </a>`
            : '';

        return `
            <div class="ts-support-row is-${side}" data-message-id="${Number(message.id || 0)}">
                <div class="ts-support-bubble">
                    ${!system ? `<div class="ts-support-sender">${escapeHtml(sender)}</div>` : ''}
                    ${text}
                    ${attachment}
                    <div class="ts-support-time">${formatTime(message.created_at)}</div>
                </div>
            </div>
        `;
    }

    function appendMessages(messages, forceScroll) {
        if (!Array.isArray(messages) || messages.length === 0) return;

        if (!initialized || messagesEl.querySelector('.ts-support-empty')) {
            messagesEl.innerHTML = '';
        }

        const wasNearBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 90;

        messages.forEach((message) => {
            const id = Number(message.id || 0);
            if (id <= lastMessageId && messagesEl.querySelector(`[data-message-id="${id}"]`)) return;
            messagesEl.insertAdjacentHTML('beforeend', messageHtml(message));
            lastMessageId = Math.max(lastMessageId, id);
        });

        initialized = true;

        if (forceScroll || wasNearBottom) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    async function markRead() {
        if (!conversationId) return;

        const data = new FormData(form);
        data.delete('message');
        data.delete('attachment');
        data.set('conversation_id', String(conversationId));

        try {
            await fetch(readUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            setUnread(0);
        } catch (e) {}
    }

    async function poll(forceAll = false) {
        try {
            const url = new URL(pollUrl, window.location.origin);
            url.searchParams.set('after_id', forceAll ? '0' : String(lastMessageId));

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const payload = await response.json();

            if (!payload.ok) return;

            if (payload.conversation) {
                conversationId = Number(payload.conversation.id || 0);
                conversationInput.value = String(conversationId);
                categoryInput.value = payload.conversation.category || 'general';
                priorityInput.value = payload.conversation.priority || 'normal';
            }

            if (forceAll && Array.isArray(payload.messages) && payload.messages.length > 0) {
                messagesEl.innerHTML = '';
                lastMessageId = 0;
                initialized = false;
            }

            appendMessages(payload.messages || [], forceAll || isOpen);

            if (!payload.conversation && !initialized) {
                emptyState();
            }

            if (isOpen) {
                setUnread(0);
                markRead();
            } else {
                setUnread(payload.unread_count || 0);
            }
        } catch (e) {}
    }

    function openPanel() {
        isOpen = true;
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        launcher.setAttribute('aria-expanded', 'true');
        poll(true);
        setTimeout(() => messageInput.focus(), 180);
    }

    function closePanel() {
        isOpen = false;
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        launcher.setAttribute('aria-expanded', 'false');
    }

    launcher.addEventListener('click', () => {
        isOpen ? closePanel() : openPanel();
    });

    closeBtn.addEventListener('click', closePanel);

    attachmentInput.addEventListener('change', () => {
        const file = attachmentInput.files && attachmentInput.files[0];
        fileNameEl.textContent = file ? `${file.name} · ${formatSize(file.size)}` : '';
    });

    messageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending) return;

        const text = messageInput.value.trim();
        const hasFile = attachmentInput.files && attachmentInput.files.length > 0;

        if (!text && !hasFile) {
            showError('Please type a message or attach a file.');
            return;
        }

        sending = true;
        showError('');

        const data = new FormData(form);
        data.set('conversation_id', String(conversationId || 0));
        data.set('category', categoryInput.value);
        data.set('priority', priorityInput.value);

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const payload = await response.json();

            if (!payload.ok) {
                throw new Error(payload.message || 'Unable to send message.');
            }

            conversationId = Number(payload.conversation_id || conversationId);
            conversationInput.value = String(conversationId);

            if (payload.message) {
                appendMessages([payload.message], true);
            }

            messageInput.value = '';
            attachmentInput.value = '';
            fileNameEl.textContent = '';
            setUnread(0);
        } catch (error) {
            showError(error.message || 'Unable to send message.');
        } finally {
            sending = false;
            messageInput.focus();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            poll(false);
        }
    });

    poll(true);
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            poll(false);
        }
    }, isOpen ? 4000 : 8000);
})();
</script>
