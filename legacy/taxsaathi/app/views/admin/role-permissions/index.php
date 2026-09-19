<?php
$roles = $roles ?? [];
?>

<section class="space-y-4">
    <div class="flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.06)] md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Access Control</p>
            <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-950">Manage Role Permissions</h1>
            <p class="mt-1 text-sm text-slate-500">Create, edit and delete roles separately from staff users.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= e(base_url('admin/staff')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Manage Staff</a>
            <a href="<?= e(base_url('admin/role-permissions/create')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-[0_10px_22px_rgba(15,23,42,0.16)] transition hover:-translate-y-[1px] hover:bg-slate-800">+ Create Role</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_14px_32px_rgba(15,23,42,0.05)]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">Permissions</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($roles as $role): ?>
                        <?php
                            $perms = json_decode((string) ($role['permissions_json'] ?? '[]'), true);
                            $perms = is_array($perms) ? $perms : [];
                            $isSystem = (int) ($role['is_system'] ?? 0) === 1;
                        ?>
                        <tr class="align-top transition hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-950"><?= e((string) ($role['name'] ?? '-')) ?></div>
                                <?php if ($isSystem): ?>
                                    <span class="mt-1 inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500">System</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-slate-600"><?= e((string) ($role['slug'] ?? '-')) ?></td>
                            <td class="px-4 py-4">
                                <div class="flex max-w-3xl flex-wrap gap-1.5">
                                    <?php foreach (array_slice($perms, 0, 8) as $permission): ?>
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600"><?= e((string) $permission) ?></span>
                                    <?php endforeach; ?>

                                    <?php if (count($perms) > 8): ?>
                                        <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">+<?= e((string) (count($perms) - 8)) ?> more</span>
                                    <?php endif; ?>

                                    <?php if (empty($perms)): ?>
                                        <span class="text-sm text-slate-400">No permissions assigned</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="<?= e(base_url('admin/role-permissions/edit?id=' . (int) $role['id'])) ?>" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Edit</a>

                                    <?php if (!$isSystem): ?>
                                        <form method="post" action="<?= e(base_url('admin/role-permissions/delete')) ?>" onsubmit="return confirm('Delete this role? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $role['id']) ?>">
                                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-700 transition hover:bg-rose-100">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No roles found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
