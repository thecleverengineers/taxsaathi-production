<?php
$user = $user ?? null;
$roles = $roles ?? [];
$isEdit = !empty($user);
$action = $isEdit ? base_url('admin/staff/update') : base_url('admin/staff/store');
?>

<section class="space-y-4">
    <div class="flex flex-col gap-3 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.06)] md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Team Control</p>
            <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-950"><?= $isEdit ? 'Edit Staff' : 'Create Staff' ?></h1>
            <p class="mt-1 text-sm text-slate-500">Assign a staff role and access will follow that role’s permissions.</p>
        </div>
        <a href="<?= e(base_url('admin/staff')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">← Back</a>
    </div>

    <form method="post" action="<?= e($action) ?>" class="space-y-4 rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_rgba(15,23,42,0.05)]">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Full Name *</span>
                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="text" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" placeholder="Staff full name" required>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Phone *</span>
                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="text" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" placeholder="+91..." required>
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Email</span>
                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="staff@example.com">
            </label>

            <label class="grid gap-1.5">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Role *</span>
                <select class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" name="role_id" required>
                    <option value="">Select role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e((string) $role['id']) ?>" <?= (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e((string) $role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_phone_verified" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= (int) ($user['is_phone_verified'] ?? 0) === 1 ? 'checked' : '' ?>>
                Phone verified
            </label>

            <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" <?= (int) ($user['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                Active
            </label>
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="<?= e(base_url('admin/staff')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Cancel</a>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-bold text-white shadow-[0_10px_22px_rgba(15,23,42,0.16)] transition hover:-translate-y-[1px] hover:bg-slate-800"><?= $isEdit ? 'Update Staff' : 'Create Staff' ?></button>
        </div>
    </form>
</section>
