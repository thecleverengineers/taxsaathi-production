<?php

declare(strict_types=1);

$roles = is_array($roles ?? null) ? $roles : [];
$users = is_array($users ?? null) ? $users : [];
?>

<section class="space-y-6">
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_20px_55px_rgba(15,23,42,.07)]">
        <div class="bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-800 px-5 py-6 text-white sm:px-7">
            <div class="text-[10px] font-bold uppercase tracking-[.22em] text-cyan-200">Admin / Manager</div>
            <h2 class="mt-2 text-2xl font-black tracking-[-.04em]">Push Notification Manager</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-200/80">Send one realtime notification to selected roles, individual users, or an order context. Every recipient receives a separate inbox record and eligible devices receive FCM push.</p>
        </div>

        <form id="pushComposer" class="grid gap-6 p-5 sm:p-7 xl:grid-cols-[1.15fr_.85fr]">
            <div class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-2 sm:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Title</span>
                        <input name="title" required maxlength="255" class="min-h-12 rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Important order update">
                    </label>

                    <label class="grid gap-2 sm:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Message</span>
                        <textarea name="message" required rows="5" class="rounded-2xl border border-slate-200 px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Write the notification message..."></textarea>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Severity</span>
                        <select name="severity" class="min-h-12 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-800 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                            <option value="normal">Normal</option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Status label</span>
                        <input name="status_label" maxlength="80" class="min-h-12 rounded-2xl border border-slate-200 px-4 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="pending_review">
                    </label>

                    <label class="grid gap-2 sm:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Open URL</span>
                        <input name="url" maxlength="500" value="<?= e(base_url('notifications')) ?>" class="min-h-12 rounded-2xl border border-slate-200 px-4 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="/notifications">
                    </label>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Order context</div>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Optional. Supplying an order automatically resolves its client, partner and assigned executive according to the selected context audience.</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <input name="order_id" type="number" min="0" class="min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="Regular order ID">
                        <input name="partner_order_id" type="number" min="0" class="min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="Partner order ID">
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <?php foreach (['client', 'partner', 'executive'] as $contextRole): ?>
                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="context_audience[]" value="<?= e($contextRole) ?>" class="h-4 w-4 rounded border-slate-300 text-brand-600" checked>
                                <?= e(ucfirst($contextRole)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Send to roles</div>
                            <p class="mt-1 text-xs text-slate-400">Resolved through user_roles + roles.</p>
                        </div>
                        <button type="button" id="selectAllRoles" class="text-[11px] font-bold text-brand-700">Select all</button>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <?php foreach ($roles as $role): ?>
                            <?php $slug = strtolower(trim((string) ($role['slug'] ?? ''))); ?>
                            <label class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <input type="checkbox" name="roles[]" value="<?= e($slug) ?>" class="role-checkbox h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span><?= e((string) ($role['name'] ?? ucfirst($slug))) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 p-4">
                    <div class="text-xs font-bold uppercase tracking-[.1em] text-slate-500">Individual users</div>
                    <p class="mt-1 text-xs text-slate-400">Optional. Choose specific users in addition to roles.</p>
                    <select name="user_ids[]" multiple size="10" class="mt-4 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                        <?php foreach ($users as $row): ?>
                            <option value="<?= e((string) ($row['id'] ?? 0)) ?>">
                                <?= e((string) ($row['name'] ?? ('User #' . ($row['id'] ?? '')))) ?><?= !empty($row['role_names']) ? ' · ' . e((string) $row['role_names']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <input type="checkbox" name="include_actor" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                    <span><strong class="block text-sm text-slate-800">Include my account</strong><span class="mt-1 block text-xs leading-5 text-slate-500">By default, the sender is excluded from the recipient list.</span></span>
                </label>

                <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-extrabold text-white shadow-[0_15px_35px_rgba(15,23,42,.2)] transition hover:-translate-y-0.5 hover:bg-slate-800">
                    Send realtime notification
                </button>
                <div id="pushComposerResult" class="hidden rounded-2xl border px-4 py-3 text-sm"></div>
            </div>
        </form>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('pushComposer');
    const result = document.getElementById('pushComposerResult');
    const selectAll = document.getElementById('selectAllRoles');
    const csrf = <?= json_encode(function_exists('csrf_token') ? (string) csrf_token() : '') ?>;
    const endpoint = <?= json_encode(base_url('push/send')) ?>;

    if (selectAll) selectAll.addEventListener('click', function () {
        const boxes = Array.from(document.querySelectorAll('.role-checkbox'));
        const shouldCheck = boxes.some((box) => !box.checked);
        boxes.forEach((box) => { box.checked = shouldCheck; });
        selectAll.textContent = shouldCheck ? 'Clear all' : 'Select all';
    });

    if (!form) return;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        result.classList.add('hidden');

        const body = new URLSearchParams();
        const data = new FormData(form);
        data.forEach((value, key) => body.append(key, String(value)));
        if (csrf) { body.set('csrf_token', csrf); body.set('_token', csrf); }

        try {
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                body
            });
            const payload = await response.json();
            if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Unable to send notification.');
            const summary = payload.result || {};
            result.className = 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700';
            result.textContent = 'Created ' + (summary.created_count || 0) + ' notification(s) for ' + (summary.recipient_count || 0) + ' recipient(s).';
            result.classList.remove('hidden');
        } catch (error) {
            result.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700';
            result.textContent = error.message || 'Unable to send notification.';
            result.classList.remove('hidden');
        } finally {
            submit.disabled = false;
        }
    });
})();
</script>
