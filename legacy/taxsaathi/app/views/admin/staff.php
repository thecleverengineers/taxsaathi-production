<section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-4 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Roles</p>
            <h2 class="mt-0.5 text-[1.15rem] font-bold tracking-[-0.03em] text-slate-900">Create Role</h2>
        </div>

        <form method="post" action="<?= e(base_url('admin/staff/save-role')) ?>" class="grid gap-4 p-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    type="text"
                    name="name"
                    placeholder="Role name"
                    required
                >
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    type="text"
                    name="slug"
                    placeholder="role-slug"
                    required
                >
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <?php foreach (permissions_catalog() as $group => $permissions): ?>
                    <div class="rounded-[18px] border border-slate-200 bg-slate-50/70 p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <strong class="text-sm font-semibold text-slate-900"><?= e($group) ?></strong>
                            <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                <?= e((string) count($permissions)) ?> items
                            </span>
                        </div>

                        <div class="space-y-2">
                            <?php foreach ($permissions as $permission): ?>
                                <label class="flex items-center gap-2.5 rounded-xl border border-transparent px-2 py-2 text-sm text-slate-700 transition hover:border-slate-200 hover:bg-white">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="<?= e($permission) ?>"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <span class="break-all"><?= e($permission) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button
                class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                type="submit"
            >
                Save Role
            </button>
        </form>

        <div class="border-t border-slate-100 p-4">
            <div class="mb-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Saved Roles</p>
            </div>

            <div class="space-y-3">
                <?php foreach ($roles as $role): ?>
                    <div class="rounded-[18px] border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-900"><?= e($role['name']) ?></div>
                                <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-500"><?= e($role['slug']) ?></div>
                            </div>

                            <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Role
                            </span>
                        </div>

                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-[12px] leading-6 text-slate-500">
                            <?= e((string) $role['permissions_json']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($roles)): ?>
                    <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        No roles created yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-4 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Users</p>
            <h2 class="mt-0.5 text-[1.15rem] font-bold tracking-[-0.03em] text-slate-900">Create Staff User</h2>
        </div>

        <form method="post" action="<?= e(base_url('admin/staff/save-user')) ?>" class="grid gap-4 p-4">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    type="text"
                    name="name"
                    placeholder="Full name"
                    required
                >
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    type="text"
                    name="phone"
                    placeholder="+91..."
                    required
                >
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    type="email"
                    name="email"
                    placeholder="Email"
                >
                <select
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    name="role_id"
                >
                    <?php foreach ($roles as $role): ?>
                        <?php if (($role['slug'] ?? '') === 'client') continue; ?>
                        <option value="<?= e((string) $role['id']) ?>"><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                    <input
                        type="checkbox"
                        name="is_phone_verified"
                        value="1"
                        checked
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    >
                    <span>Phone Verified</span>
                </label>

                <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        checked
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    >
                    <span>Active</span>
                </label>
            </div>

            <button
                class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                type="submit"
            >
                Save User
            </button>
        </form>

        <div class="border-t border-slate-100 p-4">
            <div class="mb-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Directory</p>
            </div>

            <div class="space-y-3">
                <?php foreach ($users as $user): ?>
                    <div class="flex items-center justify-between gap-3 rounded-[18px] border border-slate-200 bg-slate-50/70 p-4">
                        <div class="min-w-0 flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-sm font-bold text-white">
                                <?= e(strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1))) ?>
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900"><?= e($user['name']) ?></div>
                                <div class="mt-1 truncate text-[12px] text-slate-500">
                                    <?= e($user['phone']) ?> • <?= e($user['role_name'] ?? '-') ?>
                                </div>
                            </div>
                        </div>

                        <form method="post" action="<?= e(base_url('admin/staff/toggle-user')) ?>" class="shrink-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">

                            <button
                                class="<?= (int) $user['is_active'] === 1
                                    ? 'inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-[12px] font-semibold text-slate-700 transition hover:border-rose-200 hover:text-rose-600'
                                    : 'inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3.5 text-[12px] font-semibold text-white transition hover:bg-slate-800'
                                ?>"
                                type="submit"
                            >
                                <?= (int) $user['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($users)): ?>
                    <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        No staff users created yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>