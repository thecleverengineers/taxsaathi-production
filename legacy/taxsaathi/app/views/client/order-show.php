<?php
declare(strict_types=1);

$order = is_array($order ?? null) ? $order : [];
$submittedDocs = is_array($submittedDocs ?? null) ? $submittedDocs : [];
$paymentProofs = is_array($paymentProofs ?? null) ? $paymentProofs : [];
$deliveredDocs = is_array($deliveredDocs ?? null) ? $deliveredDocs : [];
$wrongDocs = is_array($wrongDocs ?? null) ? $wrongDocs : [];
$payments = is_array($payments ?? null) ? $payments : [];
$activity = is_array($activity ?? null) ? $activity : [];
$invoice = is_array($invoice ?? null) ? $invoice : null;
$customerDetails = is_array($customerDetails ?? null) ? $customerDetails : [];
$canEditOrder = !empty($canEditOrder);
$canResubmitOrder = !empty($canResubmitOrder);
$portalRole = strtolower(trim((string) ($portalRole ?? 'client')));
$isPartnerOrder = !empty($isPartnerOrder) || $portalRole === 'partner';
$portalActionBase = 'client/orders';

$orderId = (int) ($order['id'] ?? 0);
$orderNo = (string) ($order['order_no'] ?? 'Order');
$paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? '')));
$paymentReference = trim((string) ($order['payment_reference'] ?? ''));
$feeAmount = (float) ($isPartnerOrder
    ? ($order['payable_amount'] ?? $order['fee_amount'] ?? 0)
    : ($order['fee_amount'] ?? 0));
$feeLabel = $isPartnerOrder ? 'Payable' : 'Fee';
// Build the correction list from the authoritative submittedDocs query as well
// as any separately supplied wrongDocs array. This prevents the re-upload form
// from disappearing when a controller forgets to pass the secondary array.
$wrongDocsById = [];
foreach (array_merge($submittedDocs, $wrongDocs) as $candidateDocument) {
    if (!is_array($candidateDocument)) {
        continue;
    }

    $candidateId = (int) ($candidateDocument['id'] ?? 0);
    $candidateStatus = strtolower(trim((string) ($candidateDocument['document_status'] ?? 'active')));

    if ($candidateId > 0 && $candidateStatus === 'wrong') {
        $wrongDocsById[$candidateId] = $candidateDocument;
    }
}
$wrongDocs = array_values($wrongDocsById);
$hasWrongDocs = $wrongDocs !== [];

$documentStatusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'wrong' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        'reuploaded' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
        default => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    };
};

$documentStatusLabel = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'wrong' => 'Wrong Document',
        'reuploaded' => 'Re-uploaded',
        default => 'Active',
    };
};

$orderStatusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending', 'pending_review', 'waiting_for_payment', 'processing', 'work_in_progress', 'partial', 'clarification' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$orderStatusLabel = static function (string $status): string {
    $status = trim($status);

    if ($status === '') {
        return 'Pending';
    }

    return ucwords(str_replace('_', ' ', $status));
};

$canPayNow = static function (string $status): bool {
    $status = strtolower(trim($status));

    return $status === ''
        || in_array($status, [
            'pending',
            'waiting_for_payment',
            'unpaid',
            'failed',
        ], true);
};

$showPayNow = $orderId > 0 && $canPayNow($paymentStatus);
$payNowUrl = base_url('client/orders/payment?order_id=' . $orderId);
?>

<section class="space-y-6">
    <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]">
        <div class="border-b border-slate-100 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] px-4 py-4 sm:px-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-700">
                            <?= e($isPartnerOrder ? 'Partner Portal' : 'Client Portal') ?>
                        </span>

                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <?= e($orderNo) ?>
                        </span>

                        <?php if ($showPayNow): ?>
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">
                                Payment Required
                            </span>
                        <?php elseif ($paymentStatus === 'pending_review'): ?>
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-700">
                                Payment Under Review
                            </span>
                        <?php endif; ?>

                        <?php if ($hasWrongDocs): ?>
                            <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-rose-700">
                                Re-upload Required
                            </span>
                        <?php endif; ?>
                    </div>

                    <h2 class="mt-3 text-[1.2rem] font-bold tracking-[-0.03em] text-slate-900 sm:text-[1.35rem]">
                        <?= e($order['service_title'] ?? 'Order Details') ?>
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        View your order, payments, uploaded documents, re-upload requests, and delivered files.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($canEditOrder): ?>
                        <a
                            class="inline-flex h-11 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50 px-5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100"
                            href="#edit-order"
                        >
                            Edit Order
                        </a>
                    <?php endif; ?>

                    <?php if ($canResubmitOrder): ?>
                        <form method="post" action="<?= e(base_url($portalActionBase . '/resubmit')) ?>" class="inline-flex">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">
                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(16,185,129,0.20)] transition hover:-translate-y-[1px] hover:bg-emerald-700"
                            >
                                Resubmit for Review
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($showPayNow): ?>
                        <a
                            class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-500 px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(245,158,11,0.24)] transition hover:-translate-y-[1px] hover:bg-amber-600"
                            href="<?= e($payNowUrl) ?>"
                        >
                            Pay Now
                        </a>
                    <?php endif; ?>

                    <a
                        class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        href="<?= e(base_url('client/orders')) ?>"
                    >
                        Back to Orders
                    </a>
                </div>
            </div>
        </div>

        <?php if ($hasWrongDocs): ?>
            <div class="border-b border-rose-100 bg-rose-50 px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-rose-900">Document correction required</h3>
                        <p class="mt-1 text-sm leading-6 text-rose-800">
                            Our team has marked one or more uploaded documents as incorrect. Please upload the corrected file below to replace the older wrong upload.
                        </p>
                    </div>

                    <a
                        href="#my-documents"
                        class="inline-flex h-11 items-center justify-center rounded-2xl bg-rose-600 px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(225,29,72,0.20)] transition hover:-translate-y-[1px] hover:bg-rose-700"
                    >
                        Go to My Documents
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showPayNow): ?>
            <div class="border-b border-amber-100 bg-amber-50 px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-amber-900">Payment is pending</h3>
                        <p class="mt-1 text-sm leading-6 text-amber-800">
                            Complete your payment to continue processing this order.
                        </p>
                    </div>

                    <a
                        href="<?= e($payNowUrl) ?>"
                        class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-500 px-5 text-sm font-bold text-white shadow-[0_12px_24px_rgba(245,158,11,0.24)] transition hover:-translate-y-[1px] hover:bg-amber-600"
                    >
                        Pay Now
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4 sm:p-5">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Application ID</div>
                <div class="mt-2 text-sm font-bold text-slate-900"><?= e($orderNo) ?></div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Status</div>
                <div class="mt-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($orderStatusTone((string) ($order['status'] ?? ''))) ?>">
                        <?= e($orderStatusLabel((string) ($order['status'] ?? 'unknown'))) ?>
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border <?= $showPayNow ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' ?> p-4">
                <div class="text-[11px] font-semibold uppercase tracking-[0.14em] <?= $showPayNow ? 'text-amber-700' : 'text-slate-500' ?>">Payment</div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($orderStatusTone($paymentStatus)) ?>">
                        <?= e($orderStatusLabel($paymentStatus)) ?>
                    </span>
                </div>

                <?php if ($paymentReference !== ''): ?>
                    <div class="mt-2 text-xs font-medium text-slate-500">
                        Ref: <?= e($paymentReference) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500"><?= e($feeLabel) ?></div>
                <div class="mt-2 text-sm font-bold text-slate-900">
                    <?= e(format_money($feeAmount)) ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canEditOrder): ?>
        <section id="edit-order" class="overflow-hidden rounded-[22px] border border-brand-200 bg-white shadow-[0_14px_34px_rgba(37,99,235,0.08)]">
            <div class="border-b border-brand-100 bg-brand-50/60 px-4 py-4 sm:px-5">
                <h3 class="text-sm font-bold text-slate-900">Edit order details</h3>
                <p class="mt-1 text-xs leading-5 text-slate-600">
                    Update the customer information or notes before resubmitting. Service and fee changes require administrator approval.
                </p>
            </div>

            <form method="post" action="<?= e(base_url($portalActionBase . '/update')) ?>" class="grid gap-4 p-4 sm:p-5">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Name as per PAN</span>
                        <input
                            type="text"
                            name="name_as_per_pan"
                            required
                            maxlength="190"
                            value="<?= e((string) ($customerDetails['name_as_per_pan'] ?? '')) ?>"
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">PAN number</span>
                        <input
                            type="text"
                            name="pan_number"
                            required
                            maxlength="20"
                            value="<?= e((string) ($customerDetails['pan_number'] ?? '')) ?>"
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm uppercase text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Mobile</span>
                        <input
                            type="text"
                            name="mobile"
                            required
                            maxlength="30"
                            value="<?= e((string) ($customerDetails['mobile'] ?? '')) ?>"
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Email</span>
                        <input
                            type="email"
                            name="email"
                            required
                            maxlength="190"
                            value="<?= e((string) ($customerDetails['email'] ?? '')) ?>"
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        >
                    </label>

                    <label class="grid gap-2 md:col-span-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Financial year</span>
                        <input
                            type="text"
                            name="financial_year"
                            maxlength="255"
                            value="<?= e((string) ($order['financial_year'] ?? '')) ?>"
                            class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        >
                    </label>

                    <label class="grid gap-2 md:col-span-2">
                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Order notes</span>
                        <textarea
                            name="notes"
                            rows="4"
                            maxlength="5000"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                        ><?= e((string) ($order['notes'] ?? '')) ?></textarea>
                    </label>
                </div>

                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-slate-500">Save your corrections first, then use “Resubmit for Review” after all required files are correct.</p>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-slate-800">Save Changes</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-2">
        <div id="my-documents" class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">My Documents</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Review uploaded files, correct rejected documents, or add another document to this order.
                        </p>
                    </div>

                    <?php if ($hasWrongDocs): ?>
                        <span class="inline-flex w-fit items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-rose-700">
                            <?= e((string) count($wrongDocs)) ?> correction<?= count($wrongDocs) === 1 ? '' : 's' ?> required
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-5 p-4 sm:p-5">
                <?php
                    $wrongDocIds = [];
                    foreach ($wrongDocs as $wrongDoc) {
                        $wrongId = (int) ($wrongDoc['id'] ?? 0);
                        if ($wrongId > 0) {
                            $wrongDocIds[$wrongId] = true;
                        }
                    }
                ?>

                <?php if ($hasWrongDocs): ?>
                    <section aria-label="Documents requiring correction" class="space-y-3">
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
                            <div class="text-sm font-bold text-rose-900">Correction required</div>
                            <p class="mt-1 text-xs leading-5 text-rose-700">
                                Upload the correct file inside the matching document card. Each form replaces only that specific rejected document.
                            </p>
                        </div>

                        <?php foreach ($wrongDocs as $doc): ?>
                            <?php
                                $wrongDocumentId = (int) ($doc['id'] ?? 0);
                                $wrongDocumentName = trim((string) (($doc['label'] ?? '') ?: ($doc['original_name'] ?? 'Document')));
                                $wrongOriginalName = trim((string) ($doc['original_name'] ?? ''));
                                $wrongReason = trim((string) ($doc['wrong_reason'] ?? ''));
                            ?>

                            <article class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-[0_10px_24px_rgba(225,29,72,0.06)]">
                                <div class="bg-rose-50/80 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex rounded-full bg-rose-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-white">
                                                    Wrong document
                                                </span>

                                                <?php if (!empty($doc['wrong_marked_at'])): ?>
                                                    <span class="text-xs font-medium text-slate-500">
                                                        Marked <?= e(format_date($doc['wrong_marked_at'] ?? null)) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <h4 class="mt-3 break-words text-sm font-bold text-slate-900">
                                                <?= e($wrongDocumentName !== '' ? $wrongDocumentName : 'Document') ?>
                                            </h4>

                                            <?php if ($wrongOriginalName !== ''): ?>
                                                <p class="mt-1 break-all text-xs text-slate-500">
                                                    Uploaded file: <?= e($wrongOriginalName) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mt-3 rounded-xl border border-rose-200 bg-white px-3 py-3 text-sm leading-6 text-rose-800">
                                        <span class="font-bold">Why it was rejected:</span>
                                        <?= e($wrongReason !== '' ? $wrongReason : 'Please upload a clear and correct version of this document.') ?>
                                    </div>
                                </div>

                                <form
                                    method="post"
                                    action="<?= e(base_url($portalActionBase . '/reupload-document')) ?>"
                                    enctype="multipart/form-data"
                                    class="space-y-3 border-t border-rose-100 p-4"
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="document_id" value="<?= e((string) $wrongDocumentId) ?>">

                                    <label class="grid gap-2">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-700">
                                            Upload corrected <?= e($wrongDocumentName !== '' ? $wrongDocumentName : 'document') ?>
                                        </span>
                                        <input
                                            type="file"
                                            name="document"
                                            required
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                            class="min-h-12 w-full rounded-xl border border-rose-200 bg-rose-50/50 px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                        >
                                    </label>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs leading-5 text-slate-500">
                                            PDF, JPG, PNG, WEBP, DOC, DOCX, XLS or XLSX. Maximum 10 MB.
                                        </p>
                                        <button
                                            type="submit"
                                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-5 text-sm font-bold text-white shadow-[0_10px_20px_rgba(225,29,72,0.18)] transition hover:-translate-y-px hover:bg-rose-700"
                                        >
                                            Re-upload Correct Document
                                        </button>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <section aria-label="Uploaded documents">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h4 class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Uploaded documents</h4>
                        <span class="text-xs font-semibold text-slate-400"><?= e((string) count($submittedDocs)) ?> total</span>
                    </div>

                    <?php if ($submittedDocs !== []): ?>
                        <div class="space-y-3">
                            <?php foreach ($submittedDocs as $doc): ?>
                                <?php
                                    $docId = (int) ($doc['id'] ?? 0);
                                    if ($docId > 0 && isset($wrongDocIds[$docId])) {
                                        continue;
                                    }

                                    $docStatus = strtolower(trim((string) ($doc['document_status'] ?? 'active')));
                                    $docName = trim((string) (($doc['label'] ?? '') ?: ($doc['original_name'] ?? 'Document')));
                                    $originalName = trim((string) ($doc['original_name'] ?? ''));
                                ?>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="break-words text-sm font-bold text-slate-800">
                                                <?= e($docName !== '' ? $docName : 'Document') ?>
                                            </div>
                                            <?php if ($originalName !== ''): ?>
                                                <div class="mt-1 break-all text-xs text-slate-500"><?= e($originalName) ?></div>
                                            <?php endif; ?>
                                            <div class="mt-1 text-xs text-slate-400"><?= e(format_date($doc['created_at'] ?? null)) ?></div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($documentStatusTone($docStatus)) ?>">
                                                <?= e($documentStatusLabel($docStatus)) ?>
                                            </span>
                                            <?php if ($isPartnerOrder && $docId > 0): ?>
                                                <a
                                                    class="text-sm font-bold text-brand-700 hover:text-brand-800"
                                                    href="<?= e(base_url($portalActionBase . '/download?document_id=' . $docId)) ?>"
                                                >
                                                    Download
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm text-slate-500">
                            No documents have been uploaded for this order yet.
                        </div>
                    <?php endif; ?>
                </section>

                <section id="upload-another-document" class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4">
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-slate-900">Upload another document</h4>
                        <p class="mt-1 text-xs leading-5 text-slate-600">
                            Add an individual supporting document to this specific order. This does not replace a rejected document.
                        </p>
                    </div>

                    <form
                        method="post"
                        action="<?= e(base_url($portalActionBase . '/upload-document')) ?>"
                        enctype="multipart/form-data"
                        class="grid gap-3"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-2">
                                <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Document name</span>
                                <input
                                    type="text"
                                    name="label"
                                    required
                                    maxlength="190"
                                    placeholder="Example: Additional bank statement"
                                    class="min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-brand-400 focus:ring-4 focus:ring-brand-100"
                                >
                            </label>

                            <label class="grid gap-2">
                                <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-600">Choose file</span>
                                <input
                                    type="file"
                                    name="document"
                                    required
                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                    class="min-h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                >
                            </label>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs leading-5 text-slate-500">
                                Allowed formats: PDF, images, Word and Excel files up to 10 MB.
                            </p>
                            <button
                                type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-bold text-white shadow-[0_10px_20px_rgba(15,23,42,0.14)] transition hover:-translate-y-px hover:bg-slate-800"
                            >
                                Upload Document
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                <h3 class="text-sm font-bold text-slate-900">Download Files</h3>
            </div>

            <div class="p-4 sm:p-5">
                <?php if (!empty($deliveredDocs)): ?>
                    <div class="space-y-3">
                        <?php foreach ($deliveredDocs as $doc): ?>
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                                <div>
                                    <div class="text-sm font-semibold text-slate-800"><?= e($doc['original_name'] ?? 'File') ?></div>
                                    <div class="mt-1 text-xs text-slate-500"><?= e(format_date($doc['created_at'] ?? null)) ?></div>
                                </div>

                                <a
                                    href="<?= e(base_url($portalActionBase . '/download?document_id=' . ($doc['id'] ?? 0))) ?>"
                                    class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3.5 text-[12px] font-semibold text-white"
                                >
                                    Download
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-500">No delivered files available yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-hidden rounded-[22px] border <?= $showPayNow ? 'border-amber-200' : 'border-slate-200' ?> bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]">
            <div class="flex flex-col gap-3 border-b <?= $showPayNow ? 'border-amber-100 bg-amber-50' : 'border-slate-100' ?> px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h3 class="text-sm font-bold <?= $showPayNow ? 'text-amber-900' : 'text-slate-900' ?>">Payments</h3>
                    <?php if ($showPayNow): ?>
                        <p class="mt-1 text-xs text-amber-700">Payment is pending for this order.</p>
                    <?php endif; ?>
                </div>

                <?php if ($showPayNow): ?>
                    <a
                        href="<?= e($payNowUrl) ?>"
                        class="inline-flex h-9 items-center justify-center rounded-xl bg-amber-500 px-3.5 text-[12px] font-bold text-white transition hover:bg-amber-600"
                    >
                        Pay Now
                    </a>
                <?php endif; ?>
            </div>

            <div class="p-4 sm:p-5">
                <?php if (!empty($payments)): ?>
                    <div class="space-y-3">
                        <?php foreach ($payments as $payment): ?>
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-800">
                                        <?= e(format_money((float) ($payment['amount'] ?? 0))) ?>
                                    </div>

                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($orderStatusTone((string) ($payment['status'] ?? ''))) ?>">
                                        <?= e($orderStatusLabel((string) ($payment['status'] ?? 'unknown'))) ?>
                                    </span>
                                </div>

                                <?php if (!empty($payment['reference'])): ?>
                                    <div class="mt-1 text-xs text-slate-500">
                                        Ref: <?= e((string) $payment['reference']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-1 text-xs text-slate-500"><?= e(format_date($payment['created_at'] ?? null)) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if ($showPayNow): ?>
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <div class="text-sm font-bold text-amber-900">No payment submitted yet.</div>
                            <p class="mt-1 text-sm leading-6 text-amber-800">
                                Click Pay Now to submit your UPI transaction ID and payment screenshot.
                            </p>

                            <a
                                href="<?= e($payNowUrl) ?>"
                                class="mt-4 inline-flex h-10 items-center justify-center rounded-xl bg-amber-500 px-4 text-sm font-bold text-white transition hover:bg-amber-600"
                            >
                                Pay Now
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-500">No payment records found.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($paymentProofs)): ?>
                    <div class="mt-5">
                        <h4 class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Payment Proofs</h4>

                        <div class="mt-3 space-y-3">
                            <?php foreach ($paymentProofs as $proof): ?>
                                <div class="rounded-2xl border border-slate-200 p-4">
                                    <div class="text-sm font-semibold text-slate-800"><?= e($proof['original_name'] ?? 'Payment Proof') ?></div>
                                    <div class="mt-1 text-xs text-slate-500"><?= e(format_date($proof['created_at'] ?? null)) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                <h3 class="text-sm font-bold text-slate-900">Activity Log</h3>
            </div>

            <div class="p-4 sm:p-5">
                <?php if (!empty($activity)): ?>
                    <div class="space-y-3">
                        <?php foreach ($activity as $item): ?>
                            <div class="rounded-2xl border border-slate-200 p-4">
                                <div class="text-sm font-semibold text-slate-800"><?= e($item['message'] ?? ($item['action'] ?? 'Activity')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e(format_date($item['created_at'] ?? null)) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-500">No activity available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>