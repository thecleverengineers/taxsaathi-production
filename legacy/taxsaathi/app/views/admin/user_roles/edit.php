<?php

declare(strict_types=1);

$roles = is_array($roles ?? null) ? $roles : [];
$assignedRoleIds = is_array($assignedRoleIds ?? null) ? array_map('intval', $assignedRoleIds) : [];
$effectivePermissions = is_array($effectivePermissions ?? null) ? $effectivePermissions : [];
?>
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Edit User Roles</h1>
            <p class="mt-1 text-sm text-slate-500">Assign multiple roles. Saved only in <code>user_roles</code>.</p>
        </div>
        <a href="<?= e(base_url('admin/users/roles')) ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Back</a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="font-bold text-slate-900"><?= e((string) ($userRow['name'] ?? 'User')) ?></div>
        <div class="mt-1 text-sm text-slate-500"><?= e((string) ($userRow['email'] ?? '')) ?> <?= e((string) ($userRow['phone'] ?? '')) ?></div>
    </div>

    <form method="post" action="<?= e(base_url('admin/users/roles/update')) ?>" class="rounded-2xl border border-slate-200 bg-white p-4">
        <?= function_exists('csrf_field') ? csrf_field() : '' ?>
        <input type="hidden" name="user_id" value="<?= e((string) ($userRow['id'] ?? 0)) ?>">

        <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Available Roles</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($roles as $role): ?>
                <?php $roleId = (int) ($role['id'] ?? 0); ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-brand-200 hover:bg-brand-50/50">
                    <input type="checkbox" name="role_ids[]" value="<?= e((string) $roleId) ?>" <?= in_array($roleId, $assignedRoleIds, true) ? 'checked' : '' ?> class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600">
                    <span><span class="block font-bold text-slate-800"><?= e((string) ($role['name'] ?? 'Role')) ?></span><span class="text-xs text-slate-400"><?= e((string) ($role['slug'] ?? '')) ?></span></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 flex justify-end"><button class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700">Save User Roles</button></div>
    </form>

    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="text-sm font-black uppercase tracking-wide text-slate-500">Effective Permissions</h2>
        <div class="mt-4 flex max-h-72 flex-wrap gap-2 overflow-y-auto">
            <?php if ($effectivePermissions === []): ?><span class="text-sm text-slate-400">No permissions assigned.</span><?php endif; ?>
            <?php foreach ($effectivePermissions as $permission): ?><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"><?= e((string) $permission) ?></span><?php endforeach; ?>
        </div>
    </div>
</div>
