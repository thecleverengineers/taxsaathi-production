<?php
declare(strict_types=1);

$order = is_array($order ?? null) ? $order : [];
$clientDocs = is_array($clientDocs ?? null) ? array_values($clientDocs) : [];
$outputDocs = is_array($outputDocs ?? null) ? array_values($outputDocs) : [];
$paymentProofs = is_array($paymentProofs ?? null) ? array_values($paymentProofs) : [];
$invoice = is_array($invoice ?? null) ? $invoice : [];
$payments = is_array($payments ?? null) ? array_values($payments) : [];

$statusLabel = static function (string $status): string {
    $status = strtolower(trim($status));

    return match ($status) {
        'submitted', 'submited', 'pending', 'pending_review' => 'Pending',
        'clarification', 'pending_clarification' => 'Pending Clarification',
        'work_in_progress' => 'Work In Progress',
        default => $status !== '' ? ucwords(str_replace('_', ' ', $status)) : '-',
    };
};

$statusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'verified', 'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'work_in_progress' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
        'clarification', 'pending_clarification', 'pending_review', 'pending', 'submitted', 'submited' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$dateText = static function (mixed $value): string {
    $value = trim((string) $value);

    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime($value);

    return $ts ? date('d M Y, h:i A', $ts) : $value;
};

$downloadUrl = static function (array $doc): string {
    return base_url('partner/assigned-orders/download?document_id=' . (int) ($doc['id'] ?? 0));
};

$isImage = static function (array $doc): bool {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return true;
    }

    $name = strtolower((string) ($doc['original_name'] ?? $doc['stored_name'] ?? ''));
    $ext = pathinfo($name, PATHINFO_EXTENSION);

    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
};

$fileType = static function (array $doc): string {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return 'IMAGE';
    }

    $name = strtolower((string) ($doc['original_name'] ?? $doc['stored_name'] ?? ''));
    $ext = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));

    return $ext !== '' ? $ext : 'FILE';
};

$renderDocs = static function (array $docs, string $empty, bool $allowWrong = false) use ($downloadUrl, $isImage, $fileType, $statusTone): void {
    if ($docs === []) {
        ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm font-bold text-slate-500">
            <?= e($empty) ?>
        </div>
        <?php
        return;
    }

    foreach ($docs as $doc):
        $docStatus = strtolower(trim((string) ($doc['document_status'] ?? 'active')));
        $wrongReason = trim((string) ($doc['wrong_reason'] ?? ''));
        $name = trim((string) ($doc['label'] ?? '')) ?: trim((string) ($doc['original_name'] ?? 'Document'));
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-[10px] font-black tracking-[0.12em] text-slate-500">
                        <?= e($fileType($doc)) ?>
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-sm font-black text-slate-900"><?= e($name) ?></div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-400">
                            <span><?= e((string) ($doc['original_name'] ?? '-')) ?></span>
                            <span>·</span>
                            <span><?= e((string) ($doc['created_at'] ?? '-')) ?></span>
                        </div>

                        <?php if ($wrongReason !== ''): ?>
                            <div class="mt-2 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">
                                Reason: <?= e($wrongReason) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone($docStatus)) ?>">
                        <?= e(ucwords(str_replace('_', ' ', $docStatus))) ?>
                    </span>

                    <a href="<?= e($downloadUrl($doc)) ?>" target="_blank" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 transition hover:border-indigo-200 hover:text-indigo-700">
                        View
                    </a>
                </div>
            </div>

            <?php if ($allowWrong && $docStatus === 'active'): ?>
                <form method="post" action="<?= e(base_url('partner/assigned-orders/mark-document-wrong')) ?>" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_id" value="<?= e((string) ($doc['id'] ?? 0)) ?>">
                    <input
                        type="text"
                        name="wrong_reason"
                        required
                        maxlength="500"
                        placeholder="Reason for re-upload request"
                        class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 outline-none focus:border-rose-300 focus:bg-white"
                    >
                    <button class="h-10 rounded-xl bg-rose-600 px-4 text-xs font-black text-white transition hover:bg-rose-700" type="submit">
                        Mark Wrong
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach;
};

$currentStatus = strtolower(trim((string) ($order['status'] ?? '')));
$canAccept = in_array($currentStatus, ['submitted', 'submited', 'pending', 'pending_review', 'clarification', 'pending_clarification'], true);
?>

<section class="space-y-6">
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 bg-slate-950 px-5 py-5 sm:px-7 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-indigo-200">Partner Assigned Order</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-white">
                    <?= e((string) ($order['order_no'] ?? 'Order Details')) ?>
                </h1>
                <p class="mt-2 text-sm text-slate-300">
                    Manage work execution, client documents and final deliverables for this assigned order.
                </p>
            </div>

            <a href="<?= e(base_url('partner/assigned-orders')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white transition hover:bg-white/15">
                ← Back
            </a>
        </div>

        <div class="grid gap-4 p-5 sm:p-7 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Client</div>
                <div class="mt-2 text-base font-black text-slate-950"><?= e((string) ($order['client_name'] ?? '-')) ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($order['client_phone'] ?? '-')) ?></div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Service</div>
                <div class="mt-2 text-base font-black text-slate-950"><?= e((string) ($order['service_title'] ?? '-')) ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($order['financial_year'] ?? '-')) ?></div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Order Status</div>
                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone((string) ($order['status'] ?? ''))) ?>">
                    <?= e($statusLabel((string) ($order['status'] ?? ''))) ?>
                </span>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Payment</div>
                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone((string) ($order['payment_status'] ?? ''))) ?>">
                    <?= e($statusLabel((string) ($order['payment_status'] ?? ''))) ?>
                </span>
            </div>
        </div>
    </div>

    <div id="status" class="grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Quick Action</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Accept / Update Work</h2>

            <?php if ($canAccept): ?>
                <form method="post" action="<?= e(base_url('partner/assigned-orders/status')) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">
                    <input type="hidden" name="status" value="work_in_progress">
                    <input type="hidden" name="admin_notes" value="<?= e((string) ($order['admin_notes'] ?? '')) ?>">
                    <input type="hidden" name="completion_note" value="<?= e((string) ($order['completion_note'] ?? '')) ?>">

                    <button class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800" type="submit">
                        Accept & Start Work
                    </button>
                </form>
            <?php else: ?>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-600">
                    This order has already entered the workflow.
                </div>
            <?php endif; ?>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Status Update</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Update Order Progress</h2>

            <form method="post" action="<?= e(base_url('partner/assigned-orders/status')) ?>" class="mt-5 grid gap-4">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Order Status</span>
                    <select name="status" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                        <?php
                        $options = [
                            'work_in_progress' => 'Work In Progress',
                            'clarification' => 'Pending Clarification',
                            'completed' => 'Completed',
                            'rejected' => 'Rejected',
                        ];
                        ?>
                        <?php foreach ($options as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $currentStatus === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Internal Notes</span>
                    <textarea name="admin_notes" rows="3" class="min-h-[90px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"><?= e((string) ($order['admin_notes'] ?? '')) ?></textarea>
                </label>

                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Completion Note</span>
                    <textarea name="completion_note" rows="3" class="min-h-[90px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"><?= e((string) ($order['completion_note'] ?? '')) ?></textarea>
                </label>

                <button class="h-12 rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white transition hover:bg-indigo-700" type="submit">
                    Save Status
                </button>
            </form>
        </div>
    </div>

    <div id="client-documents" class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Client Documents</p>
        <h2 class="mt-1 text-xl font-black text-slate-950">Uploaded Client Files</h2>
        <div class="mt-5 grid gap-3">
            <?php $renderDocs($clientDocs, 'No client documents available.', true); ?>
        </div>
    </div>

    <div id="payment" class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Payment Proof</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Proof Files</h2>
            <div class="mt-5 grid gap-3">
                <?php $renderDocs($paymentProofs, 'No payment proof uploaded.', false); ?>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Invoice / Payment Summary</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Read-only Summary</h2>

            <div class="mt-5 grid gap-3">
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-500">Order Amount</span>
                    <span class="text-sm font-black text-slate-950"><?= e($money($order['fee_amount'] ?? 0)) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-500">Invoice No</span>
                    <span class="text-sm font-black text-slate-950"><?= e((string) ($invoice['invoice_no'] ?? '-')) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-500">Invoice Status</span>
                    <span class="text-sm font-black text-slate-950"><?= e($statusLabel((string) ($invoice['status'] ?? $order['payment_status'] ?? '-'))) ?></span>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm font-bold text-slate-500">Payment Records</span>
                    <span class="text-sm font-black text-slate-950"><?= e((string) count($payments)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <div id="deliverables" class="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Upload Deliverables</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Final Output Files</h2>

            <form method="post" action="<?= e(base_url('partner/assigned-orders/upload-output')) ?>" enctype="multipart/form-data" class="mt-5 grid gap-4">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">File Label</span>
                    <input name="label" value="Completed File" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                </label>

                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Choose Files</span>
                    <input type="file" name="documents[]" multiple required class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-600">
                    <span class="text-xs font-semibold text-slate-400">Allowed: PDF, images, DOC, DOCX, XLS, XLSX. Max 10MB each.</span>
                </label>

                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                    <input type="checkbox" name="is_client_visible" value="1" checked class="rounded border-slate-300">
                    Visible to client
                </label>

                <button class="h-12 rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-700" type="submit">
                    Upload & Mark Completed
                </button>
            </form>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Uploaded Deliverables</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Output Documents</h2>
            <div class="mt-5 grid gap-3">
                <?php $renderDocs($outputDocs, 'No deliverables uploaded yet.', false); ?>
            </div>
        </div>
    </div>
</section>