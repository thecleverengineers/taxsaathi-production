<?php
/** @var array $stats */
/** @var array $users */
/** @var array $roles */

$stats = is_array($stats ?? null) ? array_merge([
    'total_users' => 0,
    'active_users' => 0,
    'phone_verified' => 0,
    'email_verified' => 0,
], $stats) : [
    'total_users' => 0,
    'active_users' => 0,
    'phone_verified' => 0,
    'email_verified' => 0,
];

$users = is_array($users ?? null) ? array_values($users) : [];
$roles = is_array($roles ?? null) ? array_values($roles) : [];

if (!function_exists('user_initials')) {
    function user_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('client_label_from_user')) {
    function client_label_from_user(array $user): string
    {
        $company = trim((string) ($user['client_company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        $name = trim((string) ($user['client_name'] ?? ''));
        return $name !== '' ? $name : '—';
    }
}
?>

<style>
    .ts-users-page {
        --ts-card-bg: #ffffff;
        --ts-card-soft: #f8fafc;
        --ts-text: #0f172a;
        --ts-muted: #64748b;
        --ts-border: #e2e8f0;
        --ts-blue: #075b9a;
        --ts-blue-dark: #053b73;
    }

    html[data-theme="dark"] .ts-users-page {
        --ts-card-bg: rgba(13, 27, 46, 0.94);
        --ts-card-soft: rgba(255, 255, 255, 0.06);
        --ts-text: #f8fafc;
        --ts-muted: #cbd5e1;
        --ts-border: rgba(255, 255, 255, 0.10);
        color: #dbe7f3;
    }

    .ts-users-page .ts-title {
        color: var(--ts-text);
    }

    .ts-users-page .ts-muted {
        color: var(--ts-muted);
    }

    .ts-users-page .ts-flat-hero,
    .ts-users-page .ts-flat-section,
    .ts-users-page .ts-flat-stat,
    .ts-users-page .ts-user-row {
        border-color: var(--ts-border);
    }

    .ts-users-page .ts-premium-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(14, 165, 198, 0.58) transparent;
    }

    .ts-users-page .ts-premium-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .ts-users-page .ts-premium-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .ts-users-page .ts-premium-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, rgba(14,165,198,0.72), rgba(45,140,255,0.62));
        border-radius: 999px;
    }

    html[data-theme="dark"] .ts-users-page .bg-white,
    html[data-theme="dark"] .ts-users-page .bg-white\/80,
    html[data-theme="dark"] .ts-users-page .bg-white\/75 {
        background-color: var(--ts-card-bg) !important;
    }

    html[data-theme="dark"] .ts-users-page .bg-slate-50,
    html[data-theme="dark"] .ts-users-page .bg-slate-50\/40,
    html[data-theme="dark"] .ts-users-page .bg-slate-50\/60,
    html[data-theme="dark"] .ts-users-page .bg-slate-50\/70,
    html[data-theme="dark"] .ts-users-page .bg-slate-50\/80,
    html[data-theme="dark"] .ts-users-page .bg-slate-100 {
        background-color: var(--ts-card-soft) !important;
    }

    html[data-theme="dark"] .ts-users-page .border-slate-100,
    html[data-theme="dark"] .ts-users-page .border-slate-200,
    html[data-theme="dark"] .ts-users-page .border-slate-200\/80,
    html[data-theme="dark"] .ts-users-page .border-white\/70 {
        border-color: var(--ts-border) !important;
    }

    html[data-theme="dark"] .ts-users-page .text-slate-900,
    html[data-theme="dark"] .ts-users-page .text-slate-800,
    html[data-theme="dark"] .ts-users-page .text-slate-700 {
        color: #f8fafc !important;
    }

    html[data-theme="dark"] .ts-users-page .text-slate-600,
    html[data-theme="dark"] .ts-users-page .text-slate-500,
    html[data-theme="dark"] .ts-users-page .text-slate-400,
    html[data-theme="dark"] .ts-users-page .text-slate-300 {
        color: #cbd5e1 !important;
    }

    html[data-theme="dark"] .ts-users-page input,
    html[data-theme="dark"] .ts-users-page select,
    html[data-theme="dark"] .ts-users-page textarea {
        background-color: rgba(255,255,255,0.07) !important;
        border-color: rgba(255,255,255,0.12) !important;
        color: #f8fafc !important;
    }

    html[data-theme="dark"] .ts-users-page input::placeholder {
        color: rgba(203, 213, 225, 0.62) !important;
    }

    html[data-theme="dark"] .ts-users-page option {
        background: #0d1b2e;
        color: #f8fafc;
    }

    html[data-theme="dark"] .ts-users-page .hover\:bg-slate-50:hover,
    html[data-theme="dark"] .ts-users-page .hover\:bg-slate-50\/70:hover {
        background-color: rgba(255,255,255,0.08) !important;
    }

    html[data-theme="dark"] .ts-users-page .ts-flat-hero,
    html[data-theme="dark"] .ts-users-page .ts-flat-section,
    html[data-theme="dark"] .ts-users-page .ts-flat-stat,
    html[data-theme="dark"] .ts-users-page .ts-user-row {
        background: transparent !important;
        border-color: rgba(255,255,255,0.10) !important;
    }

    html[data-theme="dark"] .ts-users-page .shadow-sm,
    html[data-theme="dark"] .ts-users-page .shadow-\[0_18px_40px_rgba\(15\,23\,42\,0\.06\)\],
    html[data-theme="dark"] .ts-users-page .shadow-\[0_24px_60px_rgba\(15\,23\,42\,0\.08\)\],
    html[data-theme="dark"] .ts-users-page .shadow-\[0_14px_34px_rgba\(15\,23\,42\,0\.05\)\],
    html[data-theme="dark"] .ts-users-page .shadow-\[0_16px_40px_rgba\(15\,23\,42\,0\.05\)\] {
        box-shadow: none !important;
    }
</style>



<div class="ts-users-page space-y-5">
    <section class="ts-flat-hero border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-5 py-4 sm:py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-sky-700">
                        Admin Panel
                    </span>
                    <h1 class="mt-3 text-[1.75rem] font-bold tracking-[-0.04em] text-slate-900 ts-title md:text-[2rem]">
                        User Management
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 ts-muted">
                        Client record is created first and then linked automatically to the user account.
                    </p>
                </div>

                <button
                    type="button"
                    onclick="toggleAddUserAccordion()"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#075B9A] px-5 text-sm font-semibold text-white transition hover:bg-[#053B73]"
                >
                    <span class="mr-2 text-base leading-none">+</span>
                    <span id="top-add-user-label">Add User</span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-0 divide-y divide-slate-200 border-y border-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0 xl:grid-cols-4">
                <div class="ts-flat-stat bg-transparent p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Users</p>
                    <h3 class="mt-3 text-3xl font-bold tracking-[-0.05em] text-slate-900"><?= e((string) ($stats['total_users'] ?? 0)) ?></h3>
                    <p class="mt-2 text-sm text-slate-500">Total user accounts</p>
                </div>

                <div class="ts-flat-stat bg-transparent p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Active</p>
                    <h3 class="mt-3 text-3xl font-bold tracking-[-0.05em] text-slate-900"><?= e((string) ($stats['active_users'] ?? 0)) ?></h3>
                    <p class="mt-2 text-sm text-slate-500">Currently active users</p>
                </div>

                <div class="ts-flat-stat bg-transparent p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Phone Verified</p>
                    <h3 class="mt-3 text-3xl font-bold tracking-[-0.05em] text-slate-900"><?= e((string) ($stats['phone_verified'] ?? 0)) ?></h3>
                    <p class="mt-2 text-sm text-slate-500">Users with verified phone</p>
                </div>

                <div class="ts-flat-stat bg-transparent p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Email Verified</p>
                    <h3 class="mt-3 text-3xl font-bold tracking-[-0.05em] text-slate-900"><?= e((string) ($stats['email_verified'] ?? 0)) ?></h3>
                    <p class="mt-2 text-sm text-slate-500">Users with verified email</p>
                </div>
            </div>
        </div>
    </section>

    <section class="ts-flat-section border-y border-slate-200 bg-transparent">
        <div class="flex flex-col gap-4 border-b border-slate-200 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Directory</p>
                <h2 class="mt-1 text-[1.2rem] font-bold tracking-[-0.04em] text-slate-900 ts-title">Manage Users</h2>
            </div>

            <button
                type="button"
                onclick="toggleAddUserAccordion()"
                class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-[12px] font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                <span id="section-add-user-label">Add User</span>
                <svg id="add-user-icon" class="ml-2 h-4 w-4 transition-transform duration-300" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div id="add-user-accordion" class="hidden border-b border-slate-200 bg-transparent py-5">
            <div class="py-1">
                <div class="mb-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Create User</p>
                    <h3 class="mt-1 text-[1.05rem] font-semibold tracking-[-0.03em] text-slate-900 ts-title">Add New User</h3>
                    <p class="mt-1 text-sm text-slate-500">Client will be inserted first, then the user will be linked to that client.</p>
                </div>

                <form method="post" action="<?= e(base_url('admin/users/save-user')) ?>" class="grid gap-6">
                    <?= csrf_field() ?>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-slate-900 ts-title">User Details</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Full Name</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="name" required>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Phone</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="phone" required>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Email</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="email" name="email" required>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Password</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="password" name="password" placeholder="Leave blank to auto-generate">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Role</label>
                                <select name="role_id" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                    <option value="">Select role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= e((string) ($role['id'] ?? '')) ?>">
                                            <?= e((string) ($role['name'] ?? 'Role')) ?><?= !empty($role['slug']) ? ' (' . e((string) $role['slug']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_phone_verified" value="1" class="h-4 w-4">
                                <span>Phone Verified</span>
                            </label>

                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_email_verified" value="1" class="h-4 w-4">
                                <span>Email Verified</span>
                            </label>

                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4">
                                <span>Active</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-slate-900 ts-title">Client Details</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Name</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="client_name" placeholder="Defaults to user name">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Phone</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="client_phone" placeholder="Defaults to user phone">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Email</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="email" name="client_email" placeholder="Defaults to user email">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Type</label>
                                <select name="client_type" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                    <option value="individual">Individual</option>
                                    <option value="business">Business</option>
                                    <option value="company">Company</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Company Name</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="company_name">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">GST Number</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="gst_number">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">PAN Number</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="pan_number">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">City</label>
                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="city">
                            </div>

                            <div class="space-y-2 md:col-span-2 xl:col-span-1">
                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Status</label>
                                <select name="client_status" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="toggleAddUserAccordion()" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-[#075B9A] px-5 text-sm font-semibold text-white transition hover:bg-[#053B73]">
                            Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="py-5">
            <?php if (!empty($users)): ?>
                <div class="ts-premium-scroll space-y-0 overflow-x-auto">
                    <?php foreach ($users as $user): ?>
                        <?php
                            $userId = (string) ($user['id'] ?? '');
                            $isActive = (int) ($user['is_active'] ?? 0) === 1;
                            $phoneVerified = (int) ($user['is_phone_verified'] ?? 0) === 1;
                            $emailVerified = (int) ($user['is_email_verified'] ?? 0) === 1;
                            $displayName = (string) ($user['name'] ?? '');
                            $accordionId = 'user-accordion-' . $userId;
                            $iconId = 'accordion-icon-' . $userId;
                            $labelId = 'accordion-label-' . $userId;
                        ?>
                        <div class="ts-user-row border-b border-slate-200 py-5">
                            <div class="py-0">
                                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#075B9A] text-sm font-bold uppercase tracking-[0.16em] text-white">
                                                <?= e(user_initials($displayName)) ?>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2.5">
                                                    <h3 class="truncate text-[1rem] font-semibold tracking-[-0.03em] text-slate-900 ts-title">
                                                        <?= e($displayName) ?>
                                                    </h3>

                                                    <span class="<?= $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500' ?> inline-flex items-center rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]">
                                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                                    </span>

                                                    <?php if ($phoneVerified): ?>
                                                        <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-sky-700">
                                                            Phone Verified
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($emailVerified): ?>
                                                        <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-violet-700">
                                                            Email Verified
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500">
                                                    <span><?= e((string) ($user['phone'] ?? '')) ?></span>
                                                    <?php if (!empty($user['email'])): ?>
                                                        <span class="hidden text-slate-300 sm:inline">•</span>
                                                        <span class="truncate"><?= e((string) $user['email']) ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-3 flex flex-wrap gap-2.5">
                                                    <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-[11px] font-medium text-indigo-700">
                                                        Role: <?= e((string) ($user['role_name'] ?? 'No Role')) ?>
                                                    </span>

                                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-medium text-slate-600">
                                                        Client: <?= e(client_label_from_user($user)) ?>
                                                    </span>

                                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-medium text-slate-600">
                                                        Last Login: <?= e((string) ($user['last_login_at'] ?? '')) ?: 'Never' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2.5 xl:justify-end">
                                        <button
                                            type="button"
                                            onclick="toggleUserAccordion('<?= e($accordionId) ?>','<?= e($iconId) ?>','<?= e($labelId) ?>')"
                                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-[12px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            <span id="<?= e($labelId) ?>">Edit Details</span>
                                            <svg id="<?= e($iconId) ?>" class="ml-2 h-4 w-4 transition-transform duration-300" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>

                                        <form method="post" action="<?= e(base_url('admin/users/toggle-user')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                            <button class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-[12px] font-semibold text-slate-700 transition hover:bg-slate-50" type="submit">
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?= e(base_url('admin/users/delete-user')) ?>" onsubmit="return confirm('Delete this user?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                            <button class="inline-flex h-11 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white transition hover:bg-rose-700" type="submit">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div id="<?= e($accordionId) ?>" class="hidden border-t border-slate-200 bg-transparent py-5">
                                <div class="mb-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Inline Editor</p>
                                </div>

                                <form method="post" action="<?= e(base_url('admin/users/save-user')) ?>" class="grid gap-6">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($userId) ?>">

                                    <div>
                                        <h4 class="mb-3 text-sm font-semibold text-slate-900 ts-title">User Details</h4>
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Full Name</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Phone</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" required>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Email</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Password</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="password" name="password" placeholder="Leave blank to keep current password">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Role</label>
                                                <select name="role_id" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                                    <option value="">Select role</option>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?= e((string) ($role['id'] ?? '')) ?>" <?= (string) ($user['role_id'] ?? '') === (string) ($role['id'] ?? '') ? 'selected' : '' ?>>
                                                            <?= e((string) ($role['name'] ?? 'Role')) ?><?= !empty($role['slug']) ? ' (' . e((string) $role['slug']) . ')' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                                <input type="checkbox" name="is_phone_verified" value="1" class="h-4 w-4" <?= $phoneVerified ? 'checked' : '' ?>>
                                                <span>Phone Verified</span>
                                            </label>

                                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                                <input type="checkbox" name="is_email_verified" value="1" class="h-4 w-4" <?= $emailVerified ? 'checked' : '' ?>>
                                                <span>Email Verified</span>
                                            </label>

                                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                                <input type="checkbox" name="is_active" value="1" class="h-4 w-4" <?= $isActive ? 'checked' : '' ?>>
                                                <span>Active</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="mb-3 text-sm font-semibold text-slate-900 ts-title">Client Details</h4>
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Name</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="client_name" value="<?= e((string) ($user['client_name'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Phone</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="client_phone" value="<?= e((string) ($user['client_phone'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Email</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="email" name="client_email" value="<?= e((string) ($user['client_email'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Type</label>
                                                <select name="client_type" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                                    <option value="individual" <?= (string) ($user['client_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
                                                    <option value="business" <?= (string) ($user['client_type'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
                                                    <option value="company" <?= (string) ($user['client_type'] ?? '') === 'company' ? 'selected' : '' ?>>Company</option>
                                                </select>
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Company Name</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="company_name" value="<?= e((string) ($user['client_company_name'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">GST Number</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="gst_number" value="<?= e((string) ($user['client_gst_number'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">PAN Number</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="pan_number" value="<?= e((string) ($user['client_pan_number'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">City</label>
                                                <input class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm" type="text" name="city" value="<?= e((string) ($user['client_city'] ?? '')) ?>">
                                            </div>

                                            <div class="space-y-2">
                                                <label class="text-[12px] font-semibold uppercase tracking-[0.16em] text-slate-500">Client Status</label>
                                                <select name="client_status" class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm">
                                                    <option value="active" <?= (string) ($user['client_status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= (string) ($user['client_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                        <button
                                            type="button"
                                            onclick="toggleUserAccordion('<?= e($accordionId) ?>','<?= e($iconId) ?>','<?= e($labelId) ?>')"
                                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                        >
                                            Cancel
                                        </button>

                                        <button
                                            type="submit"
                                            class="inline-flex h-11 items-center justify-center rounded-xl bg-[#075B9A] px-5 text-sm font-semibold text-white transition hover:bg-[#053B73]"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="border-y border-dashed border-slate-200 py-14 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-sky-50 text-xl text-sky-700">
                        👤
                    </div>
                    <h3 class="mt-4 text-base font-semibold tracking-[-0.03em] text-slate-900 ts-title">No users added yet</h3>
                    <p class="mt-2 text-sm text-slate-500">Start by creating your first user profile.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
function closeAllEditAccordions() {
    document.querySelectorAll('[id^="user-accordion-"]').forEach(function (el) {
        el.classList.add('hidden');
    });

    document.querySelectorAll('[id^="accordion-icon-"]').forEach(function (icon) {
        icon.classList.remove('rotate-180');
    });

    document.querySelectorAll('[id^="accordion-label-"]').forEach(function (label) {
        label.textContent = 'Edit Details';
    });
}

function closeAddUserAccordion() {
    const addAccordion = document.getElementById('add-user-accordion');
    const addIcon = document.getElementById('add-user-icon');
    const topLabel = document.getElementById('top-add-user-label');
    const sectionLabel = document.getElementById('section-add-user-label');

    if (addAccordion) addAccordion.classList.add('hidden');
    if (addIcon) addIcon.classList.remove('rotate-180');
    if (topLabel) topLabel.textContent = 'Add User';
    if (sectionLabel) sectionLabel.textContent = 'Add User';
}

function toggleAddUserAccordion() {
    const addAccordion = document.getElementById('add-user-accordion');
    const addIcon = document.getElementById('add-user-icon');
    const topLabel = document.getElementById('top-add-user-label');
    const sectionLabel = document.getElementById('section-add-user-label');

    if (!addAccordion) return;

    const isOpen = !addAccordion.classList.contains('hidden');

    closeAllEditAccordions();

    if (isOpen) {
        closeAddUserAccordion();
        return;
    }

    addAccordion.classList.remove('hidden');
    if (addIcon) addIcon.classList.add('rotate-180');
    if (topLabel) topLabel.textContent = 'Close Add User';
    if (sectionLabel) sectionLabel.textContent = 'Close Add User';

    addAccordion.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function toggleUserAccordion(contentId, iconId, labelId) {
    const content = document.getElementById(contentId);
    const icon = document.getElementById(iconId);
    const label = document.getElementById(labelId);

    if (!content || !icon || !label) return;

    const isOpen = !content.classList.contains('hidden');

    closeAddUserAccordion();
    closeAllEditAccordions();

    if (!isOpen) {
        content.classList.remove('hidden');
        icon.classList.add('rotate-180');
        label.textContent = 'Close Editor';
        content.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeAddUserAccordion();
        closeAllEditAccordions();
    }
});
</script>