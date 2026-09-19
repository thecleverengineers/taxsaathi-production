<?php

declare(strict_types=1);

$notifications = is_array($notifications ?? null) ? $notifications : [];
$unreadCount = (int) ($unreadCount ?? 0);

$severityTone = static function (string $severity): string {
    return match (strtolower($severity)) {
        'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
        'high' => 'border-orange-200 bg-orange-50 text-orange-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'info' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
};

$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d M Y, h:i A', $timestamp) : '-';
};
?>

<section class="space-y-5">
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_20px_55px_rgba(15,23,42,.07)]">
        <div class="flex flex-col gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-800 px-5 py-6 text-white sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.22em] text-cyan-200">Realtime Inbox</div>
                <h2 class="mt-2 text-2xl font-black tracking-[-.04em]">Notifications</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-200/80">Role-aware order, payment, document and workflow updates for your account.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-xs font-bold backdrop-blur">
                    <?= e((string) $unreadCount) ?> unread
                </span>
                <button id="notificationPageMarkAll" type="button" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-white px-4 text-xs font-bold text-slate-900 transition hover:-translate-y-0.5">
                    Mark all as read
                </button>
            </div>
        </div>

        <div id="notificationPageList" class="divide-y divide-slate-100">
            <?php foreach ($notifications as $notification): ?>
                <?php
                    $uid = (string) ($notification['uid'] ?? '');
                    $isRead = (bool) ($notification['is_read'] ?? false);
                    $url = trim((string) ($notification['url'] ?? ''));
                    $severity = (string) ($notification['severity'] ?? 'normal');
                    $payload = json_decode((string) ($notification['payload_json'] ?? ''), true);
                    $payload = is_array($payload) ? $payload : [];
                ?>
                <article
                    class="notification-page-item relative px-5 py-5 transition hover:bg-slate-50 sm:px-7 <?= $isRead ? 'bg-white' : 'bg-brand-50/45' ?>"
                    data-uid="<?= e($uid) ?>"
                    data-url="<?= e($url) ?>"
                    data-read="<?= $isRead ? '1' : '0' ?>"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border <?= e($severityTone($severity)) ?>">
                            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4" stroke-linecap="round"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-extrabold text-slate-900"><?= e((string) ($notification['title'] ?? 'Notification')) ?></h3>
                                        <?php if (!$isRead): ?>
                                            <span class="notification-unread-dot h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-sm leading-6 text-slate-500"><?= e((string) ($notification['message'] ?? '')) ?></p>
                                </div>
                                <time class="shrink-0 text-xs font-medium text-slate-400"><?= e($formatDate($notification['created_at'] ?? '')) ?></time>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.08em] <?= e($severityTone($severity)) ?>">
                                    <?= e($severity) ?>
                                </span>
                                <?php if (!empty($notification['status_label'])): ?>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                        <?= e(ucwords(str_replace('_', ' ', (string) $notification['status_label']))) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($notification['order_no'])): ?>
                                    <span class="text-[11px] font-semibold text-slate-400"><?= e((string) $notification['order_no']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" class="notification-page-toggle inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50">
                                <?= $isRead ? 'Mark unread' : 'Mark read' ?>
                            </button>
                            <?php if ($url !== ''): ?>
                                <a href="<?= e($url) ?>" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800">Open</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if ($notifications === []): ?>
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4" stroke-linecap="round"/></svg>
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900">No notifications yet</h3>
                    <p class="mt-1 text-sm text-slate-500">New role-based updates will appear here in realtime.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(function () {
    const csrf = <?= json_encode(function_exists('csrf_token') ? (string) csrf_token() : '') ?>;
    const markReadUrl = <?= json_encode(base_url('notifications/mark-read')) ?>;
    const markUnreadUrl = <?= json_encode(base_url('notifications/mark-unread')) ?>;
    const markAllUrl = <?= json_encode(base_url('notifications/mark-all-read')) ?>;

    async function send(url, values) {
        const body = new URLSearchParams(values || {});
        if (csrf) { body.set('csrf_token', csrf); body.set('_token', csrf); }
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body
        });
        const data = await response.json();
        if (!response.ok || data.ok === false) throw new Error(data.message || 'Request failed');
        return data;
    }

    document.querySelectorAll('.notification-page-item').forEach(function (item) {
        const button = item.querySelector('.notification-page-toggle');
        if (!button) return;
        button.addEventListener('click', async function () {
            const isRead = item.dataset.read === '1';
            button.disabled = true;
            try {
                await send(isRead ? markUnreadUrl : markReadUrl, {uid:item.dataset.uid});
                item.dataset.read = isRead ? '0' : '1';
                item.classList.toggle('bg-brand-50/45', isRead);
                item.classList.toggle('bg-white', !isRead);
                button.textContent = isRead ? 'Mark read' : 'Mark unread';
                let dot = item.querySelector('.notification-unread-dot');
                if (isRead && !dot) {
                    dot = document.createElement('span');
                    dot.className = 'notification-unread-dot h-2.5 w-2.5 rounded-full bg-brand-500';
                    item.querySelector('h3').after(dot);
                } else if (!isRead && dot) dot.remove();
            } catch (error) { console.warn(error); }
            finally { button.disabled = false; }
        });
    });

    const markAll = document.getElementById('notificationPageMarkAll');
    if (markAll) markAll.addEventListener('click', async function () {
        markAll.disabled = true;
        try {
            await send(markAllUrl, {});
            document.querySelectorAll('.notification-page-item').forEach(function (item) {
                item.dataset.read = '1';
                item.classList.remove('bg-brand-50/45');
                item.classList.add('bg-white');
                const dot = item.querySelector('.notification-unread-dot'); if (dot) dot.remove();
                const button = item.querySelector('.notification-page-toggle'); if (button) button.textContent = 'Mark unread';
            });
        } catch (error) { console.warn(error); }
        finally { markAll.disabled = false; }
    });
})();
</script>
