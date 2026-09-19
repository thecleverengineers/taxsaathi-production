<?php declare(strict_types=1);

$order = is_array($order ?? null) ? $order : [];
$documents = is_array($documents ?? null) ? array_values($documents) : [];

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

$fileUrl = static function (mixed $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '#';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$fileType = static function (array $doc): string {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return 'IMAGE';
    }

    $name = strtolower((string) ($doc['original_name'] ?? $doc['file_path'] ?? ''));
    $ext = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));

    return $ext !== '' ? $ext : 'FILE';
};

$acceptedOrderId = (int) ($order['accepted_order_id'] ?? 0);
$acceptedOrderNo = trim((string) ($order['accepted_order_no'] ?? ''));
$isAccepted = $acceptedOrderId > 0;
?>

<section class="grid gap-5">
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="relative bg-slate-950 px-5 py-6 text-white sm:px-7">
            <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl"></div>
            <div class="absolute -bottom-24 -left-20 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.20em] text-indigo-200">
                        Partner Order Review
                    </p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                        <?= e((string) ($order['order_no'] ?? '')) ?>
                    </h1>
                    <p class="mt-2 text-sm text-slate-300">
                        <?= e((string) ($order['partner_name'] ?? '-')) ?> · <?= e((string) ($order['service_title'] ?? '-')) ?>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="<?= e(base_url('admin/partner-orders')) ?>"
                        class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white transition hover:bg-white/15"
                    >
                        ← Back
                    </a>

                    <?php if ($isAccepted): ?>
                        <a
                            href="<?= e(base_url('admin/orders/show?id=' . $acceptedOrderId)) ?>"
                            class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-700"
                        >
                            Open Created Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">
                    Filing Fee
                </div>
                <div class="mt-2 text-xl font-black text-slate-950">
                    <?= e($money($order['filing_fee'] ?? 0)) ?>
                </div>
            </div>

            <div class="rounded-[20px] border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">
                    Coupon
                </div>
                <div class="mt-2 text-xl font-black text-emerald-700">
                    <?= e((string) (($order['coupon_code'] ?? '') ?: '—')) ?>
                </div>
            </div>

            <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">
                    Discount
                </div>
                <div class="mt-2 text-xl font-black text-slate-950">
                    <?= e($money($order['coupon_discount_amount'] ?? 0)) ?>
                </div>
            </div>

            <div class="rounded-[20px] border border-slate-900 bg-slate-900 p-4 text-white shadow-sm">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-300">
                    Payable
                </div>
                <div class="mt-2 text-xl font-black">
                    <?= e($money($order['payable_amount'] ?? 0)) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">
                Payment Details
            </p>
            <h2 class="mt-1 text-xl font-black text-slate-950">
                Manual UPI Proof
            </h2>

            <div class="mt-5 grid gap-3 text-sm">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Reference</span>
                    <span class="font-black text-slate-950"><?= e((string) ($order['payment_reference'] ?? '-')) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Paid At</span>
                    <span class="font-black text-slate-950"><?= e($dateText($order['paid_at'] ?? '')) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Payment Status</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($order['payment_status'] ?? '')) ?>">
                        <?= e($statusLabel($order['payment_status'] ?? '')) ?>
                    </span>
                </div>

                <?php if (!empty($order['payment_proof'])): ?>
                    <a
                        class="inline-flex h-11 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-50 px-5 text-sm font-black text-indigo-700 transition hover:bg-indigo-100"
                        target="_blank"
                        href="<?= e($fileUrl($order['payment_proof'])) ?>"
                    >
                        View Payment Proof
                    </a>
                <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 px-4 py-5 text-sm font-bold text-amber-700">
                        No payment proof uploaded.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">
                Partner / Workflow
            </p>
            <h2 class="mt-1 text-xl font-black text-slate-950">
                Current Status
            </h2>

            <div class="mt-5 grid gap-3 text-sm">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Partner User</span>
                    <span class="font-black text-slate-950"><?= e((string) ($order['partner_name'] ?? '-')) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Partner ID</span>
                    <span class="font-black text-slate-950"><?= e((string) ($order['partner_id'] ?? 0)) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Order Status</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($order['order_status'] ?? '')) ?>">
                        <?= e($statusLabel($order['order_status'] ?? '')) ?>
                    </span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="font-bold text-slate-500">Filing Status</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($order['filing_status'] ?? '')) ?>">
                        <?= e($statusLabel($order['filing_status'] ?? '')) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">
                    Uploaded Documents
                </p>
                <h2 class="mt-1 text-xl font-black text-slate-950">
                    Partner Submitted Client Documents
                </h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">
                    On accept, these files will be copied into normal order documents as client documents.
                </p>
            </div>

            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                <?= e((string) count($documents)) ?> file(s)
            </span>
        </div>

        <div class="mt-5 grid gap-3">
            <?php foreach ($documents as $doc): ?>
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-[10px] font-black tracking-[0.12em] text-slate-500">
                            <?= e($fileType($doc)) ?>
                        </div>

                        <div class="min-w-0">
                            <div class="truncate font-black text-slate-950">
                                <?= e((string) ($doc['title'] ?? $doc['label'] ?? 'Document')) ?>
                            </div>
                            <div class="mt-1 truncate text-xs font-semibold text-slate-500">
                                <?= e((string) ($doc['original_name'] ?? '')) ?>
                            </div>
                        </div>
                    </div>

                    <a
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-black text-indigo-700 transition hover:bg-indigo-100"
                        href="<?= e($fileUrl($doc['file_path'] ?? '')) ?>"
                        target="_blank"
                    >
                        View
                    </a>
                </div>
            <?php endforeach; ?>

            <?php if ($documents === []): ?>
                <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 px-4 py-10 text-center">
                    <div class="text-sm font-black text-amber-800">
                        No documents uploaded.
                    </div>
                    <p class="mt-2 text-sm font-semibold text-amber-700">
                        This partner order has no uploaded requirement documents.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="rounded-[24px] border <?= $isAccepted ? 'border-emerald-200 bg-emerald-50' : 'border-indigo-200 bg-indigo-50' ?> p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] <?= $isAccepted ? 'text-emerald-700' : 'text-indigo-700' ?>">
                    Admin Acceptance
                </p>

                <h2 class="mt-1 text-xl font-black text-slate-900">
                    <?= $isAccepted ? 'Partner Order Accepted' : 'Accept Partner Order' ?>
                </h2>

                <p class="mt-2 max-w-3xl text-sm leading-6 <?= $isAccepted ? 'text-emerald-700' : 'text-indigo-700' ?>">
                    <?php if ($isAccepted): ?>
                        This partner order has already been converted into a normal assigned order.
                        The partner can manage it from Partner Assigned Orders.
                    <?php else: ?>
                        Accepting will create a normal order in the <strong>orders</strong> table for this partner,
                        assign it to the same partner user, and copy uploaded partner documents into client documents.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($isAccepted): ?>
                <a
                    href="<?= e(base_url('admin/orders/show?id=' . $acceptedOrderId)) ?>"
                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-700 px-5 text-sm font-black text-white transition hover:bg-emerald-800"
                >
                    Open Created Order
                </a>
            <?php endif; ?>
        </div>

        <?php if ($isAccepted): ?>
            <div class="mt-5 rounded-2xl border border-emerald-200 bg-white/80 px-4 py-4">
                <div class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">
                    Created Assigned Order
                </div>
                <div class="mt-2 text-lg font-black text-slate-950">
                    <?= e($acceptedOrderNo !== '' ? $acceptedOrderNo : ('Order #' . $acceptedOrderId)) ?>
                </div>
                <div class="mt-1 text-sm font-semibold text-emerald-700">
                    Visible to partner under /partner/assigned-orders
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(base_url('admin/partner-orders/accept')) ?>" class="mt-5 grid gap-4">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4">
                        <div class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">
                            Specific User
                        </div>
                        <div class="mt-2 text-base font-black text-slate-900">
                            <?= e((string) ($order['partner_name'] ?? '-')) ?>
                        </div>
                        <div class="mt-1 text-xs font-semibold text-slate-500">
                            User ID: <?= e((string) ($order['partner_id'] ?? 0)) ?>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4">
                        <div class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">
                            Will Update
                        </div>
                        <div class="mt-2 text-base font-black text-slate-900">
                            orders.assigned_user_id
                        </div>
                        <div class="mt-1 text-xs font-semibold text-slate-500">
                            Assigned to partner user
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/70 bg-white/80 p-4">
                        <div class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">
                            Workflow Status
                        </div>
                        <div class="mt-2 text-base font-black text-slate-900">
                            Work In Progress
                        </div>
                        <div class="mt-1 text-xs font-semibold text-slate-500">
                            Payment marked verified
                        </div>
                    </div>
                </div>

                <label class="grid gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">
                        Acceptance Notes
                    </span>
                    <textarea
                        name="admin_notes"
                        class="min-h-[100px] rounded-2xl border border-indigo-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                    >Accepted from partner order <?= e((string) ($order['order_no'] ?? '')) ?>.</textarea>
                </label>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    This action will create a new normal order, copy partner documents, and make it visible in
                    <strong>/partner/assigned-orders</strong> for this partner.
                </div>

                <button
                    class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_12px_28px_rgba(15,23,42,0.18)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                    type="submit"
                >
                    Accept & Create Assigned Order
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>