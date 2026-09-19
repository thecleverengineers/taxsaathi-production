<?php
$users = $users ?? [];
?>

<section class="space-y-4">
    <div class="flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.06)] md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Team Control</p>
            <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-950">Manage Staff</h1>
            <p class="mt-1 text-sm text-slate-500">Create, edit, disable or delete staff users separately from role permissions.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= e(base_url('admin/role-permissions')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Role Permissions</a>
            <a href="<?= e(base_url('admin/staff/create')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-[0_10px_22px_rgba(15,23,42,0.16)] transition hover:-translate-y-[1px] hover:bg-slate-800">+ Create Staff</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_14px_32px_rgba(15,23,42,0.05)]">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $user): ?>
                        <?php
                            $isActive = (int) ($user['is_active'] ?? 0) === 1;
                            $initial = strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1));
                        ?>
                        <tr class="align-middle transition hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-sm font-black text-white"><?= e($initial) ?></div>
                                    <div class="min-w-0">
                                        <div class="truncate font-bold text-slate-950"><?= e((string) ($user['name'] ?? '-')) ?></div>
                                        <div class="mt-1 text-xs text-slate-500">ID #<?= e((string) ($user['id'] ?? '-')) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                <div><?= e((string) ($user['phone'] ?? '-')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($user['email'] ?? '-')) ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700"><?= e((string) ($user['role_name'] ?? '-')) ?></span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $isActive ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>"><?= $isActive ? 'Active' : 'Disabled' ?></span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="<?= e(base_url('admin/staff/edit?id=' . (int) $user['id'])) ?>" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Edit</a>

                                    <form method="post" action="<?= e(base_url('admin/staff/toggle')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50"><?= $isActive ? 'Disable' : 'Enable' ?></button>
                                    </form>

                                    <form method="post" action="<?= e(base_url('admin/staff/delete')) ?>" onsubmit="return confirm('Delete this staff user? If the staff has records, disable instead.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-700 transition hover:bg-rose-100">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No staff users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
