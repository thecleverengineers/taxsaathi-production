<?php

declare(strict_types=1);

$roles = is_array($roles ?? null) ? $roles : [];
$permissionGroups = is_array($permissionGroups ?? null) ? $permissionGroups : [];
$selectedPermissions = is_array($selectedPermissions ?? null) ? $selectedPermissions : [];
$selectedRoleId = (int) (($selectedRole['id'] ?? 0));
?>
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Role Permission Mapping</h1>
            <p class="mt-1 text-sm text-slate-500">Edit <code>roles.permissions_json</code>. Users inherit these permissions through <code>user_roles</code>.</p>
        </div>
        <a href="<?= e(base_url('admin/users/roles')) ?>" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">User Roles</a>
    </div>

    <form method="get" action="<?= e(base_url('admin/roles/permissions')) ?>" class="rounded-2xl border border-slate-200 bg-white p-4">
        <label class="text-sm font-bold text-slate-700">Select Role</label>
        <div class="mt-2 flex gap-3">
            <select name="role_id" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-300">
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e((string) ($role['id'] ?? 0)) ?>" <?= (int) ($role['id'] ?? 0) === $selectedRoleId ? 'selected' : '' ?>><?= e((string) ($role['name'] ?? 'Role')) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Load</button>
        </div>
    </form>

    <?php if ($selectedRoleId > 0): ?>
        <form method="post" action="<?= e(base_url('admin/roles/permissions/update')) ?>" class="space-y-4">
            <?= function_exists('csrf_field') ? csrf_field() : '' ?>
            <input type="hidden" name="role_id" value="<?= e((string) $selectedRoleId) ?>">

            <?php foreach ($permissionGroups as $group => $permissions): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h2 class="font-black text-slate-900"><?= e((string) $group) ?></h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <?php foreach ($permissions as $permission): ?>
                            <?php $slug = (string) ($permission['permission'] ?? ''); ?>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="checkbox" name="permissions[]" value="<?= e($slug) ?>" <?= in_array($slug, $selectedPermissions, true) ? 'checked' : '' ?> class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span><span class="block text-sm font-bold text-slate-700"><?= e((string) ($permission['label'] ?? $slug)) ?></span><span class="text-xs text-slate-400"><?= e($slug) ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="sticky bottom-4 flex justify-end"><button class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg hover:bg-brand-700">Save Role Permissions</button></div>
        </form>
    <?php endif; ?>
</div>
