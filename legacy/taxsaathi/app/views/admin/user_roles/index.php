<?php

declare(strict_types=1);

$users = is_array($users ?? null) ? $users : [];
$filters = is_array($filters ?? null) ? $filters : [];
?>
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">User Roles & Permissions</h1>
            <p class="mt-1 text-sm text-slate-500">Assigned roles are loaded from <code>user_roles</code> only.</p>
        </div>
        <a href="<?= e(base_url('admin/roles/permissions')) ?>" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Role Permission Mapping</a>
    </div>

    <form method="get" action="<?= e(base_url('admin/users/roles')) ?>" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_160px_auto]">
        <input type="text" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search name, email or phone" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-300">
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-300">
            <option value="">All status</option>
            <option value="1" <?= (string) ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (string) ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400">
                    <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Roles from user_roles</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($users === []): ?>
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td class="px-4 py-3"><div class="font-bold text-slate-800"><?= e((string) ($row['name'] ?? 'User')) ?></div><div class="text-xs text-slate-400">ID: <?= e((string) ($row['id'] ?? '')) ?></div></td>
                            <td class="px-4 py-3"><div><?= e((string) ($row['email'] ?? '')) ?></div><div class="text-xs text-slate-400"><?= e((string) ($row['phone'] ?? '')) ?></div></td>
                            <td class="px-4 py-3">
                                <?php $names = array_filter(array_map('trim', explode(',', (string) ($row['role_names'] ?? '')))); ?>
                                <?php if ($names === []): ?><span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-600">No roles assigned</span><?php endif; ?>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php foreach ($names as $name): ?><span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700"><?= e($name) ?></span><?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3"><?= ((int) ($row['is_active'] ?? 0) === 1) ? '<span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Active</span>' : '<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Inactive</span>' ?></td>
                            <td class="px-4 py-3 text-right"><a href="<?= e(base_url('admin/users/roles/edit?id=' . (int) ($row['id'] ?? 0))) ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Manage Roles</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
