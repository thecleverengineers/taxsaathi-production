<?php

declare(strict_types=1);

$executive = is_array($executive ?? null) ? $executive : [];
$period = is_array($period ?? null) ? $period : [];
$workflowSummary = is_array($workflowSummary ?? null) ? $workflowSummary : [];
$workflowRows = is_array($workflowRows ?? null) ? $workflowRows : [];
$salaryRows = is_array($salaryRows ?? null) ? $salaryRows : [];
$orderPayoutRows = is_array($orderPayoutRows ?? null) ? $orderPayoutRows : [];
$salaryMethods = is_array($salaryMethods ?? null) ? $salaryMethods : [];
$salaryTableReady = (bool) ($salaryTableReady ?? false);
$paymentProfileTableReady = (bool) ($paymentProfileTableReady ?? false);
$paymentProfile = is_array($paymentProfile ?? null) ? $paymentProfile : [];
$canViewPaymentDetails = (bool) ($canViewPaymentDetails ?? false);
$canManage = (bool) ($canManage ?? false);
$canPaySalary = (bool) ($canPaySalary ?? false);

$executiveId = (int) ($executive['id'] ?? 0);
$name = trim((string) ($executive['name'] ?? 'Executive')) ?: 'Executive';
$initial = strtoupper(substr($name, 0, 1));
$isActive = (int) ($executive['is_active'] ?? 0) === 1;
$periodType = (string) ($period['type'] ?? 'month');
$periodAnchor = (string) ($period['anchor'] ?? date('Y-m-d'));
$periodStart = (string) ($period['start'] ?? '');
$periodEnd = (string) ($period['end'] ?? '');
$periodLabel = (string) ($period['label'] ?? 'Selected period');

$money = static function (mixed $value, string $currency = 'INR'): string {
    $prefix = strtoupper($currency) === 'INR' ? '₹' : strtoupper($currency) . ' ';
    return $prefix . number_format((float) $value, 2);
};

$dateTime = static function (mixed $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d M Y, h:i A', $timestamp);
};

$dateOnly = static function (mixed $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d M Y', $timestamp);
};

$statusLabel = static function (mixed $value): string {
    $status = trim((string) $value);
    return $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status));
};

$statusClass = static function (mixed $value): string {
    $status = strtolower(trim((string) $value));

    return match (true) {
        in_array($status, ['completed', 'complete', 'closed', 'delivered', 'paid'], true)
            => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
        in_array($status, ['rejected', 'failed', 'cancelled', 'canceled'], true)
            => 'bg-rose-50 text-rose-700 ring-1 ring-rose-100',
        default => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
    };
};

$methodLabel = static function (mixed $value): string {
    return match (strtolower(trim((string) $value))) {
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI / QR',
        'cash' => 'Cash',
        default => ucwords(str_replace('_', ' ', trim((string) $value))) ?: '—',
    };
};
?>

<section class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex items-start gap-3">
            <a href="<?= e(base_url('admin/executives')) ?>" class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-black text-slate-600 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700" aria-label="Back to Executive Management">←</a>
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-brand-600">Executive Management · Profile</p>
                <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-950"><?= e($name) ?></h1>
                <p class="mt-2 text-sm font-semibold text-slate-500">Review this Executive’s workflow, order-linked payouts, and manually recorded salary history.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?= e(base_url('admin/executive-payouts')) ?>" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">Executive Payouts</a>
            <?php if ($canManage): ?>
                <form method="post" action="<?= e(base_url('admin/executives/toggle')) ?>" onsubmit="return confirm('Change this Executive account status?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $executiveId) ?>">
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl px-3 text-xs font-black transition <?= $isActive ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>"><?= $isActive ? 'Disable Account' : 'Activate Account' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
        <section class="rounded-[26px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[22px] bg-indigo-600 text-2xl font-black text-white"><?= e($initial) ?></div>
                    <div>
                        <h2 class="text-xl font-black text-slate-950"><?= e($name) ?></h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500"><?= e((string) ($executive['role_names'] ?? 'Executive')) ?> · User ID #<?= e((string) $executiveId) ?></p>
                        <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[11px] font-black <?= $isActive ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-100' ?>"><?= $isActive ? 'Active account' : 'Disabled account' ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:min-w-[260px]">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Phone</p>
                        <p class="mt-1 font-bold text-slate-800"><?= e((string) ($executive['phone'] ?? '—')) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Email</p>
                        <p class="mt-1 break-all font-bold text-slate-800"><?= e((string) ($executive['email'] ?? '—')) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Joined</p>
                        <p class="mt-1 font-bold text-slate-800"><?= e($dateOnly($executive['created_at'] ?? '')) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Last login</p>
                        <p class="mt-1 font-bold text-slate-800"><?= e($dateTime($executive['last_login_at'] ?? '')) ?></p>
                    </div>
                </div>
            </div>

            <?php if ($canManage): ?>
                <details class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <summary class="cursor-pointer text-sm font-black text-slate-800">Edit Executive profile</summary>
                    <form method="post" action="<?= e(base_url('admin/executives/update')) ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) $executiveId) ?>">
                        <label class="grid gap-1.5">
                            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Name</span>
                            <input name="name" required value="<?= e((string) ($executive['name'] ?? '')) ?>" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100">
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Phone</span>
                            <input name="phone" required value="<?= e((string) ($executive['phone'] ?? '')) ?>" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100">
                        </label>
                        <label class="grid gap-1.5 sm:col-span-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Email</span>
                            <input type="email" name="email" value="<?= e((string) ($executive['email'] ?? '')) ?>" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100">
                        </label>
                        <div class="sm:col-span-2 sm:text-right">
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-800">Save Profile</button>
                        </div>
                    </form>
                </details>
            <?php else: ?>
                <p class="mt-6 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs font-semibold leading-5 text-blue-800">Managers can monitor this profile. Profile changes, account status changes, and salary entries are restricted to Administrators.</p>
            <?php endif; ?>
        </section>

        <section class="rounded-[26px] border border-slate-200 bg-slate-950 p-6 text-white shadow-[0_12px_30px_rgba(15,23,42,0.12)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-brand-300">Workflow window</p>
                    <h2 class="mt-2 text-2xl font-black"><?= e($periodLabel) ?></h2>
                    <p class="mt-1 text-xs font-semibold text-slate-400"><?= e($periodStart) ?> → <?= e($periodEnd) ?></p>
                </div>
                <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em] text-slate-300"><?= e(ucfirst($periodType)) ?></span>
            </div>
            <form method="get" action="<?= e(base_url('admin/executives/show')) ?>" class="mt-6 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <input type="hidden" name="id" value="<?= e((string) $executiveId) ?>">
                <label class="grid gap-1.5">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Period</span>
                    <select name="period_type" class="h-10 rounded-xl border border-white/10 bg-white/10 px-3 text-sm font-bold text-white outline-none focus:border-brand-300 focus:ring-4 focus:ring-brand-400/20">
                        <option value="week" class="text-slate-900" <?= $periodType === 'week' ? 'selected' : '' ?>>Weekly</option>
                        <option value="month" class="text-slate-900" <?= $periodType === 'month' ? 'selected' : '' ?>>Monthly</option>
                        <option value="year" class="text-slate-900" <?= $periodType === 'year' ? 'selected' : '' ?>>Yearly</option>
                    </select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Anchor date</span>
                    <input type="date" name="period_anchor" value="<?= e($periodAnchor) ?>" required class="h-10 rounded-xl border border-white/10 bg-white/10 px-3 text-sm font-bold text-white outline-none focus:border-brand-300 focus:ring-4 focus:ring-brand-400/20">
                </label>
                <button type="submit" class="h-10 rounded-xl bg-brand-500 px-4 text-xs font-black text-white transition hover:bg-brand-400">View</button>
            </form>
            <p class="mt-4 text-xs font-semibold leading-5 text-slate-400">Orders are grouped by the Executive assignment and their latest workflow activity in this window.</p>
        </section>
    </div>

    <section class="rounded-[26px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-brand-600">Payment destination</p>
                <h2 class="mt-2 text-xl font-black text-slate-950">UPI and bank details</h2>
                <p class="mt-1 text-sm font-semibold leading-6 text-slate-500">These details are supplied by the Executive and visible here to administrators for payout processing.</p>
            </div>
            <?php if (!$canViewPaymentDetails): ?>
                <span class="inline-flex w-fit rounded-full bg-slate-600 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-white">Details restricted</span>
            <?php elseif ($paymentProfileTableReady && (int) ($paymentProfile['payment_details_confirmed'] ?? 0) === 1): ?>
                <span class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-emerald-700 ring-1 ring-emerald-100">Ready to receive payment</span>
            <?php else: ?>
                <span class="inline-flex w-fit rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-amber-700 ring-1 ring-amber-100">Details not confirmed</span>
            <?php endif; ?>
        </div>

        <?php if (!$canViewPaymentDetails): ?>
            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-semibold leading-6 text-slate-700">Payment destination details are restricted to Administrators.</div>
        <?php elseif (!$paymentProfileTableReady): ?>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold leading-6 text-amber-900">Payment profile storage is not installed yet. Run the latest Executive Management migration.</div>
        <?php else: ?>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Preferred method</dt><dd class="mt-2 font-black text-slate-900"><?= e($methodLabel($paymentProfile['preferred_method'] ?? '')) ?></dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">UPI ID</dt><dd class="mt-2 break-all font-black text-slate-900"><?= e((string) ($paymentProfile['upi_id'] ?? '—')) ?></dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Bank / holder</dt><dd class="mt-2 font-black text-slate-900"><?= e((string) ($paymentProfile['bank_name'] ?? '—')) ?><span class="block text-xs font-semibold text-slate-500"><?= e((string) ($paymentProfile['account_holder_name'] ?? '—')) ?></span></dd></div>
                <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Account / IFSC</dt><dd class="mt-2 break-all font-black text-slate-900"><?= e((string) ($paymentProfile['account_number'] ?? '—')) ?><span class="block text-xs font-semibold text-slate-500"><?= e((string) ($paymentProfile['ifsc_code'] ?? '—')) ?></span></dd></div>
            </dl>
            <?php if (trim((string) ($paymentProfile['bank_branch'] ?? '')) !== ''): ?><p class="mt-4 text-xs font-semibold text-slate-500">Branch: <?= e((string) $paymentProfile['bank_branch']) ?></p><?php endif; ?>
            <p class="mt-4 text-xs font-semibold text-slate-400">The Executive can update these details from the My Payouts item in the same dashboard.</p>
        <?php endif; ?>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Orders</p><p class="mt-3 text-3xl font-black text-slate-950"><?= e((string) ($workflowSummary['total_orders'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-slate-500">In selected period</p></div>
        <div class="rounded-[22px] border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-emerald-700">Completed</p><p class="mt-3 text-3xl font-black text-emerald-950"><?= e((string) ($workflowSummary['completed_orders'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-emerald-800/75">Completed / delivered</p></div>
        <div class="rounded-[22px] border border-amber-200 bg-amber-50 p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-amber-700">Active</p><p class="mt-3 text-3xl font-black text-amber-950"><?= e((string) ($workflowSummary['active_orders'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-amber-800/75">Still in workflow</p></div>
        <div class="rounded-[22px] border border-blue-200 bg-blue-50 p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-blue-700">Completion rate</p><p class="mt-3 text-3xl font-black text-blue-950"><?= e((string) ($workflowSummary['completion_rate'] ?? 0)) ?>%</p><p class="mt-1 text-xs font-semibold text-blue-800/75">Selected period</p></div>
        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Order value</p><p class="mt-3 text-2xl font-black text-slate-950"><?= e($money($workflowSummary['order_value'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-slate-500">Assigned work</p></div>
        <div class="rounded-[22px] border border-indigo-200 bg-indigo-50 p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-[0.15em] text-indigo-700">Completed value</p><p class="mt-3 text-2xl font-black text-indigo-950"><?= e($money($workflowSummary['completed_value'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-indigo-800/75">Completed work</p></div>
    </div>

    <section class="rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-950">Workflow by status</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">A period snapshot for <?= e($periodLabel) ?>.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach (($workflowSummary['statuses'] ?? []) as $workflowStatus => $count): ?>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black <?= e($statusClass($workflowStatus)) ?>"><?= e($statusLabel($workflowStatus)) ?>: <?= e((string) $count) ?></span>
                <?php endforeach; ?>
                <?php if (($workflowSummary['statuses'] ?? []) === []): ?><span class="text-xs font-semibold text-slate-400">No workflow activity in this period.</span><?php endif; ?>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] border-collapse text-left text-sm">
                <thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white"><tr><th class="px-5 py-4">Order</th><th class="px-5 py-4">Client</th><th class="px-5 py-4">Service</th><th class="px-5 py-4">Status</th><th class="px-5 py-4 text-right">Value</th><th class="px-5 py-4 text-right">Last activity</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($workflowRows as $row): ?>
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4"><div class="font-black text-slate-900"><?= e((string) ($row['order_no'] ?? ('#' . ($row['id'] ?? '')))) ?></div><div class="mt-1 text-[11px] font-semibold text-slate-400">Created <?= e($dateOnly($row['created_at'] ?? '')) ?></div></td>
                            <td class="px-5 py-4 font-bold text-slate-700"><?= e((string) ($row['client_name'] ?? 'Client')) ?></td>
                            <td class="px-5 py-4 font-semibold text-slate-600"><?= e((string) ($row['service_title'] ?? 'Service')) ?></td>
                            <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black <?= e($statusClass($row['status'] ?? '')) ?>"><?= e($statusLabel($row['status'] ?? '')) ?></span></td>
                            <td class="px-5 py-4 text-right font-black text-slate-900"><?= e($money($row['fee_amount'] ?? 0)) ?></td>
                            <td class="px-5 py-4 text-right text-xs font-semibold text-slate-500"><?= e($dateTime($row['updated_at'] ?? ($row['created_at'] ?? ''))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($workflowRows === []): ?><tr><td colspan="6" class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No assigned orders were active in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
        <section class="rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-lg font-black text-slate-950">Manual salary ledger</h2><p class="mt-1 text-xs font-semibold text-slate-500">One paid salary record per Executive and exact week, month, or year.</p></div>
                <?php if (!$salaryTableReady): ?><span class="inline-flex w-fit rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-black text-rose-700 ring-1 ring-rose-100">Migration required</span><?php endif; ?>
            </div>

            <?php if (!$salaryTableReady): ?>
                <div class="m-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold leading-6 text-amber-900">The salary table is not installed yet. Run the included migration or updated database dump before recording salary.</div>
            <?php elseif ($canPaySalary): ?>
                <form method="post" action="<?= e(base_url('admin/executives/salary/pay')) ?>" class="m-5 grid gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 sm:grid-cols-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="executive_user_id" value="<?= e((string) $executiveId) ?>">
                    <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Salary period</span><select name="period_type" required class="h-10 rounded-xl border border-indigo-100 bg-white px-3 text-sm font-bold text-slate-800"><option value="week" <?= $periodType === 'week' ? 'selected' : '' ?>>Weekly</option><option value="month" <?= $periodType === 'month' ? 'selected' : '' ?>>Monthly</option><option value="year" <?= $periodType === 'year' ? 'selected' : '' ?>>Yearly</option></select></label>
                    <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Period anchor</span><input type="date" name="period_anchor" value="<?= e($periodAnchor) ?>" required class="h-10 rounded-xl border border-indigo-100 bg-white px-3 text-sm font-bold text-slate-800"></label>
                    <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Amount (INR)</span><input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00" class="h-10 rounded-xl border border-indigo-100 bg-white px-3 text-sm font-bold text-slate-800"></label>
                    <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Payment method</span><select name="payment_method" required class="h-10 rounded-xl border border-indigo-100 bg-white px-3 text-sm font-bold text-slate-800"><?php foreach ($salaryMethods as $method): ?><option value="<?= e((string) $method) ?>"><?= e($methodLabel($method)) ?></option><?php endforeach; ?></select></label>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Reference / UTR (optional)</span><input name="payment_reference" maxlength="120" placeholder="Bank reference, UTR, or cash voucher number" class="h-10 rounded-xl border border-indigo-100 bg-white px-3 text-sm font-semibold text-slate-800"></label>
                    <label class="grid gap-1.5 sm:col-span-2"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Notes (optional)</span><textarea name="notes" rows="2" maxlength="4000" placeholder="Add a short payment note" class="rounded-xl border border-indigo-100 bg-white px-3 py-2 text-sm font-semibold text-slate-800"></textarea></label>
                    <div class="sm:col-span-2 sm:text-right"><button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-indigo-600 px-4 text-xs font-black text-white transition hover:bg-indigo-700" onclick="return confirm('Record this salary payment? The same Executive and period cannot be recorded twice.');">Record Salary Payment</button></div>
                </form>
            <?php else: ?>
                <div class="m-5 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-4 text-sm font-semibold leading-6 text-blue-800">Managers have read-only access to salary history. An Administrator must record manual salary payments.</div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500"><tr><th class="px-5 py-3">Period</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3">Method / reference</th><th class="px-5 py-3">Paid by</th><th class="px-5 py-3 text-right">Paid at</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($salaryRows as $row): ?>
                            <tr><td class="px-5 py-4"><div class="font-black text-slate-900"><?= e($statusLabel($row['period_type'] ?? '')) ?></div><div class="mt-1 text-xs font-semibold text-slate-500"><?= e($dateOnly($row['period_start'] ?? '')) ?> – <?= e($dateOnly($row['period_end'] ?? '')) ?></div></td><td class="px-5 py-4 text-right font-black text-slate-900"><?= e($money($row['amount'] ?? 0, (string) ($row['currency'] ?? 'INR'))) ?></td><td class="px-5 py-4"><div class="font-bold text-slate-700"><?= e($methodLabel($row['payment_method'] ?? '')) ?></div><div class="mt-1 max-w-[220px] truncate text-xs font-semibold text-slate-400" title="<?= e((string) ($row['payment_reference'] ?? '')) ?>"><?= e((string) ($row['payment_reference'] ?? '—')) ?></div></td><td class="px-5 py-4 text-xs font-semibold text-slate-600"><?= e((string) ($row['paid_by_name'] ?? 'Administrator')) ?></td><td class="px-5 py-4 text-right text-xs font-semibold text-slate-500"><?= e($dateTime($row['paid_at'] ?? '')) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if ($salaryRows === []): ?><tr><td colspan="5" class="px-5 py-10 text-center text-sm font-semibold text-slate-400">No manual salary payments have been recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5"><div><h2 class="text-lg font-black text-slate-950">Order-linked payouts</h2><p class="mt-1 text-xs font-semibold text-slate-500">Completed-order payments recorded separately.</p></div><a href="<?= e(base_url('admin/executive-payouts')) ?>" class="text-xs font-black text-brand-700 hover:text-brand-900">Open ledger →</a></div>
            <div class="divide-y divide-slate-100">
                <?php foreach ($orderPayoutRows as $row): ?>
                    <div class="flex items-start justify-between gap-4 px-5 py-4"><div class="min-w-0"><p class="truncate font-black text-slate-900"><?= e((string) ($row['order_no'] ?? 'Order')) ?></p><p class="mt-1 text-xs font-semibold text-slate-500"><?= e($methodLabel($row['payment_method'] ?? '')) ?><?php if (trim((string) ($row['payment_reference'] ?? '')) !== ''): ?> · <?= e((string) $row['payment_reference']) ?><?php endif; ?></p><p class="mt-1 text-[11px] font-semibold text-slate-400"><?= e($dateTime($row['paid_at'] ?? '')) ?></p></div><p class="shrink-0 font-black text-emerald-700"><?= e($money($row['amount'] ?? 0, (string) ($row['currency'] ?? 'INR'))) ?></p></div>
                <?php endforeach; ?>
                <?php if ($orderPayoutRows === []): ?><div class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No order-linked payouts recorded for this Executive.</div><?php endif; ?>
            </div>
        </section>
    </div>
</section>
