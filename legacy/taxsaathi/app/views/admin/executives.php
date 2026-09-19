<?php

declare(strict_types=1);

$executives = is_array($executives ?? null) ? $executives : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filters = is_array($filters ?? null) ? $filters : [];
$canManage = (bool) ($canManage ?? false);
$search = (string) ($filters['q'] ?? '');
$status = (string) ($filters['status'] ?? 'all');

$money = static function (mixed $value): string {
    return '₹' . number_format((float) $value, 2);
};
?>

<section class="space-y-6">
    <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.06)] lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-brand-600">Team · Workflow control</p>
            <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-950">Executive Management</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                Open an Executive profile to monitor weekly, monthly and yearly workflow performance, review order payouts, and manage manual salary records.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= e(base_url('admin/executive-payouts')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">
                Executive Payouts
            </a>
            <a href="<?= e(base_url('admin/staff')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">
                Manage Staff
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Executives</p>
            <p class="mt-3 text-3xl font-black text-slate-950"><?= e((string) ($stats['total'] ?? 0)) ?></p>
            <p class="mt-1 text-xs font-semibold text-slate-500">Matched through user_roles</p>
        </div>
        <div class="rounded-[22px] border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Active</p>
            <p class="mt-3 text-3xl font-black text-emerald-950"><?= e((string) ($stats['active'] ?? 0)) ?></p>
            <p class="mt-1 text-xs font-semibold text-emerald-800/75">Available for assignment</p>
        </div>
        <div class="rounded-[22px] border border-blue-200 bg-blue-50 p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-700">Assigned Orders</p>
            <p class="mt-3 text-3xl font-black text-blue-950"><?= e((string) ($stats['assigned_orders'] ?? 0)) ?></p>
            <p class="mt-1 text-xs font-semibold text-blue-800/75">All-time assigned workflow</p>
        </div>
        <div class="rounded-[22px] border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Completed Orders</p>
            <p class="mt-3 text-3xl font-black text-amber-950"><?= e((string) ($stats['completed_orders'] ?? 0)) ?></p>
            <p class="mt-1 text-xs font-semibold text-amber-800/75">Completed or delivered</p>
        </div>
    </div>

    <form method="get" action="<?= e(base_url('admin/executives')) ?>" class="grid gap-3 rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_auto] md:items-end">
        <label class="grid gap-2">
            <span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search Executive</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, phone or email" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-100">
        </label>
        <label class="grid gap-2">
            <span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Account status</span>
            <select name="status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-100">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Executives</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Disabled</option>
            </select>
        </label>
        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-black text-white transition hover:bg-slate-800">Apply Filter</button>
    </form>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black tracking-[-0.02em] text-slate-950">Executive users</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">Select a profile to monitor workflow and salary history.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600"><?= e((string) count($executives)) ?> record(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1120px] border-collapse text-left text-sm">
                <thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white">
                    <tr>
                        <th class="px-5 py-4">Executive</th>
                        <th class="px-5 py-4">Contact</th>
                        <th class="px-5 py-4 text-center">Orders</th>
                        <th class="px-5 py-4 text-center">Completed</th>
                        <th class="px-5 py-4 text-right">Order Payouts</th>
                        <th class="px-5 py-4 text-right">Salary Paid</th>
                        <th class="px-5 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($executives as $executive): ?>
                        <?php
                        $id = (int) ($executive['id'] ?? 0);
                        $active = (int) ($executive['is_active'] ?? 0) === 1;
                        $name = trim((string) ($executive['name'] ?? 'Executive')) ?: 'Executive';
                        $initial = strtoupper(substr($name, 0, 1));
                        ?>
                        <tr class="align-middle transition hover:bg-slate-50/80">
                            <td class="px-5 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-sm font-black text-white"><?= e($initial) ?></div>
                                    <div class="min-w-0">
                                        <a href="<?= e(base_url('admin/executives/show?id=' . $id)) ?>" class="truncate font-black text-slate-950 hover:text-brand-700"><?= e($name) ?></a>
                                        <div class="mt-1 text-xs font-semibold text-slate-400">Executive · ID #<?= e((string) $id) ?></div>
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-black <?= $active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-100' ?>"><?= $active ? 'Active' : 'Disabled' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-5 text-slate-600">
                                <div><?= e((string) ($executive['phone'] ?? '—')) ?></div>
                                <div class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($executive['email'] ?? '—')) ?></div>
                            </td>
                            <td class="px-5 py-5 text-center font-black text-slate-900"><?= e((string) ($executive['assigned_orders'] ?? 0)) ?></td>
                            <td class="px-5 py-5 text-center font-black text-emerald-700"><?= e((string) ($executive['completed_orders'] ?? 0)) ?></td>
                            <td class="px-5 py-5 text-right font-black text-slate-900"><?= e($money($executive['order_payout_total'] ?? 0)) ?></td>
                            <td class="px-5 py-5 text-right font-black text-slate-900"><?= e($money($executive['salary_total'] ?? 0)) ?></td>
                            <td class="px-5 py-5 text-right">
                                <a href="<?= e(base_url('admin/executives/show?id=' . $id)) ?>" class="inline-flex h-9 items-center justify-center rounded-xl bg-brand-600 px-3 text-xs font-black text-white transition hover:bg-brand-700">Open Profile</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($executives === []): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="text-sm font-black text-slate-700">No Executive users found.</p>
                                <p class="mt-1 text-xs font-semibold text-slate-400">Executive users must have a user_roles assignment with role_id = 3.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
