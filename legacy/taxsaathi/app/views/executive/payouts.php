<?php

declare(strict_types=1);

$executive = is_array($executive ?? null) ? $executive : [];
$rows = is_array($rows ?? null) ? $rows : [];
$salaryRows = is_array($salaryRows ?? null) ? $salaryRows : [];
$profile = is_array($profile ?? null) ? $profile : [];
$stats = is_array($stats ?? null) ? $stats : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$payoutTableReady = (bool) ($payoutTableReady ?? false);
$salaryTableReady = (bool) ($salaryTableReady ?? false);
$paymentProfileTableReady = (bool) ($paymentProfileTableReady ?? false);

$money = static function (mixed $value, string $currency = 'INR'): string {
    return strtoupper($currency) === 'INR'
        ? '₹' . number_format((float) $value, 2)
        : strtoupper($currency) . ' ' . number_format((float) $value, 2);
};

$date = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }

    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d M Y, h:i A', $timestamp);
};

$dateOnly = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }

    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d M Y', $timestamp);
};

$methodLabel = static function (mixed $value): string {
    return match (strtolower(trim((string) $value))) {
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI / QR',
        'cash' => 'Cash',
        default => ucwords(str_replace('_', ' ', trim((string) $value))) ?: '—',
    };
};

$name = trim((string) ($executive['name'] ?? 'Executive')) ?: 'Executive';
$initial = strtoupper(substr($name, 0, 1));
$profileConfirmed = (int) ($profile['payment_details_confirmed'] ?? 0) === 1;
$preferredMethod = (string) ($profile['preferred_method'] ?? 'bank_transfer');
?>

<section class="space-y-6">
    <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.06)] lg:flex-row lg:items-end lg:justify-between">
        <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[20px] bg-indigo-600 text-xl font-black text-white"><?= e($initial) ?></div>
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-brand-600">Finance · Executive workspace</p>
                <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-950">My Payouts</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Track every completed-order payout and manual salary payment recorded for <?= e($name) ?>. This page uses the same dashboard and scopes data to your session user ID.</p>
            </div>
        </div>
        <a href="<?= e(base_url('dashboard')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">Back to Dashboard</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-[22px] border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Order payouts</p><p class="mt-3 text-3xl font-black text-emerald-950"><?= e((string) ($stats['paid_count'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-emerald-800/75">Paid records</p></div>
        <div class="rounded-[22px] border border-blue-200 bg-blue-50 p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-blue-700">Total received</p><p class="mt-3 text-3xl font-black text-blue-950"><?= e($money($stats['paid_amount'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-blue-800/75">Completed-order payouts</p></div>
        <div class="rounded-[22px] border border-indigo-200 bg-indigo-50 p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-700">Salary records</p><p class="mt-3 text-3xl font-black text-indigo-950"><?= e((string) ($stats['salary_count'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-indigo-800/75">Weekly, monthly or yearly</p></div>
    </div>

    <section id="payment-details" class="rounded-[26px] border border-slate-200 bg-white p-6 shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-brand-600">Payment destination</p>
                <h2 class="mt-2 text-xl font-black text-slate-950">UPI and bank details</h2>
                <p class="mt-1 max-w-2xl text-sm font-semibold leading-6 text-slate-500">Keep your payout destination current. Administrators see these details on the Executive Payouts page.</p>
            </div>
            <?php if ($paymentProfileTableReady && $profileConfirmed): ?>
                <span class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-emerald-700 ring-1 ring-emerald-100">Ready to receive payment</span>
            <?php else: ?>
                <span class="inline-flex w-fit rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-amber-700 ring-1 ring-amber-100">Action required</span>
            <?php endif; ?>
        </div>

        <?php if (!$paymentProfileTableReady): ?>
            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold leading-6 text-amber-900">Payment profile storage is not installed yet. Ask an administrator to run the latest Executive Management migration.</div>
        <?php else: ?>
            <form method="post" action="<?= e(base_url('executive/payouts/payment-profile')) ?>" class="mt-5 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                <?= csrf_field() ?>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">UPI ID</span><input name="upi_id" maxlength="120" value="<?= e((string) ($profile['upi_id'] ?? '')) ?>" placeholder="name@bank" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Preferred method</span><select name="preferred_method" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"><?php foreach ($paymentMethods as $method): ?><option value="<?= e((string) $method) ?>" <?= $preferredMethod === $method ? 'selected' : '' ?>><?= e($methodLabel($method)) ?></option><?php endforeach; ?></select></label>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Bank name</span><input name="bank_name" maxlength="191" value="<?= e((string) ($profile['bank_name'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Account holder name</span><input name="account_holder_name" maxlength="191" value="<?= e((string) ($profile['account_holder_name'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Account number</span><input name="account_number" maxlength="80" value="<?= e((string) ($profile['account_number'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">IFSC code</span><input name="ifsc_code" maxlength="30" value="<?= e((string) ($profile['ifsc_code'] ?? '')) ?>" placeholder="ABCD0123456" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold uppercase text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="grid gap-1.5 sm:col-span-2"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Bank branch (optional)</span><input name="bank_branch" maxlength="191" value="<?= e((string) ($profile['bank_branch'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-brand-400 focus:ring-4 focus:ring-brand-100"></label>
                <label class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:col-span-2"><input type="checkbox" name="payment_details_confirmed" value="1" <?= $profileConfirmed ? 'checked' : '' ?> class="mt-1 h-4 w-4 rounded border-emerald-300 text-emerald-600"><span class="text-xs font-bold leading-5 text-emerald-900">I confirm these details are correct and mark this destination ready to receive Executive payouts.</span></label>
                <div class="sm:col-span-2 sm:text-right"><button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-slate-800">Save Payment Details</button></div>
            </form>
        <?php endif; ?>
    </section>

    <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-black text-slate-950">Completed-order payout history</h2><p class="mt-1 text-xs font-semibold text-slate-500">Each completed order is paid once by an Administrator and remains in this ledger.</p></div><span class="inline-flex w-fit rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-black text-emerald-700"><?= e((string) count($rows)) ?> record(s)</span></div>
        <?php if (!$payoutTableReady): ?><div class="m-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold leading-6 text-amber-900">Payout records are not installed yet. Ask an administrator to run the latest migration.</div><?php endif; ?>
        <div class="overflow-x-auto"><table class="w-full min-w-[980px] border-collapse text-left text-sm"><thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white"><tr><th class="px-5 py-4">Order</th><th class="px-5 py-4">Client / Service</th><th class="px-5 py-4">Method / reference</th><th class="px-5 py-4 text-right">Amount</th><th class="px-5 py-4 text-right">Paid at</th><th class="px-5 py-4">Paid by</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
                <tr class="transition hover:bg-slate-50/80"><td class="px-5 py-4"><a href="<?= e(base_url('admin/orders/show?id=' . (int) ($row['order_id'] ?? 0))) ?>" class="font-black text-brand-700 hover:text-brand-900"><?= e((string) ($row['order_no'] ?? ('Order #' . ($row['order_id'] ?? '')))) ?></a><p class="mt-1 text-xs font-semibold text-slate-400"><?= e(ucwords(str_replace('_', ' ', (string) ($row['order_status'] ?? 'completed')))) ?></p></td><td class="px-5 py-4"><p class="font-bold text-slate-800"><?= e((string) ($row['client_name'] ?? 'Client')) ?></p><p class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($row['service_title'] ?? 'Service')) ?></p></td><td class="px-5 py-4"><p class="font-black text-slate-700"><?= e($methodLabel($row['payment_method'] ?? '')) ?></p><p class="mt-1 max-w-[220px] truncate text-xs font-semibold text-slate-400" title="<?= e((string) ($row['payment_reference'] ?? '')) ?>"><?= e((string) ($row['payment_reference'] ?? '—')) ?></p></td><td class="px-5 py-4 text-right font-black text-emerald-700"><?= e($money($row['amount'] ?? 0, (string) ($row['currency'] ?? 'INR'))) ?></td><td class="px-5 py-4 text-right text-xs font-semibold text-slate-500"><?= e($date($row['paid_at'] ?? '')) ?></td><td class="px-5 py-4 text-xs font-semibold text-slate-600"><?= e((string) ($row['paid_by_name'] ?? 'Administrator')) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="6" class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No completed-order payout has been recorded for you yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5"><h2 class="text-lg font-black text-slate-950">Manual salary history</h2><p class="mt-1 text-xs font-semibold text-slate-500">Salary records entered by an Administrator for your weekly, monthly, or yearly period.</p></div>
        <?php if (!$salaryTableReady): ?><div class="m-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold leading-6 text-amber-900">Salary records are not installed yet.</div><?php else: ?>
            <div class="overflow-x-auto"><table class="w-full min-w-[850px] border-collapse text-left text-sm"><thead class="bg-slate-50 text-[11px] font-black uppercase tracking-[0.12em] text-slate-500"><tr><th class="px-5 py-4">Period</th><th class="px-5 py-4 text-right">Amount</th><th class="px-5 py-4">Method / reference</th><th class="px-5 py-4 text-right">Paid at</th><th class="px-5 py-4">Paid by</th></tr></thead><tbody class="divide-y divide-slate-100">
                <?php foreach ($salaryRows as $row): ?><tr><td class="px-5 py-4"><p class="font-black text-slate-800"><?= e(ucfirst((string) ($row['period_type'] ?? 'period'))) ?></p><p class="mt-1 text-xs font-semibold text-slate-500"><?= e($dateOnly($row['period_start'] ?? '')) ?> – <?= e($dateOnly($row['period_end'] ?? '')) ?></p></td><td class="px-5 py-4 text-right font-black text-indigo-700"><?= e($money($row['amount'] ?? 0, (string) ($row['currency'] ?? 'INR'))) ?></td><td class="px-5 py-4"><p class="font-bold text-slate-700"><?= e($methodLabel($row['payment_method'] ?? '')) ?></p><p class="mt-1 text-xs font-semibold text-slate-400"><?= e((string) ($row['payment_reference'] ?? '—')) ?></p></td><td class="px-5 py-4 text-right text-xs font-semibold text-slate-500"><?= e($date($row['paid_at'] ?? '')) ?></td><td class="px-5 py-4 text-xs font-semibold text-slate-600"><?= e((string) ($row['paid_by_name'] ?? 'Administrator')) ?></td></tr><?php endforeach; ?>
                <?php if ($salaryRows === []): ?><tr><td colspan="5" class="px-5 py-12 text-center text-sm font-semibold text-slate-400">No manual salary payment has been recorded yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</section>
