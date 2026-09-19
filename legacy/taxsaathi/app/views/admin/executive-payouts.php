<?php

declare(strict_types=1);

$rows = is_array($rows ?? null) ? $rows : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filters = is_array($filters ?? null) ? $filters : [];
$executives = is_array($executives ?? null) ? $executives : [];
$selectedExecutive = is_array($selectedExecutive ?? null) ? $selectedExecutive : [];
$selectedPaymentProfile = is_array($selectedPaymentProfile ?? null) ? $selectedPaymentProfile : [];
$schemaReady = (bool) ($schemaReady ?? false);
$paymentProfileTableReady = (bool) ($paymentProfileTableReady ?? false);
$canViewPaymentDetails = (bool) ($canViewPaymentDetails ?? false);
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$canPay = (bool) ($canPay ?? false);
$selectedExecutiveId = (int) ($selectedExecutiveId ?? 0);

$money = static function (mixed $value): string {
    return '₹' . number_format((float) $value, 2);
};

$date = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }

    $time = strtotime($raw);
    return $time === false ? $raw : date('d M Y, h:i A', $time);
};

$methodLabel = static function (mixed $value): string {
    return match (strtolower(trim((string) $value))) {
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI / QR',
        'cash' => 'Cash',
        default => ucwords(str_replace('_', ' ', trim((string) $value))) ?: '—',
    };
};

$filterStatus = (string) ($filters['payout_status'] ?? 'all');
$search = (string) ($filters['q'] ?? '');
$profileConfirmed = (int) ($selectedPaymentProfile['payment_details_confirmed'] ?? 0) === 1;
$bulkMethod = (string) ($selectedPaymentProfile['preferred_method'] ?? 'bank_transfer');
?>

<section class="space-y-6">
    <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.06)] lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.22em] text-brand-600">Finance · Executive work</p>
            <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-950">Executive Payouts</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Filter an Executive, review completed work, and record one-time payments for one, many, or all unpaid completed orders. Administrators can pay; Managers can review.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="<?= e(base_url('admin/executives')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">Executive Management</a><a href="<?= e(base_url('admin/orders')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">View Orders</a></div>
    </div>

    <?php if (!$schemaReady): ?><div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900"><strong>Executive payout records are not installed yet.</strong> Run the latest Executive Management update SQL once, then reload this page.</div><?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-3"><div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Completed orders</p><p class="mt-3 text-3xl font-black text-slate-950"><?= e((string) ($stats['total'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-slate-500">Current filter</p></div><div class="rounded-[22px] border border-amber-200 bg-amber-50 p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Awaiting payment</p><p class="mt-3 text-3xl font-black text-amber-950"><?= e((string) ($stats['pending'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-amber-800/75"><?= e($money($stats['pending_amount'] ?? 0)) ?> default order value</p></div><div class="rounded-[22px] border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Paid to Executives</p><p class="mt-3 text-3xl font-black text-emerald-950"><?= e($money($stats['paid_amount'] ?? 0)) ?></p><p class="mt-1 text-xs font-semibold text-emerald-800/75"><?= e((string) ($stats['paid'] ?? 0)) ?> order(s) paid once</p></div></div>

    <form method="get" action="<?= e(base_url('admin/executive-payouts')) ?>" class="grid gap-3 rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_260px_220px_auto] lg:items-end">
        <label class="grid gap-2"><span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search order, client or Executive</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Order no., name or email" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-100"></label>
        <label class="grid gap-2"><span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Filter Executive</span><select name="executive_user_id" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-800 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-100"><option value="0">All Executives</option><?php foreach ($executives as $executive): ?><option value="<?= e((string) ($executive['id'] ?? 0)) ?>" <?= $selectedExecutiveId === (int) ($executive['id'] ?? 0) ? 'selected' : '' ?>><?= e((string) ($executive['name'] ?? 'Executive')) ?></option><?php endforeach; ?></select></label>
        <label class="grid gap-2"><span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Payment status</span><select name="payout_status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-800 outline-none transition focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-100"><option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All completed orders</option><option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Unpaid only</option><option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid records</option></select></label>
        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-black text-white transition hover:bg-slate-800">Apply Filter</button>
    </form>

    <?php if ($selectedExecutiveId > 0): ?>
        <section class="rounded-[26px] border <?= $profileConfirmed ? 'border-emerald-200 bg-emerald-50/60' : 'border-amber-200 bg-amber-50/70' ?> p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] <?= $profileConfirmed ? 'text-emerald-700' : 'text-amber-700' ?>">Selected Executive · payment destination</p><h2 class="mt-2 text-xl font-black text-slate-950"><?= e((string) ($selectedExecutive['name'] ?? 'Executive')) ?></h2><p class="mt-1 text-xs font-semibold text-slate-600"><?= e((string) ($selectedExecutive['email'] ?? '')) ?> · <?= e((string) ($selectedExecutive['phone'] ?? '')) ?></p></div><?php if (!$canViewPaymentDetails): ?><span class="inline-flex w-fit rounded-full bg-slate-600 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-white">Details restricted</span><?php elseif ($paymentProfileTableReady && $profileConfirmed): ?><span class="inline-flex w-fit rounded-full bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-white">Ready to receive payment</span><?php else: ?><span class="inline-flex w-fit rounded-full bg-amber-600 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.12em] text-white">Details not confirmed</span><?php endif; ?></div>
            <?php if (!$canViewPaymentDetails): ?><p class="mt-4 text-sm font-semibold text-slate-700">Payment destination details are restricted to Administrators.</p><?php elseif (!$paymentProfileTableReady): ?><p class="mt-4 text-sm font-semibold text-amber-900">Run the latest migration to collect UPI and bank details.</p><?php elseif ($selectedPaymentProfile !== []): ?><dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><div><dt class="text-[10px] font-black uppercase tracking-[0.13em] text-slate-500">Preferred</dt><dd class="mt-1 font-black text-slate-900"><?= e($methodLabel($selectedPaymentProfile['preferred_method'] ?? '')) ?></dd></div><div><dt class="text-[10px] font-black uppercase tracking-[0.13em] text-slate-500">UPI ID</dt><dd class="mt-1 break-all font-black text-slate-900"><?= e((string) ($selectedPaymentProfile['upi_id'] ?? '—')) ?></dd></div><div><dt class="text-[10px] font-black uppercase tracking-[0.13em] text-slate-500">Bank</dt><dd class="mt-1 font-black text-slate-900"><?= e((string) ($selectedPaymentProfile['bank_name'] ?? '—')) ?></dd></div><div><dt class="text-[10px] font-black uppercase tracking-[0.13em] text-slate-500">Account holder</dt><dd class="mt-1 font-black text-slate-900"><?= e((string) ($selectedPaymentProfile['account_holder_name'] ?? '—')) ?></dd></div><div><dt class="text-[10px] font-black uppercase tracking-[0.13em] text-slate-500">Account / IFSC</dt><dd class="mt-1 break-all font-black text-slate-900"><?= e((string) ($selectedPaymentProfile['account_number'] ?? '—')) ?><span class="block text-xs font-semibold text-slate-600"><?= e((string) ($selectedPaymentProfile['ifsc_code'] ?? '—')) ?></span></dd></div></dl><?php else: ?><p class="mt-4 text-sm font-semibold text-amber-900">This Executive has not saved payment details yet.</p><?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($canPay && $selectedExecutiveId > 0): ?>
        <form id="bulkPayoutForm" method="post" action="<?= e(base_url('admin/executive-payouts/pay-many')) ?>" class="rounded-[26px] border border-indigo-200 bg-indigo-50/70 p-5 shadow-sm">
            <?= csrf_field() ?><input type="hidden" name="executive_user_id" value="<?= e((string) $selectedExecutiveId) ?>">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-indigo-700">Administrator bulk payment</p><h2 class="mt-2 text-xl font-black text-indigo-950">Pay selected or all unpaid orders</h2><p id="bulkSelectionHint" class="mt-1 text-xs font-semibold text-indigo-800/75">Select pending rows below. Bulk amounts default to each order value and can be edited.</p></div><label class="flex items-center gap-2 rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-black text-indigo-900"><input id="payAllUnpaid" type="checkbox" name="pay_all_unpaid" value="1" class="h-4 w-4 rounded border-indigo-300 text-indigo-600"> Pay all unpaid orders for this Executive</label></div>
            <div class="mt-4 grid gap-3 md:grid-cols-3"><label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Payment method</span><select name="payment_method" required class="h-11 rounded-xl border border-indigo-200 bg-white px-3 text-sm font-bold text-slate-800"><?php foreach ($paymentMethods as $method): ?><option value="<?= e((string) $method) ?>" <?= $bulkMethod === $method ? 'selected' : '' ?>><?= e($methodLabel($method)) ?></option><?php endforeach; ?></select></label><label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Transaction / reference</span><input name="payment_reference" maxlength="120" placeholder="Optional UTR / reference" class="h-11 rounded-xl border border-indigo-200 bg-white px-3 text-sm font-semibold text-slate-800"></label><label class="grid gap-1.5"><span class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Note</span><input name="notes" maxlength="4000" placeholder="Optional bulk payment note" class="h-11 rounded-xl border border-indigo-200 bg-white px-3 text-sm font-semibold text-slate-800"></label></div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-xs font-semibold leading-5 text-indigo-900/75">Bank transfer and UPI require the Executive to confirm a complete destination first. Cash can be recorded without bank details.</p><button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-indigo-600 px-5 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-indigo-700">Record Bulk Payment</button></div>
        </form>
    <?php elseif ($canPay): ?>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm font-semibold leading-6 text-indigo-900">Select one Executive in the filter above to enable multi-order and all-unpaid payment.</div>
    <?php endif; ?>

    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-lg font-black tracking-[-0.02em] text-slate-950">Completed order payout ledger</h2><p class="mt-1 text-xs font-semibold text-slate-500">Only orders assigned to a user with <code class="font-mono">user_roles.role_id = 3</code> appear here. The unique order record prevents duplicate payment.</p></div><div class="flex items-center gap-2"><span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600"><?= e((string) count($rows)) ?> record(s)</span><?php if ($canPay && $selectedExecutiveId > 0): ?><label class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500"><input id="selectVisiblePayouts" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600"> Select visible unpaid</label><?php endif; ?></div></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[1320px] border-collapse text-left text-sm"><thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white"><tr><th class="px-5 py-4">Select / Order</th><th class="px-5 py-4">Executive</th><th class="px-5 py-4">Client / Service</th><th class="px-5 py-4">Completed</th><th class="px-5 py-4 text-right">Order Value</th><th class="px-5 py-4">Payout / Action</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach ($rows as $row): ?>
                <?php $isPaid = strtolower(trim((string) ($row['payout_status'] ?? ''))) === 'paid'; $orderId = (int) ($row['id'] ?? 0); $isBulkTarget = !$isPaid && $canPay && $selectedExecutiveId > 0; ?>
                <tr class="align-top transition hover:bg-slate-50/80"><td class="whitespace-nowrap px-5 py-5"><?php if ($isBulkTarget): ?><div class="flex items-start gap-3"><input type="checkbox" name="order_ids[]" value="<?= e((string) $orderId) ?>" form="bulkPayoutForm" data-payout-select class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600"><div><a href="<?= e(base_url('admin/orders/show?id=' . $orderId)) ?>" class="font-black text-brand-700 hover:text-brand-900"><?= e((string) ($row['order_no'] ?? ('Order #' . $orderId))) ?></a><p class="mt-1 text-xs font-semibold text-slate-400">ID #<?= e((string) $orderId) ?></p></div></div><?php else: ?><a href="<?= e(base_url('admin/orders/show?id=' . $orderId)) ?>" class="font-black text-brand-700 hover:text-brand-900"><?= e((string) ($row['order_no'] ?? ('Order #' . $orderId))) ?></a><p class="mt-1 text-xs font-semibold text-slate-400">ID #<?= e((string) $orderId) ?></p><?php endif; ?></td><td class="px-5 py-5"><p class="font-black text-slate-900"><?= e((string) ($row['executive_name'] ?? 'Executive')) ?></p><p class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($row['executive_email'] ?? '')) ?></p><span class="mt-2 inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black text-indigo-700 ring-1 ring-indigo-100">Executive role · 3</span></td><td class="max-w-[250px] px-5 py-5"><p class="truncate font-black text-slate-900"><?= e((string) ($row['client_name'] ?? 'Client')) ?></p><p class="mt-1 truncate text-xs font-semibold text-slate-500"><?= e((string) ($row['service_title'] ?? 'Service')) ?></p></td><td class="whitespace-nowrap px-5 py-5 text-xs font-semibold text-slate-600"><?= e($date($row['completed_at'] ?? $row['updated_at'] ?? null)) ?></td><td class="whitespace-nowrap px-5 py-5 text-right font-black text-slate-900"><?= e($money($row['fee_amount'] ?? 0)) ?><?php if ($isBulkTarget): ?><label class="mt-2 block text-left"><span class="text-[10px] font-black uppercase tracking-[0.12em] text-indigo-700">Bulk amount</span><input type="number" name="amounts[<?= e((string) $orderId) ?>]" value="<?= e(number_format((float) ($row['fee_amount'] ?? 0), 2, '.', '')) ?>" min="0.01" max="999999999.99" step="0.01" form="bulkPayoutForm" class="mt-1 h-9 w-full rounded-lg border border-indigo-200 bg-indigo-50 px-2 text-xs font-black text-slate-900"></label><?php endif; ?></td><td class="min-w-[390px] px-5 py-5"><?php if ($isPaid): ?><div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><div class="flex items-center justify-between gap-3"><span class="inline-flex rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.12em] text-white">Paid once</span><strong class="text-lg font-black text-emerald-900"><?= e($money($row['payout_amount'] ?? 0)) ?></strong></div><dl class="mt-3 grid gap-1 text-xs font-semibold text-emerald-900/75"><div class="flex justify-between gap-3"><dt>Method</dt><dd class="font-black"><?= e($methodLabel($row['payment_method'] ?? '')) ?></dd></div><div class="flex justify-between gap-3"><dt>Paid at</dt><dd class="font-black"><?= e($date($row['paid_at'] ?? null)) ?></dd></div><div class="flex justify-between gap-3"><dt>Paid by</dt><dd class="font-black"><?= e((string) ($row['paid_by_name'] ?? 'Administrator')) ?></dd></div><?php if (trim((string) ($row['payment_reference'] ?? '')) !== ''): ?><div class="flex justify-between gap-3"><dt>Reference</dt><dd class="max-w-[190px] truncate font-black"><?= e((string) $row['payment_reference']) ?></dd></div><?php endif; ?></dl></div><?php elseif ($canPay): ?><form method="post" action="<?= e(base_url('admin/executive-payouts/pay')) ?>" class="grid gap-2 rounded-2xl border border-amber-200 bg-amber-50/70 p-3" onsubmit="return confirm('Record this one-time Executive payment for this completed order?');"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>"><div class="grid gap-2 sm:grid-cols-2"><label class="grid gap-1"><span class="text-[10px] font-black uppercase tracking-[0.12em] text-amber-800">Amount (INR)</span><input type="number" name="amount" min="0.01" max="999999999.99" step="0.01" required placeholder="Enter amount" class="h-10 rounded-xl border border-amber-200 bg-white px-3 text-sm font-black text-slate-900"></label><label class="grid gap-1"><span class="text-[10px] font-black uppercase tracking-[0.12em] text-amber-800">Method</span><select name="payment_method" required class="h-10 rounded-xl border border-amber-200 bg-white px-3 text-sm font-bold text-slate-900"><option value="">Choose method</option><?php foreach ($paymentMethods as $method): ?><option value="<?= e((string) $method) ?>"><?= e($methodLabel($method)) ?></option><?php endforeach; ?></select></label></div><div class="grid gap-2 sm:grid-cols-2"><input type="text" name="payment_reference" maxlength="120" placeholder="Transaction / reference no. (optional)" class="h-10 rounded-xl border border-amber-200 bg-white px-3 text-xs font-semibold text-slate-800"><input type="text" name="notes" maxlength="4000" placeholder="Short note (optional)" class="h-10 rounded-xl border border-amber-200 bg-white px-3 text-xs font-semibold text-slate-800"></div><button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-amber-700">Mark Paid Once</button></form><?php else: ?><div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-900">Awaiting payment by an Administrator. Managers can view this ledger but cannot record payments.</div><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="6" class="px-5 py-12 text-center"><p class="text-sm font-black text-slate-700">No completed Executive orders found.</p><p class="mt-1 text-xs font-semibold text-slate-400">Completed orders must have an active Executive assignment before they appear here.</p></td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</section>

<?php if ($canPay && $selectedExecutiveId > 0): ?>
<script>
(() => {
    const selector = document.getElementById('selectVisiblePayouts');
    const allBox = document.getElementById('payAllUnpaid');
    const form = document.getElementById('bulkPayoutForm');
    const boxes = Array.from(document.querySelectorAll('[data-payout-select]'));
    const hint = document.getElementById('bulkSelectionHint');

    const refresh = () => {
        const count = boxes.filter((box) => box.checked).length;
        if (hint) {
            hint.textContent = allBox && allBox.checked
                ? 'All unpaid completed orders for this Executive will be paid. Amounts default to each order value.'
                : `${count} unpaid order(s) selected. Amounts default to each order value and can be edited below.`;
        }
    };

    if (selector) {
        selector.addEventListener('change', () => {
            boxes.forEach((box) => { box.checked = selector.checked; });
            refresh();
        });
    }
    boxes.forEach((box) => box.addEventListener('change', refresh));
    if (allBox) allBox.addEventListener('change', refresh);

    window.confirmBulkExecutivePayout = () => {
        const all = allBox && allBox.checked;
        const count = boxes.filter((box) => box.checked).length;
        if (!all && count === 0) {
            window.alert('Select at least one unpaid order or choose all unpaid orders.');
            return false;
        }
        return window.confirm(all ? 'Record payment for every unpaid completed order assigned to this Executive?' : `Record payment for ${count} selected completed order(s)? Each order can be paid only once.`);
    };

    if (form) form.addEventListener('submit', (event) => {
        if (!window.confirmBulkExecutivePayout()) event.preventDefault();
    });
    refresh();
})();
</script>
<?php endif; ?>
