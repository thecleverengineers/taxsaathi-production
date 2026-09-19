<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? array_values($rows) : [];
$portalRole = strtolower(trim((string) ($portalRole ?? (!empty($isPartnerUser) ? 'partner' : 'client'))));
$isPartner = $portalRole === 'partner';

$heading = trim((string) ($heading ?? '')) ?: ($isPartner ? 'My Applications' : 'My Applications');
$description = trim((string) ($description ?? '')) ?: ($isPartner
    ? 'Orders submitted through your partner profile are shown here.'
    : 'Only applications linked to your client profile are shown here.');
$ordersHomeUrl = trim((string) ($ordersHomeUrl ?? '')) ?: ($isPartner ? 'partner/orders' : 'client/orders');
$viewPath = trim((string) ($viewPath ?? '')) ?: ($isPartner ? 'partner/orders/show' : 'client/orders/view');
$newOrderUrl = trim((string) ($newOrderUrl ?? '')) ?: ($isPartner ? 'partner/services' : 'services');

$statusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'verified', 'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending', 'pending_review', 'waiting_for_payment', 'pending_payment', 'submitted', 'processing', 'work_in_progress', 'in_progress', 'payment_under_review', 'partial', 'clarification' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$statusLabel = static function (string $status): string {
    $status = trim($status);
    return $status === '' ? 'Pending' : ucwords(str_replace('_', ' ', $status));
};

$money = static function (float $amount): string {
    if (function_exists('format_money')) {
        return (string) format_money($amount);
    }

    return '₹' . number_format($amount, 2);
};

$dateLabel = static function (string $date): string {
    if ($date === '') {
        return '—';
    }

    if (function_exists('format_date')) {
        return (string) format_date($date);
    }

    $timestamp = strtotime($date);
    return $timestamp === false ? $date : date('d M Y', $timestamp);
};

$portalLabel = $isPartner ? 'Partner Portal' : 'Client Portal';
$accessLabel = $isPartner ? 'Verified Partner Access' : 'Verified Client Access';
$emptyTitle = $isPartner ? 'No applications found' : 'No applications found';
$emptyDescription = $isPartner
    ? 'Orders submitted through your partner profile will appear here.'
    : 'Orders placed under your linked client profile will appear here.';
$emptyActionLabel = $isPartner ? 'Browse Partner Services' : 'Browse Services';
?>

<section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500"><?= e($portalLabel) ?></p>
                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-700 ring-1 ring-emerald-200"><?= e($accessLabel) ?></span>
            </div>
            <h2 class="mt-1 text-[1.15rem] font-bold tracking-[-0.03em] text-slate-900"><?= e($heading) ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= e($description) ?></p>
        </div>

        <a
            class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
            href="<?= e(base_url($newOrderUrl)) ?>"
        >
            New Order
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-left">
            <thead class="bg-slate-50/90">
                <tr class="text-[11px] uppercase tracking-[0.16em] text-slate-500">
                    <th class="px-4 py-3 font-semibold">Application ID</th>
                    <th class="px-4 py-3 font-semibold">Service</th>
                    <th class="px-4 py-3 font-semibold"><?= $isPartner ? 'Payable' : 'Fee' ?></th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Payment</th>
                    <th class="px-4 py-3 font-semibold">Date</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row): ?>
                    <?php
                        $orderId = (int) ($row['id'] ?? 0);
                        $orderNo = trim((string) ($row['order_no'] ?? '')) ?: ('Application #' . $orderId);
                        $serviceTitle = trim((string) ($row['service_title'] ?? '')) ?: 'Service';
                        $orderStatus = (string) ($isPartner ? ($row['order_status'] ?? $row['status'] ?? 'pending') : ($row['status'] ?? 'pending'));
                        $paymentStatus = (string) ($row['payment_status'] ?? 'pending');
                        $amount = (float) ($isPartner
                            ? ($row['payable_amount'] ?? $row['fee_amount'] ?? 0)
                            : ($row['fee_amount'] ?? 0));
                        $createdAt = trim((string) ($row['created_at'] ?? $row['updated_at'] ?? ''));
                        $orderUrl = $orderId > 0
                            ? base_url($viewPath . '?id=' . $orderId . '&order_id=' . $orderId)
                            : '';
                        $canContinuePayment = $isPartner && in_array(strtolower(trim($paymentStatus)), ['pending', 'waiting_for_payment', 'unpaid'], true);
                    ?>
                    <tr
                        class="transition hover:bg-slate-50/70 <?= $orderUrl !== '' ? 'cursor-pointer' : '' ?>"
                        <?php if ($orderUrl !== ''): ?>data-my-application-row data-href="<?= e($orderUrl) ?>"<?php endif; ?>
                    >
                        <td class="whitespace-nowrap px-4 py-3">
                            <?php if ($orderUrl !== ''): ?>
                                <a href="<?= e($orderUrl) ?>" class="block">
                            <?php endif; ?>
                            <div class="font-semibold text-slate-900"><?= e($orderNo) ?></div>
                            <?php if (!empty($row['invoice_no'])): ?>
                                <div class="mt-0.5 text-[11px] font-medium text-slate-400"><?= e((string) $row['invoice_no']) ?></div>
                            <?php endif; ?>
                            <?php if ($orderUrl !== ''): ?>
                                </a>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3 text-sm font-medium text-slate-700">
                            <?= e($serviceTitle) ?>
                            <?php if ($isPartner && !empty($row['coupon_code'])): ?>
                                <div class="mt-1 text-[11px] font-semibold text-emerald-700">Coupon: <?= e((string) $row['coupon_code']) ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-900">
                            <?= e($money($amount)) ?>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($statusTone($orderStatus)) ?>">
                                <?= e($statusLabel($orderStatus)) ?>
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($statusTone($paymentStatus)) ?>">
                                <?= e($statusLabel($paymentStatus)) ?>
                            </span>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                            <?= e($dateLabel($createdAt)) ?>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($orderId > 0): ?>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <?php if ($canContinuePayment): ?>
                                        <a
                                            class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-600 px-3.5 text-[12px] font-semibold text-white transition hover:bg-emerald-700"
                                            href="<?= e(base_url('partner/orders/payment?order_id=' . $orderId)) ?>"
                                        >
                                            Pay
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 px-3.5 text-[12px] font-semibold text-brand-700 transition hover:border-brand-300 hover:bg-brand-100"
                                        href="<?= e($orderUrl . '#edit-order') ?>"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 text-[12px] font-semibold text-slate-700 transition hover:border-brand-200 hover:text-brand-700"
                                        href="<?= e($orderUrl) ?>"
                                    >
                                        View
                                    </a>

                                    <?php if (!$isPartner): ?>
                                        <a
                                            class="inline-flex h-9 items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-3.5 text-[12px] font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100"
                                            href="<?= e(base_url('client/orders/invoice-preview?id=' . $orderId)) ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Preview Invoice
                                        </a>

                                        <a
                                            class="inline-flex h-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 text-[12px] font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100"
                                            href="<?= e(base_url('client/orders/invoice-download?id=' . $orderId)) ?>"
                                        >
                                            Download PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="mx-auto max-w-md">
                                <div class="text-sm font-bold text-slate-800"><?= e($emptyTitle) ?></div>
                                <p class="mt-1 text-sm leading-6 text-slate-500"><?= e($emptyDescription) ?></p>
                                <a href="<?= e(base_url($newOrderUrl)) ?>" class="mt-4 inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white"><?= e($emptyActionLabel) ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($rows !== []): ?>
    <script>
        document.querySelectorAll('[data-my-application-row]').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('a, button, input, select, textarea, label')) {
                    return;
                }

                const href = row.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            });
        });
    </script>
<?php endif; ?>