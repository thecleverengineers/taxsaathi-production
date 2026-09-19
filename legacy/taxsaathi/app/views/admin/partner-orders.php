<?php declare(strict_types=1);

$rows = is_array($rows ?? null) ? array_values($rows) : [];

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$statusLabel = static function (mixed $status): string {
    $status = trim((string) $status);

    return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : '-';
};

$statusTone = static function (mixed $status): string {
    return match (strtolower(trim((string) $status))) {
        'approved', 'paid', 'verified', 'completed', 'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending_review', 'payment_submitted', 'payment_under_review', 'waiting_for_payment', 'pending_payment', 'in_progress' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$dateText = static function (mixed $value): string {
    $value = trim((string) $value);

    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d M Y, h:i A', $timestamp) : $value;
};

$totalOrders = count($rows);
$waitingPayment = 0;
$pendingReview = 0;
$accepted = 0;
$totalPayable = 0.0;

foreach ($rows as $row) {
    $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? '')));
    $orderStatus = strtolower(trim((string) ($row['order_status'] ?? '')));

    if ($paymentStatus === 'waiting_for_payment') {
        $waitingPayment++;
    }

    if ($paymentStatus === 'pending_review' || $orderStatus === 'payment_submitted') {
        $pendingReview++;
    }

    if ((int) ($row['accepted_order_id'] ?? 0) > 0 || $orderStatus === 'approved') {
        $accepted++;
    }

    $totalPayable += (float) ($row['payable_amount'] ?? 0);
}
?>

<section class="grid gap-5">
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="relative bg-slate-950 px-5 py-6 text-white sm:px-7">
            <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.20em] text-indigo-200">
                        Admin Review
                    </p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        Partner Orders
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                        Review partner-submitted applications, approve manual UPI payment, and convert them into assigned workflow orders.
                    </p>
                </div>

                <a
                    href="<?= e(base_url('admin/dashboard')) ?>"
                    class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white transition hover:bg-white/15"
                >
                    Admin Dashboard
                </a>
            </div>
        </div>

        <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-5">
            <?php
            $cards = [
                ['label' => 'Total Orders', 'value' => $totalOrders, 'tone' => 'bg-slate-50 text-slate-900'],
                ['label' => 'Waiting Payment', 'value' => $waitingPayment, 'tone' => 'bg-amber-50 text-amber-700'],
                ['label' => 'Pending Review', 'value' => $pendingReview, 'tone' => 'bg-indigo-50 text-indigo-700'],
                ['label' => 'Accepted', 'value' => $accepted, 'tone' => 'bg-emerald-50 text-emerald-700'],
                ['label' => 'Total Payable', 'value' => $money($totalPayable), 'tone' => 'bg-slate-950 text-white'],
            ];
            ?>

            <?php foreach ($cards as $card): ?>
                <div class="rounded-[22px] border border-slate-200 <?= e($card['tone']) ?> p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] opacity-70">
                        <?= e((string) $card['label']) ?>
                    </div>
                    <div class="mt-2 text-2xl font-black">
                        <?= e((string) $card['value']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">
                    Partner Order Queue
                </p>
                <h2 class="mt-1 text-lg font-black text-slate-950">
                    Review / Accept Partner Orders
                </h2>
            </div>

            <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                <?= e((string) $totalOrders) ?> record(s)
            </div>
        </div>

        <?php if ($rows === []): ?>
            <div class="px-5 py-16 text-center">
                <div class="text-base font-black text-slate-900">No partner orders yet.</div>
                <p class="mt-2 text-sm text-slate-500">Partner-submitted orders will appear here for admin approval.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] border-collapse text-left text-sm">
                    <thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Partner</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3 text-right">Payable</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Filing</th>
                            <th class="px-4 py-3">Accepted Order</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $acceptedOrderId = (int) ($row['accepted_order_id'] ?? 0);
                            $acceptedOrderNo = trim((string) ($row['accepted_order_no'] ?? ''));
                            ?>
                            <tr class="transition hover:bg-indigo-50/40">
                                <td class="px-4 py-3">
                                    <div class="font-black text-slate-950">
                                        <?= e((string) ($row['order_no'] ?? '')) ?>
                                    </div>
                                    <div class="mt-1 text-xs font-semibold text-slate-400">
                                        #<?= e((string) ($row['id'] ?? 0)) ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800">
                                        <?= e((string) ($row['partner_name'] ?? '-')) ?>
                                    </div>
                                    <div class="mt-1 text-xs font-semibold text-slate-400">
                                        <?= e((string) ($row['partner_email'] ?? '-')) ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3 font-semibold text-slate-700">
                                    <?= e((string) ($row['service_title'] ?? '-')) ?>
                                </td>

                                <td class="px-4 py-3 text-right font-black text-slate-950">
                                    <?= e($money($row['payable_amount'] ?? 0)) ?>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($row['payment_status'] ?? '')) ?>">
                                        <?= e($statusLabel($row['payment_status'] ?? '')) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($row['filing_status'] ?? '')) ?>">
                                        <?= e($statusLabel($row['filing_status'] ?? '')) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <?php if ($acceptedOrderId > 0): ?>
                                        <a href="<?= e(base_url('admin/orders/show?id=' . $acceptedOrderId)) ?>" class="font-black text-emerald-700 hover:text-emerald-800">
                                            <?= e($acceptedOrderNo !== '' ? $acceptedOrderNo : ('Order #' . $acceptedOrderId)) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
                                            Not Accepted
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3 text-xs font-semibold text-slate-500">
                                    <?= e($dateText($row['created_at'] ?? '')) ?>
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <a
                                        class="inline-flex h-9 items-center justify-center rounded-xl <?= $acceptedOrderId > 0 ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-slate-950 hover:bg-slate-800' ?> px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition"
                                        href="<?= e(base_url('admin/partner-orders/show?id=' . (int) ($row['id'] ?? 0))) ?>"
                                    >
                                        <?= $acceptedOrderId > 0 ? 'Open' : 'Review / Accept' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>