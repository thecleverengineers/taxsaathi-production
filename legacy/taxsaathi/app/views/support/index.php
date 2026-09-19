<?php
declare(strict_types=1);

$conversations = is_array($conversations ?? null) ? array_values($conversations) : [];
$selectedConversation = is_array($selectedConversation ?? null) ? $selectedConversation : null;
$messages = is_array($messages ?? null) ? array_values($messages) : [];
$stats = is_array($stats ?? null) ? $stats : [];
$supportStaff = is_array($supportStaff ?? null) ? array_values($supportStaff) : [];
$currentSupportUserId = (int) ($currentSupportUserId ?? 0);

$statusTone = static function (string $status): string {
    return match (strtolower($status)) {
        'open' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'resolved' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'closed' => 'bg-slate-100 text-slate-600 ring-slate-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
};

$priorityTone = static function (string $priority): string {
    return match (strtolower($priority)) {
        'urgent' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'high' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'low' => 'bg-slate-100 text-slate-600 ring-slate-200',
        default => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    };
};
?>

<style>
    .support-page {
        --sp-card: #fff;
        --sp-text: #17211f;
        --sp-muted: #71807c;
        --sp-border: rgba(18, 38, 34, .09);
        --sp-green: #006b5b;
        --sp-bg: #f3f5f4;
    }

    html[data-theme="dark"] .support-page {
        --sp-card: #0d211c;
        --sp-text: #f4fbf8;
        --sp-muted: #a8bbb5;
        --sp-border: rgba(255,255,255,.10);
        --sp-bg: #071511;
    }

    .support-card {
        border: 1px solid var(--sp-border);
        background: var(--sp-card);
        box-shadow: 0 18px 45px rgba(0,37,31,.055);
    }

    .support-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(0,107,91,.34) transparent;
    }

    .support-conversation-item.is-active {
        border-color: rgba(0,107,91,.24);
        background: #edf8f4;
        box-shadow: 0 12px 26px rgba(0,107,91,.08);
    }

    html[data-theme="dark"] .support-conversation-item.is-active {
        background: rgba(53,194,160,.11);
    }

    .support-message {
        display: flex;
        margin-bottom: 14px;
    }

    .support-message.requester { justify-content: flex-start; }
    .support-message.support { justify-content: flex-end; }
    .support-message.system { justify-content: center; }

    .support-bubble {
        max-width: 78%;
        border-radius: 20px;
        padding: 11px 13px;
        line-height: 1.55;
        font-size: 13px;
    }

    .support-message.requester .support-bubble {
        border: 1px solid var(--sp-border);
        border-bottom-left-radius: 7px;
        background: var(--sp-card);
        color: var(--sp-text);
    }

    .support-message.support .support-bubble {
        border-bottom-right-radius: 7px;
        background: linear-gradient(135deg, #007a66, #00564a);
        color: #fff;
    }

    .support-message.system .support-bubble {
        max-width: 95%;
        border-radius: 999px;
        background: rgba(113,128,124,.10);
        color: var(--sp-muted);
        font-size: 11px;
        text-align: center;
    }

    .support-file {
        display: flex;
        margin-top: 8px;
        align-items: center;
        gap: 8px;
        border-radius: 12px;
        padding: 8px 9px;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
    }

    .support-message.requester .support-file {
        background: #edf8f4;
        color: #006b5b;
    }

    .support-message.support .support-file {
        background: rgba(255,255,255,.13);
        color: #fff;
    }
</style>

<section class="support-page space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Support Operations</p>
            <h1 class="mt-2 text-3xl font-black tracking-[-0.045em] text-[var(--sp-text)]">Customer Support Inbox</h1>
            <p class="mt-2 max-w-2xl text-sm leading-7 text-[var(--sp-muted)]">
                Admin, manager and telecaller users can reply, assign, prioritize and resolve client or partner conversations.
            </p>
        </div>

        <button id="supportRefresh" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#006b5b] px-5 text-sm font-black text-white shadow-[0_14px_26px_rgba(0,107,91,.18)]">
            Refresh Inbox
        </button>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <?php foreach ([
            'all' => 'All',
            'open' => 'Open',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
            'unread' => 'Unread',
        ] as $key => $label): ?>
            <div class="support-card rounded-[22px] p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]"><?= e($label) ?></p>
                <strong id="supportStat-<?= e($key) ?>" class="mt-2 block text-2xl font-black tracking-[-0.05em] text-[var(--sp-text)]">
                    <?= e((string) ($stats[$key] ?? 0)) ?>
                </strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid min-h-[690px] gap-4 xl:grid-cols-[340px_minmax(0,1fr)_300px]">
        <aside class="support-card overflow-hidden rounded-[28px]">
            <div class="border-b p-4" style="border-color:var(--sp-border)">
                <input id="supportSearch" class="h-11 w-full rounded-2xl border bg-[var(--sp-bg)] px-4 text-sm outline-none" style="border-color:var(--sp-border);color:var(--sp-text)" placeholder="Search conversations...">
                <select id="supportStatusFilter" class="mt-2 h-10 w-full rounded-2xl border bg-[var(--sp-card)] px-3 text-xs font-bold outline-none" style="border-color:var(--sp-border);color:var(--sp-text)">
                    <option value="all">All statuses</option>
                    <option value="open">Open</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>

            <div id="supportConversationList" class="support-scroll max-h-[610px] overflow-y-auto p-3">
                <?php foreach ($conversations as $conversation): ?>
                    <?php
                        $active = $selectedConversation && (int) $selectedConversation['id'] === (int) $conversation['id'];
                    ?>
                    <button
                        type="button"
                        class="support-conversation-item <?= $active ? 'is-active' : '' ?> mb-2 w-full rounded-[20px] border p-3 text-left transition hover:-translate-y-0.5"
                        style="border-color:var(--sp-border)"
                        data-conversation-id="<?= e((string) $conversation['id']) ?>"
                        data-status="<?= e((string) $conversation['status']) ?>"
                        data-search="<?= e(strtolower(
                            (string) $conversation['requester_name'] . ' '
                            . (string) $conversation['requester_email'] . ' '
                            . (string) $conversation['requester_phone'] . ' '
                            . (string) $conversation['subject']
                        )) ?>"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-sm font-black text-emerald-700">
                                <?= e(strtoupper(substr((string) $conversation['requester_name'], 0, 1))) ?>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <strong class="truncate text-sm font-black text-[var(--sp-text)]">
                                        <?= e((string) $conversation['requester_name']) ?>
                                    </strong>
                                    <?php if ((int) $conversation['support_unread_count'] > 0): ?>
                                        <span class="ml-auto inline-flex min-w-5 h-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[10px] font-black text-white">
                                            <?= e((string) $conversation['support_unread_count']) ?>
                                        </span>
                                    <?php endif; ?>
                                </span>

                                <span class="mt-1 block truncate text-xs font-bold text-[var(--sp-muted)]">
                                    <?= e((string) $conversation['subject']) ?>
                                </span>
                                <span class="mt-1 block truncate text-[11px] text-[var(--sp-muted)]">
                                    <?= e((string) $conversation['last_message_preview']) ?>
                                </span>

                                <span class="mt-2 flex flex-wrap gap-1.5">
                                    <span class="inline-flex rounded-full px-2 py-1 text-[9px] font-black uppercase tracking-[0.10em] ring-1 <?= e($statusTone((string) $conversation['status'])) ?>">
                                        <?= e((string) $conversation['status']) ?>
                                    </span>
                                    <span class="inline-flex rounded-full px-2 py-1 text-[9px] font-black uppercase tracking-[0.10em] ring-1 <?= e($priorityTone((string) $conversation['priority'])) ?>">
                                        <?= e((string) $conversation['priority']) ?>
                                    </span>
                                </span>
                            </span>
                        </div>
                    </button>
                <?php endforeach; ?>

                <?php if ($conversations === []): ?>
                    <div class="py-16 text-center text-sm font-semibold text-[var(--sp-muted)]">
                        No support conversations yet.
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="support-card flex min-w-0 flex-col overflow-hidden rounded-[28px]">
            <header id="supportChatHeader" class="border-b px-5 py-4" style="border-color:var(--sp-border)">
                <?php if ($selectedConversation): ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0">
                            <h2 id="supportSelectedName" class="truncate text-lg font-black text-[var(--sp-text)]">
                                <?= e((string) $selectedConversation['requester_name']) ?>
                            </h2>
                            <p id="supportSelectedSubject" class="mt-1 text-xs font-bold text-[var(--sp-muted)]">
                                <?= e((string) $selectedConversation['subject']) ?>
                            </p>
                        </div>
                        <span id="supportSelectedStatus" class="ml-auto inline-flex rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.12em] ring-1 <?= e($statusTone((string) $selectedConversation['status'])) ?>">
                            <?= e((string) $selectedConversation['status']) ?>
                        </span>
                    </div>
                <?php else: ?>
                    <h2 class="text-lg font-black text-[var(--sp-text)]">Select a conversation</h2>
                <?php endif; ?>
            </header>

            <div id="supportMessages" class="support-scroll flex-1 overflow-y-auto bg-[var(--sp-bg)] p-5">
                <?php foreach ($messages as $message): ?>
                    <?php
                        $side = $message['message_type'] === 'system'
                            ? 'system'
                            : ($message['sender_side'] === 'support' ? 'support' : 'requester');
                    ?>
                    <div class="support-message <?= e($side) ?>" data-message-id="<?= e((string) $message['id']) ?>">
                        <div class="support-bubble">
                            <?php if ($side !== 'system'): ?>
                                <div class="mb-1 text-[10px] font-black uppercase tracking-[0.12em] opacity-70">
                                    <?= e((string) $message['sender_name']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($message['message'] !== ''): ?>
                                <div><?= nl2br(e((string) $message['message'])) ?></div>
                            <?php endif; ?>

                            <?php if ($message['attachment_url'] !== ''): ?>
                                <a href="<?= e((string) $message['attachment_url']) ?>" class="support-file">
                                    <span>📎</span>
                                    <span><?= e((string) $message['attachment_name']) ?></span>
                                </a>
                            <?php endif; ?>

                            <div class="mt-1 text-[10px] opacity-60"><?= e((string) $message['created_at']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$selectedConversation): ?>
                    <div class="flex h-full items-center justify-center text-center text-sm font-semibold text-[var(--sp-muted)]">
                        Choose a conversation from the left.
                    </div>
                <?php endif; ?>
            </div>

            <form id="supportReplyForm" class="border-t p-4" style="border-color:var(--sp-border)" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input id="supportReplyConversationId" type="hidden" name="conversation_id" value="<?= e((string) ($selectedConversation['id'] ?? '')) ?>">
                <input id="supportReplyAttachment" type="file" name="attachment" hidden accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">

                <div class="flex items-end gap-2 rounded-[20px] border bg-[var(--sp-bg)] p-2" style="border-color:var(--sp-border)">
                    <label for="supportReplyAttachment" class="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">📎</label>
                    <textarea id="supportReplyMessage" name="message" class="max-h-32 min-h-10 flex-1 resize-none bg-transparent px-2 py-2 text-sm outline-none" style="color:var(--sp-text)" placeholder="Reply as support team..." maxlength="5000"></textarea>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-2xl bg-[#006b5b] px-5 text-xs font-black uppercase tracking-[0.14em] text-white">
                        Send
                    </button>
                </div>
                <div id="supportReplyFileName" class="mt-2 text-[11px] font-bold text-[var(--sp-muted)]"></div>
                <div id="supportReplyError" class="mt-2 hidden rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700"></div>
            </form>
        </main>

        <aside class="support-card rounded-[28px] p-5">
            <h3 class="text-base font-black text-[var(--sp-text)]">Conversation Details</h3>

            <div id="supportDetailPanel" class="mt-5 space-y-5">
                <?php if ($selectedConversation): ?>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]">Contact</p>
                        <p id="supportDetailEmail" class="mt-2 break-all text-sm font-bold text-[var(--sp-text)]"><?= e((string) $selectedConversation['requester_email']) ?></p>
                        <p id="supportDetailPhone" class="mt-1 text-sm font-bold text-[var(--sp-text)]"><?= e((string) $selectedConversation['requester_phone']) ?></p>
                    </div>

                    <form id="supportStatusForm" class="space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e((string) $selectedConversation['id']) ?>">
                        <input type="hidden" name="action" value="status">
                        <label class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]">Status</label>
                        <select name="status" class="h-11 w-full rounded-2xl border bg-[var(--sp-card)] px-3 text-sm font-bold outline-none" style="border-color:var(--sp-border);color:var(--sp-text)">
                            <?php foreach (['open', 'pending', 'resolved', 'closed'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $selectedConversation['status'] === $status ? 'selected' : '' ?>>
                                    <?= e(ucwords($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <form id="supportPriorityForm" class="space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e((string) $selectedConversation['id']) ?>">
                        <input type="hidden" name="action" value="priority">
                        <label class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]">Priority</label>
                        <select name="priority" class="h-11 w-full rounded-2xl border bg-[var(--sp-card)] px-3 text-sm font-bold outline-none" style="border-color:var(--sp-border);color:var(--sp-text)">
                            <?php foreach (['low', 'normal', 'high', 'urgent'] as $priority): ?>
                                <option value="<?= e($priority) ?>" <?= $selectedConversation['priority'] === $priority ? 'selected' : '' ?>>
                                    <?= e(ucwords($priority)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <form id="supportAssignForm" class="space-y-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= e((string) $selectedConversation['id']) ?>">
                        <input type="hidden" name="action" value="assign">
                        <label class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]">Assigned Support</label>
                        <select name="assigned_to" class="h-11 w-full rounded-2xl border bg-[var(--sp-card)] px-3 text-sm font-bold outline-none" style="border-color:var(--sp-border);color:var(--sp-text)">
                            <option value="">Unassigned</option>
                            <option value="self">Assign to me</option>
                            <?php foreach ($supportStaff as $staff): ?>
                                <option value="<?= e((string) $staff['id']) ?>" <?= (int) ($selectedConversation['assigned_to_user_id'] ?? 0) === (int) $staff['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $staff['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div class="rounded-[20px] bg-[var(--sp-bg)] p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--sp-muted)]">Current assignment</p>
                        <strong id="supportAssignedName" class="mt-2 block text-sm font-black text-[var(--sp-text)]">
                            <?= e((string) ($selectedConversation['assigned_to_name'] ?: 'Unassigned')) ?>
                        </strong>
                    </div>
                <?php else: ?>
                    <p class="text-sm font-semibold text-[var(--sp-muted)]">Select a conversation to view details.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<script>
(function () {
    const pollUrl = <?= json_encode(base_url('support/poll')) ?>;
    const replyUrl = <?= json_encode(base_url('support/reply')) ?>;
    const updateUrl = <?= json_encode(base_url('support/update')) ?>;

    let selectedId = Number(<?= json_encode((int) ($selectedConversation['id'] ?? 0)) ?>);
    let lastMessageId = Number(<?= json_encode((int) (($messages !== [] ? end($messages)['id'] : 0))) ?>);

    const listEl = document.getElementById('supportConversationList');
    const messagesEl = document.getElementById('supportMessages');
    const searchEl = document.getElementById('supportSearch');
    const statusFilterEl = document.getElementById('supportStatusFilter');
    const refreshBtn = document.getElementById('supportRefresh');
    const replyForm = document.getElementById('supportReplyForm');
    const replyMessage = document.getElementById('supportReplyMessage');
    const replyConversationId = document.getElementById('supportReplyConversationId');
    const replyAttachment = document.getElementById('supportReplyAttachment');
    const replyFileName = document.getElementById('supportReplyFileName');
    const replyError = document.getElementById('supportReplyError');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatSize(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return Math.round(value / 1024) + ' KB';
        return (value / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function messageHtml(message) {
        const side = message.message_type === 'system'
            ? 'system'
            : (message.sender_side === 'support' ? 'support' : 'requester');

        const attachment = message.attachment_url
            ? `<a href="${escapeHtml(message.attachment_url)}" class="support-file">
                    <span>📎</span>
                    <span>${escapeHtml(message.attachment_name || 'Attachment')} · ${escapeHtml(formatSize(message.attachment_size))}</span>
               </a>`
            : '';

        return `
            <div class="support-message ${side}" data-message-id="${Number(message.id || 0)}">
                <div class="support-bubble">
                    ${side !== 'system' ? `<div class="mb-1 text-[10px] font-black uppercase tracking-[0.12em] opacity-70">${escapeHtml(message.sender_name)}</div>` : ''}
                    ${message.message ? `<div>${escapeHtml(message.message).replaceAll('\n', '<br>')}</div>` : ''}
                    ${attachment}
                    <div class="mt-1 text-[10px] opacity-60">${escapeHtml(message.created_at || '')}</div>
                </div>
            </div>
        `;
    }

    function appendMessages(messages, forceScroll = false) {
        if (!Array.isArray(messages) || messages.length === 0) return;
        const nearBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 100;

        messages.forEach((message) => {
            const id = Number(message.id || 0);
            if (messagesEl.querySelector(`[data-message-id="${id}"]`)) return;
            messagesEl.insertAdjacentHTML('beforeend', messageHtml(message));
            lastMessageId = Math.max(lastMessageId, id);
        });

        if (forceScroll || nearBottom) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function filterList() {
        const query = String(searchEl.value || '').trim().toLowerCase();
        const status = statusFilterEl.value;

        listEl.querySelectorAll('.support-conversation-item').forEach((item) => {
            const matchesSearch = !query || String(item.dataset.search || '').includes(query);
            const matchesStatus = status === 'all' || item.dataset.status === status;
            item.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    }

    searchEl?.addEventListener('input', filterList);
    statusFilterEl?.addEventListener('change', filterList);

    listEl?.addEventListener('click', (event) => {
        const button = event.target.closest('.support-conversation-item');
        if (!button) return;

        selectedId = Number(button.dataset.conversationId || 0);
        lastMessageId = 0;

        listEl.querySelectorAll('.support-conversation-item').forEach((item) => item.classList.remove('is-active'));
        button.classList.add('is-active');

        if (replyConversationId) replyConversationId.value = String(selectedId);
        messagesEl.innerHTML = '<div class="flex h-full items-center justify-center text-sm font-semibold text-[var(--sp-muted)]">Loading conversation...</div>';
        poll(true);
    });

    async function poll(forceAll = false) {
        try {
            const url = new URL(pollUrl, window.location.origin);
            url.searchParams.set('conversation_id', String(selectedId || 0));
            url.searchParams.set('after_id', forceAll ? '0' : String(lastMessageId));

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const payload = await response.json();
            if (!payload.ok) return;

            if (forceAll && payload.conversation) {
                messagesEl.innerHTML = '';
                lastMessageId = 0;
            }

            if (payload.conversation) {
                selectedId = Number(payload.conversation.id || selectedId);
                if (replyConversationId) replyConversationId.value = String(selectedId);

                const name = document.getElementById('supportSelectedName');
                const subject = document.getElementById('supportSelectedSubject');
                const email = document.getElementById('supportDetailEmail');
                const phone = document.getElementById('supportDetailPhone');
                const assigned = document.getElementById('supportAssignedName');

                if (name) name.textContent = payload.conversation.requester_name || 'User';
                if (subject) subject.textContent = payload.conversation.subject || 'Support';
                if (email) email.textContent = payload.conversation.requester_email || '—';
                if (phone) phone.textContent = payload.conversation.requester_phone || '—';
                if (assigned) assigned.textContent = payload.conversation.assigned_to_name || 'Unassigned';
            }

            appendMessages(payload.messages || [], forceAll);

            Object.entries(payload.stats || {}).forEach(([key, value]) => {
                const el = document.getElementById(`supportStat-${key}`);
                if (el) el.textContent = String(value);
            });
        } catch (e) {}
    }

    refreshBtn?.addEventListener('click', () => poll(true));

    replyAttachment?.addEventListener('change', () => {
        const file = replyAttachment.files && replyAttachment.files[0];
        replyFileName.textContent = file ? `${file.name} · ${formatSize(file.size)}` : '';
    });

    replyMessage?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            replyForm.requestSubmit();
        }
    });

    replyForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedId) return;

        const data = new FormData(replyForm);
        data.set('conversation_id', String(selectedId));

        try {
            replyError.classList.add('hidden');

            const response = await fetch(replyUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const payload = await response.json();

            if (!payload.ok) throw new Error(payload.message || 'Unable to send reply.');

            if (payload.message) appendMessages([payload.message], true);

            replyMessage.value = '';
            replyAttachment.value = '';
            replyFileName.textContent = '';
        } catch (error) {
            replyError.textContent = error.message || 'Unable to send reply.';
            replyError.classList.remove('hidden');
        }
    });

    async function updateConversation(form) {
        if (!selectedId) return;

        const data = new FormData(form);
        data.set('conversation_id', String(selectedId));

        try {
            const response = await fetch(updateUrl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const payload = await response.json();

            if (!payload.ok) throw new Error(payload.message || 'Unable to update conversation.');
            poll(true);
        } catch (error) {
            alert(error.message || 'Unable to update conversation.');
        }
    }

    ['supportStatusForm', 'supportPriorityForm', 'supportAssignForm'].forEach((id) => {
        const form = document.getElementById(id);
        form?.querySelector('select')?.addEventListener('change', () => updateConversation(form));
    });

    if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;

    setInterval(() => {
        if (document.visibilityState === 'visible') poll(false);
    }, 4000);
})();
</script>
