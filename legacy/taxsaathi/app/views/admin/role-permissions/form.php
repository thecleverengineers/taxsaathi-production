<?php
$role = $role ?? null;
$selectedPermissions = $selectedPermissions ?? [];
$isEdit = !empty($role);
$isSystem = $isEdit && (int) ($role['is_system'] ?? 0) === 1;
$action = $isEdit ? base_url('admin/role-permissions/update') : base_url('admin/role-permissions/store');
?>

<section class="space-y-4">
    <div class="flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.06)] md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Access Control</p>
            <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-950"><?= $isEdit ? 'Edit Role Permission' : 'Create Role Permission' ?></h1>
            <p class="mt-1 text-sm text-slate-500">Select only the permissions this role should access.</p>
        </div>
        <a href="<?= e(base_url('admin/role-permissions')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">← Back</a>
    </div>

    <form method="post" action="<?= e($action) ?>" class="space-y-4 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.05)]">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $role['id']) ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Role Name *</span>
                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60 <?= $isSystem ? 'opacity-70' : '' ?>" type="text" name="name" value="<?= e((string) ($role['name'] ?? '')) ?>" placeholder="Example: Executive" <?= $isSystem ? 'readonly' : '' ?> required>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Role Slug *</span>
                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60 <?= $isSystem ? 'opacity-70' : '' ?>" type="text" name="slug" value="<?= e((string) ($role['slug'] ?? '')) ?>" placeholder="executive" <?= $isSystem ? 'readonly' : '' ?> required>
            </label>
        </div>

        <?php if (!$isSystem): ?>
            <label class="inline-flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_system" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= (int) ($role['is_system'] ?? 0) === 1 ? 'checked' : '' ?>>
                System role
            </label>
        <?php else: ?>
            <input type="hidden" name="is_system" value="1">
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <?php foreach (permissions_catalog() as $group => $permissions): ?>
                <div class="rounded-[20px] border border-slate-200 bg-slate-50/70 p-4">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <strong class="text-sm font-bold text-slate-950"><?= e((string) $group) ?></strong>
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500"><?= e((string) count($permissions)) ?> items</span>
                    </div>

                    <div class="space-y-2">
                        <?php foreach ($permissions as $permission): ?>
                            <label class="flex items-center gap-2.5 rounded-xl border border-transparent px-2 py-2 text-sm text-slate-700 transition hover:border-slate-200 hover:bg-white">
                                <input type="checkbox" name="permissions[]" value="<?= e((string) $permission) ?>" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= in_array((string) $permission, $selectedPermissions, true) ? 'checked' : '' ?>>
                                <span class="break-all"><?= e((string) $permission) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="<?= e(base_url('admin/role-permissions')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-bold text-white shadow-[0_10px_22px_rgba(15,23,42,0.16)] transition hover:-translate-y-[1px] hover:bg-slate-800"><?= $isEdit ? 'Update Role' : 'Create Role' ?></button>
        </div>
    </form>
</section>
