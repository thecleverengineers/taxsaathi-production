<?php
declare(strict_types=1);

$order = is_array($order ?? null) ? $order : [];
$assignees = is_array($assignees ?? null) ? $assignees : [];
$paymentProofs = is_array($paymentProofs ?? null) ? $paymentProofs : [];
$clientDocs = is_array($clientDocs ?? null) ? $clientDocs : [];
$outputDocs = is_array($outputDocs ?? null) ? $outputDocs : [];
$payments = is_array($payments ?? null) ? $payments : [];
$invoice = is_array($invoice ?? null) ? $invoice : [];

$orderStatusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'success', 'verified' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending', 'pending_review', 'processing', 'clarification', 'work_in_progress', 'partial' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'submitted' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$orderStatusLabel = static function (string $status): string {
    $status = trim($status);
    return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : '-';
};

$orderStatusDisplayLabel = static function (string $status) use ($orderStatusLabel): string {
    $key = strtolower(trim($status));

    if (in_array($key, ['submitted', 'submited'], true)) {
        return 'Pending';
    }

    return $orderStatusLabel($status);
};

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$isRoleThreeUser = (bool) ($isRoleThreeUser ?? false);
$canAssignOrders = (bool) ($canAssignOrders ?? !$isRoleThreeUser);
$clientDocsLocked = (bool) ($clientDocsLocked ?? false);

/*
 * Admin / Manager / assigned Executive can view client documents directly.
 * For these internal roles, the old "Client Documents Unlock" gate must not
 * appear in the workflow UI and must not block document preview/download.
 */
$canViewClientDocsDirectly = (bool) (
    $canViewClientDocsDirectly
    ?? $adminCanViewClientDocsDirectly
    ?? false
);
$adminCanViewClientDocsDirectly = $canViewClientDocsDirectly; // backward compatibility for older view references
$hideClientDocsUnlockSection = (bool) ($hideClientDocsUnlockSection ?? $canViewClientDocsDirectly);
$clientDocsVisibleForCurrentUser = !$clientDocsLocked || $canViewClientDocsDirectly;

$assigneeMap = [];
$assigneeDetailsMap = [];
foreach ($assignees as $staff) {
    $staffId = (int) ($staff['id'] ?? 0);
    if ($staffId > 0) {
        $assigneeMap[$staffId] = trim((string) ($staff['name'] ?? ('User #' . $staffId)));
        $assigneeDetailsMap[$staffId] = [
            'name' => $assigneeMap[$staffId],
            'email' => trim((string) ($staff['email'] ?? '')),
            'phone' => trim((string) ($staff['phone'] ?? $staff['mobile'] ?? '')),
        ];
    }
}

$resolveAssigneeName = static function ($assignedUserId) use ($assigneeMap): string {
    $assignedUserId = (int) $assignedUserId;
    if ($assignedUserId <= 0) {
        return 'Unassigned';
    }

    return $assigneeMap[$assignedUserId] ?? ('User #' . $assignedUserId);
};

$currentAssignedUserId = (int) ($order['assigned_user_id'] ?? 0);
$currentAssignedUserName = $resolveAssigneeName($currentAssignedUserId);
$currentAssignedUserDetails = $assigneeDetailsMap[$currentAssignedUserId] ?? [
    'name' => $currentAssignedUserName,
    'email' => '',
    'phone' => '',
];

/*
 * Display Financial Year whenever the order contains a non-empty value.
 * The value is treated as normal text and is not restricted by service slug.
 */
$financialYearText = trim(html_entity_decode(
    (string) ($financialYearText ?? $order['financial_year'] ?? ''),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
));
$showFinancialYear = (bool) ($financialYearAvailable ?? ($financialYearText !== ''));

$orderWorkflowStatusOptions = ['pending_review', 'clarification', 'approved', 'work_in_progress', 'completed', 'rejected'];
$invoiceStatusOptions = ['unpaid', 'pending', 'partial', 'paid'];

$paymentTotalRecorded = 0.0;
foreach ($payments as $payment) {
    $paymentTotalRecorded += (float) ($payment['amount'] ?? 0);
}

$orderFeeAmount = (float) ($order['fee_amount'] ?? 0);
$invoiceTotalAmount = (float) ($invoice['total_amount'] ?? $orderFeeAmount);
$invoicePaidAmount = (float) ($invoice['paid_amount'] ?? 0);
$totalPaidForSummary = $paymentTotalRecorded > 0 ? $paymentTotalRecorded : $invoicePaidAmount;
$balanceDueForSummary = max(0, $invoiceTotalAmount - $totalPaidForSummary);

$orderStatusText = $orderStatusDisplayLabel((string) ($order['status'] ?? ''));
$paymentStatusText = $orderStatusLabel((string) ($order['payment_status'] ?? ($invoice['status'] ?? '')));
$invoiceStatusText = $orderStatusLabel((string) ($invoice['status'] ?? 'unpaid'));
$clientDocumentCount = count($clientDocs);
$outputDocumentCount = count($outputDocs);
$paymentProofCount = count($paymentProofs);
$paymentRecordCount = count($payments);

$currentOrderStatusKey = strtolower(trim((string) ($order['status'] ?? 'submitted')));
if ($currentOrderStatusKey === 'submited') {
    $currentOrderStatusKey = 'submitted';
}
if ($currentOrderStatusKey === '') {
    $currentOrderStatusKey = 'submitted';
}

$currentPaymentStatusKey = strtolower(trim((string) ($order['payment_status'] ?? ($invoice['status'] ?? 'pending'))));
if ($currentPaymentStatusKey === '') {
    $currentPaymentStatusKey = 'pending';
}

$workflowIntakeStatuses = ['submitted', 'submited', 'pending_review', 'clarification'];
$workflowAcceptedStatuses = ['approved', 'work_in_progress', 'completed'];
$canAcceptToWorkInProgress = in_array($currentOrderStatusKey, $workflowIntakeStatuses, true);

$serviceTurnaroundDays = (int) (
    $order['service_turnaround_days']
    ?? $order['turnaround_days']
    ?? $order['service_tat_days']
    ?? 0
);

$orderCreatedAtRaw = trim((string) ($order['created_at'] ?? ''));
$orderDueDateIso = trim((string) ($order['order_due_date'] ?? $order['due_date'] ?? ''));

$formatWorkflowDate = static function (?string $value, string $fallback = '-'): string {
    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable $e) {
        return $value;
    }
};

if ($orderDueDateIso === '' && $serviceTurnaroundDays > 0 && $orderCreatedAtRaw !== '') {
    try {
        $orderDueDateIso = (new DateTimeImmutable($orderCreatedAtRaw))
            ->modify('+' . $serviceTurnaroundDays . ' days')
            ->format('Y-m-d');
    } catch (Throwable $e) {
        $orderDueDateIso = '';
    }
}

$isOrderClosed = in_array($currentOrderStatusKey, ['completed', 'rejected', 'cancelled'], true);
$orderDueDateText = $formatWorkflowDate($orderDueDateIso, '-');
$orderDueStatusText = $serviceTurnaroundDays > 0 ? 'No due date' : 'No TAT';
$orderDueTone = 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
$orderDueUrgency = 9999;

if ($isOrderClosed) {
    $orderDueStatusText = 'Closed';
    $orderDueTone = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
    $orderDueUrgency = 9000;
} elseif ($orderDueDateIso !== '') {
    try {
        $today = new DateTimeImmutable('today');
        $dueDate = new DateTimeImmutable($orderDueDateIso);
        $daysLeft = (int) $today->diff($dueDate)->format('%r%a');
        $orderDueUrgency = $daysLeft;

        if ($daysLeft === 0) {
            $orderDueStatusText = 'Due today';
            $orderDueTone = 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
        } elseif ($daysLeft < 0) {
            $orderDueStatusText = abs($daysLeft) . 'd overdue';
            $orderDueTone = 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
        } else {
            $orderDueStatusText = $daysLeft . 'd left';
            $orderDueTone = $daysLeft <= 2
                ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
        }
    } catch (Throwable $e) {
        $orderDueStatusText = 'Invalid due date';
        $orderDueTone = 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
    }
}

$paymentCleared = in_array($currentPaymentStatusKey, ['paid', 'verified', 'success'], true);
$orderApproved = in_array($currentOrderStatusKey, ['approved', 'work_in_progress', 'completed'], true);
$workStarted = in_array($currentOrderStatusKey, ['work_in_progress', 'completed'], true);
$orderCompleted = $currentOrderStatusKey === 'completed';
$orderRejected = in_array($currentOrderStatusKey, ['rejected', 'cancelled'], true);
$hasClientDocs = $clientDocumentCount > 0;
$hasOutputDocs = $outputDocumentCount > 0;
$invoiceStatusKey = strtolower(trim((string) ($invoice['status'] ?? '')));
$hasInvoice = $invoice !== [] && (
    (int) ($invoice['id'] ?? 0) > 0
    || trim((string) ($invoice['invoice_no'] ?? '')) !== ''
    || isset($invoice['total_amount'])
);
$invoiceClosed = in_array($invoiceStatusKey, ['paid'], true) || ($invoiceTotalAmount > 0 && $balanceDueForSummary <= 0.01);

$workflowStateTone = static function (string $state): string {
    return match ($state) {
        'completed' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'current' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
        'blocked' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
    };
};

$workflowStepIcon = static function (string $state): string {
    return match ($state) {
        'completed' => '✓',
        'current' => '→',
        'blocked' => '!',
        default => '•',
    };
};

$orderAccepted = in_array($currentOrderStatusKey, $workflowAcceptedStatuses, true);
$staffAssigned = $currentAssignedUserId > 0;
$documentsUnlocked = !$clientDocsLocked || $canViewClientDocsDirectly;
$clientDocsAvailableForAdmin = $clientDocsVisibleForCurrentUser && $hasClientDocs;
$workExecutionReady = $orderAccepted && $staffAssigned && $documentsUnlocked;
$invoiceReady = $hasInvoice || $paymentCleared || $paymentRecordCount > 0;

$advancedWorkflowSteps = [
    [
        'step' => '1',
        'tab' => 'overview',
        'title' => 'Overview + Pending Accept',
        'state' => $orderRejected ? 'blocked' : ($orderAccepted ? 'completed' : 'current'),
        'summary' => $orderAccepted ? 'Order accepted into workflow.' : 'Pending order waiting for acceptance.',
        'action' => 'Review order details and accept the pending order to move it to Work In Progress.',
    ],
    [
        'step' => '2',
        'tab' => 'assign',
        'title' => 'Assign Staff',
        'state' => $staffAssigned ? 'completed' : ($orderAccepted ? 'current' : 'waiting'),
        'summary' => 'Assigned to ' . $currentAssignedUserName . '.',
        'action' => 'Assign a role_id = 3 executive responsible for this order.',
    ],
    [
        'step' => '3',
        'tab' => 'approval',
        'title' => 'Client Documents Unlock',
        'state' => $documentsUnlocked ? 'completed' : ($staffAssigned || $orderAccepted ? 'current' : 'waiting'),
        'summary' => $documentsUnlocked
            ? ($clientDocumentCount . ' client document(s) unlocked.')
            : ($adminCanViewClientDocsDirectly
                ? ('Admin preview available · ' . $clientDocumentCount . ' document(s).')
                : 'Client documents are locked.'),
        'action' => $adminCanViewClientDocsDirectly
            ? 'Admin can view documents now. Use unlock only when the documents should enter the processing/client workflow.'
            : 'Accept the unlock gate so assigned staff can view and process client documents.',
    ],
    [
        'step' => '4',
        'tab' => 'status',
        'title' => 'Work Execution',
        'state' => $orderCompleted ? 'completed' : ($workStarted ? 'current' : ($workExecutionReady ? 'current' : 'waiting')),
        'summary' => 'Execution status: ' . $orderStatusText . '.',
        'action' => 'Update internal notes, client-facing completion note and execution status.',
    ],
    [
        'step' => '5',
        'tab' => 'payment-proof',
        'title' => 'Payment Verification',
        'state' => $paymentCleared ? 'completed' : (($workStarted || $orderAccepted) ? 'current' : 'waiting'),
        'summary' => $paymentCleared ? 'Payment is verified/paid.' : 'Verify proof or record manual payment.',
        'action' => 'Check uploaded payment proof and save verified payment entry.',
    ],
    [
        'step' => '6',
        'tab' => 'invoice',
        'title' => 'Invoicing',
        'state' => ($hasInvoice && $invoiceClosed) ? 'completed' : ($invoiceReady ? 'current' : 'waiting'),
        'summary' => 'Invoice status: ' . $invoiceStatusText . '.',
        'action' => 'Create or update invoice after payment verification.',
    ],
    [
        'step' => '7',
        'tab' => 'deliverables',
        'title' => 'Upload Deliverables',
        'state' => $hasOutputDocs ? 'completed' : (($hasInvoice || $paymentCleared) ? 'current' : 'waiting'),
        'summary' => $outputDocumentCount . ' output file(s) uploaded.',
        'action' => 'Upload final deliverables and close the order.',
    ],
];

if ($hideClientDocsUnlockSection) {
    $advancedWorkflowSteps = array_values(array_filter(
        $advancedWorkflowSteps,
        static fn (array $step): bool => (string) ($step['tab'] ?? '') !== 'approval'
    ));

    array_splice($advancedWorkflowSteps, 2, 0, [[
        'step' => '3',
        'tab' => 'client-uploads',
        'title' => 'Client Documents',
        'state' => $hasClientDocs ? 'completed' : 'current',
        'summary' => $clientDocumentCount . ' client document(s) available.',
        'action' => 'View client documents directly. No unlock step is required for your role.',
    ]]);
}

$workflowCompletedCount = 0;
$workflowCurrentStep = $advancedWorkflowSteps[0] ?? [];
foreach ($advancedWorkflowSteps as $workflowStep) {
    if (($workflowStep['state'] ?? '') === 'completed') {
        $workflowCompletedCount++;
        continue;
    }

    if (($workflowCurrentStep['state'] ?? '') === 'completed') {
        $workflowCurrentStep = $workflowStep;
    }
}

$workflowTotalSteps = max(1, count($advancedWorkflowSteps));
$workflowProgressPercent = (int) round(($workflowCompletedCount / $workflowTotalSteps) * 100);
$defaultWorkflowTabId = (string) ($workflowCurrentStep['tab'] ?? 'overview');
if ($defaultWorkflowTabId === '') {
    $defaultWorkflowTabId = 'overview';
}

$workflowTabStateMap = [];
$workflowTabActionMap = [];
foreach ($advancedWorkflowSteps as $workflowStep) {
    $tabId = (string) ($workflowStep['tab'] ?? '');
    if ($tabId === '') {
        continue;
    }

    $state = (string) ($workflowStep['state'] ?? 'waiting');
    $existingState = (string) ($workflowTabStateMap[$tabId] ?? '');
    $rank = ['blocked' => 4, 'current' => 3, 'waiting' => 2, 'completed' => 1, '' => 0];
    if (($rank[$state] ?? 0) >= ($rank[$existingState] ?? 0)) {
        $workflowTabStateMap[$tabId] = $state;
        $workflowTabActionMap[$tabId] = (string) ($workflowStep['action'] ?? 'Open this workflow section.');
    }
}

$isImageDocument = static function (array $doc): bool {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));
    $name = strtolower((string) ($doc['original_name'] ?? $doc['label'] ?? $doc['requirement_label'] ?? ''));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return true;
    }

    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
};

$documentPreviewUrl = static function (array $doc): string {
    return base_url('admin/orders/download?document_id=' . (int) ($doc['id'] ?? 0));
};

$documentDisplayName = static function (array $doc, string $fallback = 'Document'): string {
    $name = trim((string) (
        $doc['requirement_label']
        ?? $doc['label']
        ?? $doc['original_name']
        ?? ''
    ));

    return $name !== '' ? $name : $fallback;
};

$documentTypeLabel = static function (array $doc): string {
    $mime = strtolower((string) ($doc['mime_type'] ?? ''));
    $name = strtolower((string) ($doc['original_name'] ?? $doc['label'] ?? $doc['requirement_label'] ?? ''));
    $ext = strtoupper((string) pathinfo($name, PATHINFO_EXTENSION));

    if ($mime !== '' && str_starts_with($mime, 'image/')) {
        return 'IMAGE';
    }

    if ($ext !== '') {
        return $ext;
    }

    return 'FILE';
};

$renderDocumentList = static function (
    array $documents,
    string $emptyText,
    string $fallbackLabel,
    bool $allowWrongMark = false
) use ($isImageDocument, $documentPreviewUrl, $documentDisplayName, $documentTypeLabel): void {
    if ($documents === []) {
        ?>
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center text-sm font-semibold text-slate-500">
            <?= e($emptyText) ?>
        </div>
        <?php
        return;
    }

    foreach ($documents as $doc):
        $previewUrl  = $documentPreviewUrl($doc);
        $isImage     = $isImageDocument($doc);
        $displayName = $documentDisplayName($doc, $fallbackLabel);
        $typeLabel   = $documentTypeLabel($doc);
        $docStatus   = strtolower(trim((string) ($doc['document_status'] ?? 'active')));
        $wrongReason = trim((string) ($doc['wrong_reason'] ?? ''));

        $statusClass = match ($docStatus) {
            'wrong' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
            'reuploaded' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
            default => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        };

        $statusLabel = match ($docStatus) {
            'wrong' => 'Wrong Document',
            'reuploaded' => 'Re-uploaded',
            default => 'Active',
        };
        ?>
        <div class="grid gap-3 rounded-2xl border <?= $docStatus === 'wrong' ? 'border-rose-200 bg-rose-50/40' : 'border-slate-100 bg-white' ?> px-3 py-3 transition hover:border-brand-100 hover:bg-slate-50">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <?php if ($isImage): ?>
                        <a href="<?= e($previewUrl) ?>" target="_blank" class="shrink-0">
                            <img
                                src="<?= e($previewUrl) ?>"
                                alt="<?= e($displayName) ?>"
                                class="h-14 w-14 rounded-xl border border-slate-200 bg-slate-100 object-cover"
                            >
                        </a>
                    <?php else: ?>
                        <span class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-black tracking-[0.14em] text-slate-500">
                            <?= e($typeLabel) ?>
                        </span>
                    <?php endif; ?>

                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-slate-800"><?= e($displayName) ?></div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">
                            <span><?= e($typeLabel) ?></span>
                            <?php if (!empty($doc['created_at'])): ?>
                                <span>· <?= e((string) $doc['created_at']) ?></span>
                            <?php endif; ?>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black normal-case tracking-normal <?= e($statusClass) ?>">
                                <?= e($statusLabel) ?>
                            </span>
                        </div>

                        <?php if ($wrongReason !== ''): ?>
                            <div class="mt-2 rounded-xl border border-rose-100 bg-white px-3 py-2 text-xs font-semibold leading-5 text-rose-700">
                                Reason: <?= e($wrongReason) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <a
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-[12px] font-bold text-slate-700 transition hover:border-brand-200 hover:text-brand-700"
                    href="<?= e($previewUrl) ?>"
                    target="_blank"
                >
                    View
                </a>
            </div>

            <?php if ($allowWrongMark && $docStatus === 'active'): ?>
                <form
                    method="post"
                    action="<?= e(base_url('admin/orders/mark-client-document-wrong')) ?>"
                    class="grid gap-2 rounded-2xl border border-rose-100 bg-white p-3 sm:grid-cols-[1fr_auto]"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="document_id" value="<?= e((string) ($doc['id'] ?? 0)) ?>">

                    <input
                        type="text"
                        name="wrong_reason"
                        required
                        maxlength="500"
                        placeholder="Reason for re-upload request, e.g. PAN card is blurred / wrong file uploaded"
                        class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 outline-none focus:border-rose-300 focus:bg-white"
                    >

                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-xs font-black text-white hover:bg-rose-700"
                    >
                        Mark Wrong
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
    endforeach;
};

$tabs = [
    [
        'id' => 'overview',
        'step' => '1',
        'label' => 'Overview + Accept',
        'description' => 'Review pending order details and accept to start work.',
        'summary' => 'Status: ' . $orderStatusText,
    ],
    [
        'id' => 'assign',
        'step' => '2',
        'label' => 'Assign Staff',
        'description' => 'Assign or update the executive responsible for this order.',
        'summary' => 'Assigned: ' . $currentAssignedUserName,
    ],
    [
        'id' => 'approval',
        'step' => '3',
        'label' => 'Client Docs Unlock',
        'description' => 'Unlock client documents for workflow processing.',
        'summary' => $clientDocsLocked ? 'Client docs locked' : 'Client docs unlocked',
    ],
    [
        'id' => 'client-uploads',
        'step' => $hideClientDocsUnlockSection ? '3' : '3A',
        'label' => 'Client Documents',
        'description' => $canViewClientDocsDirectly
            ? 'View all documents submitted by the client directly. No unlock required for your role.'
            : 'View all documents submitted by the client after approval unlock.',
        'summary' => $clientDocumentCount . ' document(s)',
    ],
    [
        'id' => 'status',
        'step' => '4',
        'label' => 'Work Execution',
        'description' => 'Track execution status, internal notes and client-facing completion note.',
        'summary' => 'Current: ' . $orderStatusText,
    ],
    [
        'id' => 'payment-proof',
        'step' => '5',
        'label' => 'Payment Verification',
        'description' => 'Review uploaded proof and record offline/manual payments.',
        'summary' => $paymentProofCount . ' proof(s) · ' . $paymentRecordCount . ' record(s)',
    ],
    [
        'id' => 'invoice',
        'step' => '6',
        'label' => 'Invoicing',
        'description' => 'Create, update and track invoice totals, paid amount and status.',
        'summary' => 'Invoice: ' . $invoiceStatusText,
    ],
    [
        'id' => 'deliverables',
        'step' => '7',
        'label' => 'Upload Deliverables',
        'description' => 'Upload completed work files and control client visibility.',
        'summary' => $outputDocumentCount . ' output file(s)',
    ],
];

if ($hideClientDocsUnlockSection) {
    $tabs = array_values(array_filter(
        $tabs,
        static fn (array $tab): bool => (string) ($tab['id'] ?? '') !== 'approval'
    ));
}

foreach ($tabs as &$tab) {
    $tabId = (string) ($tab['id'] ?? '');
    $tab['workflow_state'] = $workflowTabStateMap[$tabId] ?? 'waiting';
    $tab['action'] = $workflowTabActionMap[$tabId] ?? 'Open this workflow section.';
}
unset($tab);

ob_start();
?>
<div class="grid gap-4 lg:grid-cols-1">
   
    <div class="grid content-start gap-4">
        <div class="rounded-[22px] border border-slate-200 bg-slate-50/80 p-5">
            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Current Status</div>
            <div class="mt-4 flex flex-wrap gap-3">
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black <?= e($orderStatusTone((string) ($order['status'] ?? ''))) ?>">
                    Order: <?= e($orderStatusDisplayLabel((string) ($order['status'] ?? ''))) ?>
                </span>
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black <?= e($orderStatusTone((string) ($order['payment_status'] ?? ''))) ?>">
                    Payment: <?= e($orderStatusLabel((string) ($order['payment_status'] ?? ''))) ?>
                </span>
                <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black <?= $currentAssignedUserId > 0 ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200' ?>">
                    Assigned: <?= e($currentAssignedUserName) ?>
                </span>
            </div>
        </div>

        <?php if ($canAcceptToWorkInProgress): ?>
            <form method="post" action="<?= e(base_url('admin/orders/update-status')) ?>" class="rounded-[22px] border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">
                <input type="hidden" name="status" value="work_in_progress">
                <input type="hidden" name="admin_notes" value="<?= e((string) ($order['admin_notes'] ?? '')) ?>">
                <input type="hidden" name="completion_note" value="<?= e((string) ($order['completion_note'] ?? '')) ?>">

                <div class="text-[11px] font-black uppercase tracking-[0.2em] text-indigo-700">Step 1 Action</div>
                <h3 class="mt-2 text-base font-black text-slate-900">Accept pending order</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    This will update the order status to <strong>Work In Progress</strong> and move it into the staff workflow.
                </p>
                <button
                    class="mt-4 inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                    type="submit"
                >
                    Accept & Start Work
                </button>
            </form>
        <?php endif; ?>

        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Admin Notes</div>
            <div class="mt-3 rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-7 text-slate-700">
                <?= nl2br(e((string) (($order['admin_notes'] ?? '') ?: '-'))) ?>
            </div>
        </div>

        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Client Facing Completion Note</div>
            <div class="mt-3 rounded-2xl bg-slate-50 px-4 py-4 text-sm leading-7 text-slate-700">
                <?= nl2br(e((string) (($order['completion_note'] ?? '') ?: '-'))) ?>
            </div>
        </div>
    </div>
     <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 1</p>
            <h2 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Order Overview</h2>
         </div>
<div class="rounded-[2rem] border border-slate-200/70 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.08)] overflow-hidden">
    <?php
    $submittedByText = trim((string) (($order['submitted_by_name'] ?? '') ?: '-'));
 
    $overviewRows = [
        'Order No' => (string) ($order['order_no'] ?? '-'),
        'Name as per PAN' => (string) (($order['customer_name_as_per_pan'] ?? $order['client_name'] ?? '') ?: '-'),
        'PAN Number' => (string) (($order['client_pan_number'] ?? $order['customer_pan_number'] ?? '') ?: '-'),
        'Mobile Number' => (string) (($order['client_phone'] ?? '') ?: '-'),
        'Email Address' => (string) (($order['client_email'] ?? '') ?: '-'),
        'Submitted By' => $submittedByText,
        'Service' => (string) ($order['service_title'] ?? '-'),
        'Turnaround' => $serviceTurnaroundDays > 0 ? ($serviceTurnaroundDays . ' day(s)') : '-',
        'Due Date' => $orderDueDateText . ($orderDueStatusText !== '' && $orderDueStatusText !== '-' ? ' · ' . $orderDueStatusText : ''),
        'Fee' => $money($order['fee_amount'] ?? 0),
        'Payment Reference' => (string) (($order['payment_reference'] ?? '') ?: '-'),
    ];

    if ($showFinancialYear) {
        $overviewRows = array_slice($overviewRows, 0, 5, true)
            + ['Financial Year' => $financialYearText]
            + array_slice($overviewRows, 5, null, true);
    }

    $overviewIcons = [
        'Order No' => 'ORD',
        'Name as per PAN' => 'PAN',
        'PAN Number' => 'ID',
        'Mobile Number' => 'MOB',
        'Email Address' => '@',
        'Financial Year' => 'FY',
        'Submitted By' => 'USR',
        'Service' => 'SVC',
        'Turnaround' => 'TAT',
        'Due Date' => 'DUE',
        'Fee' => '₹',
        'Payment Reference' => 'PAY',
    ];
    ?>

    <div class="relative overflow-hidden border-b border-slate-200/70 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 px-5 py-5 sm:px-6">
        <div class="absolute -right-16 -top-16 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-20 left-10 h-44 w-44 rounded-full bg-cyan-400/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.28em] text-cyan-200/80">
                    Order Overview
                </p>
                <h3 class="mt-1 text-lg font-black tracking-tight text-white">
                    Client & Service Details
                </h3>
                <p class="mt-1 text-sm font-medium text-slate-300">
                    Complete order information, payment reference and due status.
                </p>
            </div>

            <div class="inline-flex w-fit items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-sm font-black text-white backdrop-blur">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_0_4px_rgba(52,211,153,0.18)]"></span>
                <?= e((string) ($order['order_no'] ?? 'Order')) ?>
            </div>
        </div>
    </div>

    <div class="grid gap-3 bg-gradient-to-b from-slate-50/70 to-white p-4 sm:p-5 md:grid-cols-2">
        <?php foreach ($overviewRows as $label => $value): ?>
            <?php
            $labelText = (string) $label;
            $valueText = (string) $value;
            $iconText = $overviewIcons[$labelText] ?? '•';

            $isFee = $labelText === 'Fee';
            $isDue = $labelText === 'Due Date';
            $isPayment = $labelText === 'Payment Reference';

            $dueLower = strtolower($valueText);
            $dueBadgeClass = 'bg-slate-100 text-slate-700 ring-slate-200';
            if ($isDue && str_contains($dueLower, 'overdue')) {
                $dueBadgeClass = 'bg-rose-50 text-rose-700 ring-rose-200';
            } elseif ($isDue && (str_contains($dueLower, 'today') || str_contains($dueLower, 'due'))) {
                $dueBadgeClass = 'bg-amber-50 text-amber-700 ring-amber-200';
            } elseif ($isDue && (str_contains($dueLower, 'left') || str_contains($dueLower, 'remaining'))) {
                $dueBadgeClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
            }

            $cardClass = 'group rounded-3xl border border-slate-200/80 bg-white px-4 py-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-[0_18px_40px_rgba(15,23,42,0.08)]';

            if ($isFee) {
                $cardClass .= ' md:col-span-1 bg-gradient-to-br from-emerald-50 to-white border-emerald-200/80';
            }

            if ($isPayment) {
                $cardClass .= ' md:col-span-2';
            }
            ?>

            <div class="<?= e($cardClass) ?>">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-xs font-black tracking-tight text-white shadow-sm group-hover:scale-105 transition-transform">
                        <?= e($iconText) ?>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                                <?= e($labelText) ?>
                            </p>

                            <?php if ($isDue): ?>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black ring-1 <?= e($dueBadgeClass) ?>">
                                    Status
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($isFee): ?>
                            <p class="mt-2 text-2xl font-black tracking-tight text-emerald-700">
                                <?= e($valueText) ?>
                            </p>
                        <?php elseif ($isDue): ?>
                            <p class="mt-2 inline-flex max-w-full rounded-2xl px-3 py-2 text-sm font-black ring-1 <?= e($dueBadgeClass) ?>">
                                <?= e($valueText) ?>
                            </p>
                        <?php else: ?>
                            <p class="mt-2 break-words text-sm font-extrabold leading-6 text-slate-900">
                                <?= e($valueText) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
    </div>

</div>
<?php
$overviewPanel = ob_get_clean();

ob_start();
?>
<div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-5 py-5">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 4</p>
        <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Work Execution</h3>

    </div>

    <form method="post" action="<?= e(base_url('admin/orders/update-status')) ?>" class="grid gap-4 p-5 md:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

        <label class="grid gap-2 md:col-span-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Order Status</span>
            <select
                class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                name="status"
            >
                <?php foreach ($orderWorkflowStatusOptions as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($order['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e($status === 'submitted' ? 'Pending' : $orderStatusLabel($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Internal Notes</span>
            <textarea
                class="min-h-[150px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                name="admin_notes"
                rows="5"
                placeholder="Internal notes"
            ><?= e($order['admin_notes'] ?? '') ?></textarea>
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Client-Facing Completion Note</span>
            <textarea
                class="min-h-[150px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                name="completion_note"
                rows="5"
                placeholder="Client-facing completion note"
            ><?= e($order['completion_note'] ?? '') ?></textarea>
        </label>

        <button
            class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800 md:col-span-2"
            type="submit"
        >
            Update Status
        </button>
    </form>
</div>
<?php
$statusPanel = ob_get_clean();

ob_start();
?>
<style>
    .step-sm{
        display: none;
    }
</style>
<div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-5 py-5">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 3</p>
        <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Approval</h3>
    </div>

    <form method="post" action="<?= e(base_url('admin/orders/approve')) ?>" class="grid gap-4 p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Approval Notes</span>
            <textarea
                class="min-h-[160px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                name="admin_notes"
                rows="5"
                placeholder="Notes for approval"
            ><?= e($order['admin_notes'] ?? '') ?></textarea>
        </label>

        <button
            class="inline-flex h-12 items-center justify-center rounded-2xl bg-emerald-600 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(5,150,105,0.20)] transition hover:-translate-y-[1px] hover:bg-emerald-700"
            type="submit"
        >
            Approve Order & Unlock Client Documents
        </button>
    </form>
</div>
<?php
$approvalPanel = ob_get_clean();

ob_start();
?>
<div class="grid gap-4 xl:grid-cols-2">
    <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 5</p>
            <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Payment Verification</h3>
        </div>
        <div class="grid gap-3 p-4">
            <?php $renderDocumentList($paymentProofs, 'No payment proof uploaded.', 'Payment Proof'); ?>
        </div>
    </div>

    <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Manual Entry</p>
            <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Manual Payment Entry</h3>
        </div>

        <form method="post" action="<?= e(base_url('admin/orders/save-payment')) ?>" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Amount</span>
                <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="amount" placeholder="0.00">
            </label>

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Method</span>
                <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 placeholder:text-slate-400 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="text" name="method" placeholder="UPI / Bank / Cash">
            </label>

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Reference No.</span>
                <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="text" name="reference_no" placeholder="Transaction reference">
            </label>

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Received Date</span>
                <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="date" name="received_at" value="<?= e(date('Y-m-d')) ?>">
            </label>

            <label class="grid gap-2 md:col-span-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Notes</span>
                <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" name="notes" rows="3" placeholder="Payment note"></textarea>
            </label>

            <button class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800 md:col-span-2" type="submit">
                Record Payment
            </button>
        </form>

        <?php if (!empty($payments)): ?>
            <div class="border-t border-slate-100 p-4">
                <h4 class="mb-3 text-[12px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Records</h4>
                <div class="grid gap-2">
                    <?php foreach ($payments as $payment): ?>
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-slate-800"><?= e((string) (($payment['reference_no'] ?? '') ?: ($payment['method'] ?? 'Payment'))) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) (($payment['received_at'] ?? '') ?: ($payment['created_at'] ?? ''))) ?></div>
                            </div>
                            <strong class="text-sm font-black text-slate-900"><?= e($money($payment['amount'] ?? 0)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$paymentProofManualPanel = ob_get_clean();

ob_start();
?>
<div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-5 py-5">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 2</p>
        <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Assign Staff</h3>
        </div>

    <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Current Assignment</div>
                <div class="mt-1 text-base font-black text-slate-900"><?= e($currentAssignedUserName) ?></div>
                <?php if ($currentAssignedUserId > 0): ?>
                    <div class="mt-1 text-xs font-semibold text-slate-500">
                        <?= e($currentAssignedUserDetails['email'] ?: '-') ?>
                        <?= $currentAssignedUserDetails['phone'] !== '' ? ' • ' . e($currentAssignedUserDetails['phone']) : '' ?>
                    </div>
                <?php endif; ?>
            </div>

            <span class="inline-flex w-fit rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] <?= $currentAssignedUserId > 0 ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' ?>">
                <?= $currentAssignedUserId > 0 ? 'Assigned' : 'Unassigned' ?>
            </span>
        </div>
    </div>

    <?php if ($canAssignOrders): ?>
        <form method="post" action="<?= e(base_url('admin/orders/assign')) ?>" class="grid gap-4 p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

            <?php if (!empty($assignees)): ?>
                <label class="grid gap-2">
                    <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Assigned Staff</span>
                    <select
                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                        name="assigned_user_id"
                    >
                        <option value="0">Unassigned</option>
                        <?php foreach ($assignees as $staff): ?>
                            <option value="<?= e((string) ($staff['id'] ?? 0)) ?>" <?= $currentAssignedUserId === (int) ($staff['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e(trim((string) ($staff['name'] ?? ('User #' . ($staff['id'] ?? 0)))) . (((string) ($staff['email'] ?? '')) !== '' ? ' — ' . (string) $staff['email'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button
                    class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                    type="submit"
                >
                    Save Assignment
                </button>
            <?php else: ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold text-amber-700">
                    No role_id = 3 users are available for assignment.
                </div>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div class="p-5">
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-4 text-sm text-indigo-700">
                Assignment is read-only for your role. This order is assigned to <strong><?= e($currentAssignedUserName) ?></strong>.
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$assignPanel = ob_get_clean();

ob_start();
?>
<div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-5 py-5">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 3A</p>
        <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Client Documents</h3>
        </div>

    <div class="grid gap-3 p-4">
        <?php if ($canViewClientDocsDirectly || !$clientDocsLocked): ?>
            <?php $renderDocumentList($clientDocs, 'No client documents available.', 'Client Document', true); ?>
        <?php else: ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold text-amber-700">
                Client uploads remain locked until admin approves the order.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$clientUploadsPanel = ob_get_clean();

ob_start();
?>
<div class="grid gap-4 xl:grid-cols-2">
    <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 7</p>
            <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Upload Deliverables</h3>
</div>

        <form id="adminOutputUploadForm" method="post" action="<?= e(base_url('admin/orders/upload-output')) ?>" enctype="multipart/form-data" class="grid gap-4 p-5">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Completed File Type</span>
                <select
                    id="adminOutputFileLabel"
                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                    name="label"
                    required
                >
                    <option value="Filed Return Pack">Filed Return Pack</option>
                    <option value="Acknowledgement">Acknowledgement</option>
                    <option value="Computation Sheet">Computation Sheet</option>
                    <option value="Invoice / Receipt">Invoice / Receipt</option>
                    <option value="Other Completed File">Other Completed File</option>
                </select>
            </label>

            <label class="grid gap-2">
                <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Upload Files</span>
                <input
                    id="adminOutputFiles"
                    class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                    type="file"
                    name="documents[]"
                    multiple
                    required
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                >
            </label>

            <div id="adminOutputPreviewList" class="grid gap-3 sm:grid-cols-2"></div>

            <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_client_visible" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                Visible to client
            </label>

            <button
                class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                type="submit"
            >
                Upload Output Files
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Deliverables</p>
            <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Output Documents</h3>
        </div>
        <div class="grid gap-3 p-4">
            <?php $renderDocumentList($outputDocs, 'No output documents uploaded.', 'Output Document'); ?>
        </div>
    </div>
</div>
<?php
$deliverablesPanel = ob_get_clean();

ob_start();
?>
<div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-5 py-5">
        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-brand-600">Step 6</p>
        <h3 class="mt-1 text-xl font-black tracking-[-0.03em] text-slate-900">Invoicing</h3>

    </div>

    <form method="post" action="<?= e(base_url('admin/orders/save-invoice')) ?>" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= e((string) ($order['id'] ?? 0)) ?>">

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Issue Date</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="date" name="issue_date" value="<?= e((string) ($invoice['issue_date'] ?? date('Y-m-d'))) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Due Date</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="date" name="due_date" value="<?= e((string) ($invoice['due_date'] ?? ($orderDueDateIso !== '' ? $orderDueDateIso : date('Y-m-d')))) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Subtotal</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="subtotal" value="<?= e((string) ($invoice['subtotal'] ?? ($order['fee_amount'] ?? 0))) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Tax %</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="tax_percent" value="<?= e((string) ($invoice['tax_percent'] ?? setting('invoice_gst_percent', '18'))) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Tax Amount</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="tax_amount" value="<?= e((string) ($invoice['tax_amount'] ?? 0)) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Total Amount</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="total_amount" value="<?= e((string) ($invoice['total_amount'] ?? ($order['fee_amount'] ?? 0))) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Paid Amount</span>
            <input class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" name="paid_amount" value="<?= e((string) ($invoice['paid_amount'] ?? 0)) ?>">
        </label>

        <label class="grid gap-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Status</span>
            <select class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" name="status">
                <?php foreach ($invoiceStatusOptions as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($invoice['status'] ?? 'unpaid') === $status ? 'selected' : '' ?>>
                        <?= e($status === 'submitted' ? 'Pending' : $orderStatusLabel($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="grid gap-2 md:col-span-2">
            <span class="text-[12px] font-black uppercase tracking-[0.14em] text-slate-500">Notes</span>
            <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60" name="notes" rows="3"><?= e($invoice['notes'] ?? '') ?></textarea>
        </label>

        <button class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-black text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800 md:col-span-2" type="submit">
            Save Invoice
        </button>
    </form>
</div>
<?php
$invoicePanel = ob_get_clean();

$panelHtml = [
    'overview' => $overviewPanel,
    'assign' => $assignPanel,
    'approval' => $approvalPanel,
    'client-uploads' => $clientUploadsPanel,
    'status' => $statusPanel,
    'payment-proof' => $paymentProofManualPanel,
    'invoice' => $invoicePanel,
    'deliverables' => $deliverablesPanel,
];

if ($hideClientDocsUnlockSection) {
    unset($panelHtml['approval']);
}
?>

<style>
    /* Scoped corporate operations theme. It intentionally sits at the end of
       the view so it can refine the host Tailwind utilities without changing
       the rest of the admin application. */
    .corporate-order-shell {
        --corp-ink: #132238;
        --corp-navy: #102a43;
        --corp-blue: #1b5b87;
        --corp-teal: #0e7490;
        --corp-line: #dce5ee;
        --corp-muted: #64748b;
        color: var(--corp-ink);
        font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .corporate-order-shell .corporate-order-frame {
        overflow: hidden;
        border-color: var(--corp-line);
        border-radius: 24px;
        background: #f8fafc;
        box-shadow: 0 24px 70px rgba(15, 35, 55, .09);
    }

    .corporate-order-shell .corporate-order-hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        border-bottom-color: rgba(255, 255, 255, .12);
        background:
            radial-gradient(circle at 85% 15%, rgba(57, 189, 208, .24), transparent 24%),
            radial-gradient(circle at 12% 110%, rgba(29, 91, 135, .34), transparent 35%),
            linear-gradient(122deg, #0d2138 0%, #143957 58%, #164e6b 100%);
        padding: 30px 32px;
    }

    .corporate-order-shell .corporate-order-hero::after {
        position: absolute;
        z-index: -1;
        right: 9%;
        bottom: -90px;
        width: 260px;
        height: 260px;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 999px;
        box-shadow: 0 0 0 28px rgba(255, 255, 255, .025), 0 0 0 58px rgba(255, 255, 255, .018);
        content: "";
    }

    .corporate-order-shell .corporate-order-eyebrow {
        color: #8be0e6;
        letter-spacing: .24em;
    }

    .corporate-order-shell .corporate-order-title {
        color: #fff;
        font-size: clamp(1.55rem, 3vw, 2.15rem);
        letter-spacing: -.045em;
    }

    .corporate-order-shell .corporate-order-description {
        max-width: 760px;
        color: rgba(226, 232, 240, .82);
    }

    .corporate-order-shell .corporate-financial-year {
        border-color: rgba(139, 224, 230, .34);
        background: rgba(7, 26, 44, .28);
        color: #e0fbff;
        box-shadow: none;
        backdrop-filter: blur(10px);
    }

    .corporate-order-shell .corporate-financial-year span {
        color: #8be0e6;
    }

    .corporate-order-shell .corporate-financial-year strong {
        color: #fff;
    }

    .corporate-order-shell .corporate-order-back-link {
        border-color: rgba(255, 255, 255, .2);
        background: rgba(255, 255, 255, .09);
        color: #fff;
        box-shadow: 0 10px 25px rgba(3, 19, 34, .16);
        backdrop-filter: blur(12px);
    }

    .corporate-order-shell .corporate-order-back-link:hover {
        border-color: rgba(139, 224, 230, .7);
        background: rgba(255, 255, 255, .16);
        color: #fff;
    }

    .corporate-order-shell .corporate-summary-band {
        border-bottom-color: var(--corp-line);
        background: #f2f6fa;
        padding: 22px 32px;
    }

    .corporate-order-shell .corporate-summary-card {
        position: relative;
        overflow: hidden;
        min-height: 112px;
        border-color: var(--corp-line);
        border-radius: 16px;
        background: rgba(255, 255, 255, .94);
        padding: 16px 18px;
        box-shadow: 0 8px 22px rgba(18, 42, 67, .055);
    }

    .corporate-order-shell .corporate-summary-card::before {
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #2fa7b8, #1b5b87);
        content: "";
    }

    .corporate-order-shell .corporate-summary-card > div:first-child {
        color: #8091a3;
        letter-spacing: .14em;
    }

    .corporate-order-shell .corporate-summary-card > div:nth-child(2) {
        margin-top: 12px;
    }

    .corporate-order-shell .corporate-summary-card .inline-flex {
        border-radius: 999px;
        letter-spacing: .06em;
    }

    .corporate-order-shell .corporate-workflow-overview {
        border-bottom-color: var(--corp-line);
        background: #fff;
        padding: 28px 32px;
    }

    .corporate-order-shell .corporate-progress-card {
        border-color: var(--corp-line);
        border-radius: 18px;
        background: #f7fafc;
        padding: 18px;
    }

    .corporate-order-shell .corporate-progress-card > div.rounded-2xl {
        border-color: var(--corp-line);
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(18, 42, 67, .05);
    }

    .corporate-order-shell .corporate-progress-bar {
        background: #dbe7ef;
    }

    .corporate-order-shell .corporate-progress-bar > div {
        background: linear-gradient(90deg, #1b5b87, #2fa7b8);
    }

    .corporate-order-shell .corporate-next-action {
        border-color: #b9d9e1;
        border-radius: 18px;
        background: linear-gradient(145deg, #e9f8fa 0%, #f5fbfc 100%);
    }

    .corporate-order-shell .corporate-next-action > p:first-child {
        color: var(--corp-teal);
    }

    .corporate-order-shell .corporate-next-action .orderStepJump,
    .corporate-order-shell .corporate-order-hero .corporate-order-back-link {
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .corporate-order-shell .corporate-next-action .orderStepJump {
        background: var(--corp-navy);
        box-shadow: 0 9px 18px rgba(16, 42, 67, .16);
    }

    .corporate-order-shell .corporate-next-action .orderStepJump:hover {
        background: #1a4669;
        box-shadow: 0 12px 22px rgba(16, 42, 67, .22);
        transform: translateY(-1px);
    }

    .corporate-order-shell .corporate-workflow-quick {
        border-color: var(--corp-line);
        border-radius: 16px;
        box-shadow: 0 7px 18px rgba(18, 42, 67, .045);
    }

    .corporate-order-shell .corporate-workflow-quick:hover {
        border-color: #9bcbd5;
        background: #f2fbfc;
        box-shadow: 0 12px 24px rgba(18, 42, 67, .09);
    }

    .corporate-order-shell .corporate-workflow-body {
        background: #f4f7fa;
        padding: 24px 32px 32px;
    }

    .corporate-order-shell .corporate-workflow-nav {
        position: sticky;
        top: 16px;
        border-color: var(--corp-line);
        border-radius: 18px;
        background: #edf3f7;
        padding: 12px;
    }

    .corporate-order-shell .corporate-workflow-nav > div:first-child {
        padding: 10px 10px 12px;
    }

    .corporate-order-shell .corporate-tab-button {
        min-height: 84px;
        border-color: var(--corp-line);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 12px rgba(18, 42, 67, .035);
    }

    .corporate-order-shell .corporate-tab-button[aria-selected="true"] {
        border-color: var(--corp-navy) !important;
        background: var(--corp-navy) !important;
        box-shadow: 0 10px 20px rgba(16, 42, 67, .18);
        color: #fff !important;
    }

    .corporate-order-shell .corporate-tab-button[aria-selected="true"] > span:first-child {
        background: rgba(255, 255, 255, .14) !important;
        color: #fff !important;
    }

    .corporate-order-shell .corporate-tab-button[aria-selected="true"] [data-workflow-summary] {
        background: rgba(255, 255, 255, .14) !important;
        color: #fff !important;
    }

    .corporate-order-shell .orderStepPanel > div {
        border-color: var(--corp-line);
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(18, 42, 67, .065);
    }

    .corporate-order-shell .orderStepPanel > div > div:first-child {
        border-bottom-color: var(--corp-line);
        background: #fff;
    }

    .corporate-order-shell .orderStepPanel h3,
    .corporate-order-shell .orderStepPanel h2 {
        color: var(--corp-ink);
        letter-spacing: -.03em;
    }

    .corporate-order-shell .orderStepPanel input:not([type="checkbox"]),
    .corporate-order-shell .orderStepPanel select,
    .corporate-order-shell .orderStepPanel textarea {
        border-color: #d5e0e9;
        border-radius: 11px;
        background: #f8fafc;
        color: var(--corp-ink);
    }

    .corporate-order-shell .orderStepPanel input:not([type="checkbox"]):focus,
    .corporate-order-shell .orderStepPanel select:focus,
    .corporate-order-shell .orderStepPanel textarea:focus {
        border-color: #6ab5c1;
        box-shadow: 0 0 0 4px rgba(47, 167, 184, .13);
        background: #fff;
    }

    .corporate-order-shell .orderStepPanel button[type="submit"] {
        border-radius: 11px;
        background: var(--corp-navy);
        box-shadow: 0 10px 20px rgba(16, 42, 67, .14);
    }

    .corporate-order-shell .orderStepPanel button[type="submit"]:hover {
        background: #1a4669;
    }

    .corporate-order-shell .orderStepPanel a[target="_blank"] {
        border-radius: 10px;
        border-color: #d5e0e9;
    }

    .corporate-order-shell .orderStepPanel a[target="_blank"]:hover {
        border-color: #8ac9d2;
        color: var(--corp-blue);
    }

    @media (max-width: 1023px) {
        .corporate-order-shell .corporate-workflow-nav {
            position: static;
        }
    }

    @media (max-width: 639px) {
        .corporate-order-shell .corporate-order-hero,
        .corporate-order-shell .corporate-summary-band,
        .corporate-order-shell .corporate-workflow-overview,
        .corporate-order-shell .corporate-workflow-body {
            padding-right: 16px;
            padding-left: 16px;
        }

        .corporate-order-shell .corporate-order-hero {
            padding-top: 24px;
            padding-bottom: 24px;
        }
    }
</style>

<section class="corporate-order-shell grid gap-5">
    <div class="corporate-order-frame overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
        <div class="corporate-order-hero flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative z-10">
                <p class="corporate-order-eyebrow text-[11px] font-black uppercase tracking-[0.22em]">Operations / Order Management</p>

                <?php if ($showFinancialYear): ?>
                    <div class="corporate-financial-year mt-3 inline-flex flex-wrap items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-900 shadow-sm">
                        <span class="font-black uppercase tracking-[0.08em] text-indigo-600">Financial Year:</span>
                        <strong class="font-black text-indigo-950"><?= e($financialYearText) ?></strong>
                    </div>
                <?php endif; ?>

                <h1 class="corporate-order-title mt-3 text-2xl font-black tracking-[-0.04em] text-slate-900">
                    <?= e((string) ($order['order_no'] ?? 'Order Details')) ?>
                </h1>
                <p class="corporate-order-description mt-2 text-sm leading-6 text-slate-500">
                    Step-by-step workflow: overview acceptance, staff assignment, client document unlock, work execution, payment verification, invoicing and final deliverables. Admins can preview client documents before unlock.
                </p>
            </div>

            <a
                href="<?= e(base_url('admin/orders')) ?>"
                class="corporate-order-back-link relative z-10 inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:border-brand-200 hover:text-brand-700"
            >
                ← Back to Orders
            </a>
        </div>

        <div class="corporate-summary-band border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-indigo-50/50 px-5 py-5">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div class="corporate-summary-card rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Order Status</div>
                    <div class="mt-3 inline-flex rounded-full px-3 py-1.5 text-xs font-black <?= e($orderStatusTone((string) ($order['status'] ?? ''))) ?>">
                        <?= e($orderStatusText) ?>
                    </div>
                </div>

                <div class="corporate-summary-card rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Payment Status</div>
                    <div class="mt-3 inline-flex rounded-full px-3 py-1.5 text-xs font-black <?= e($orderStatusTone((string) ($order['payment_status'] ?? ''))) ?>">
                        <?= e($paymentStatusText) ?>
                    </div>
                </div>

                <div class="corporate-summary-card rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Due Date / TAT</div>
                    <div class="mt-3 truncate text-sm font-black text-slate-900"><?= e($orderDueDateText) ?></div>
                    <div class="mt-2 inline-flex rounded-full px-3 py-1 text-[11px] font-black <?= e($orderDueTone) ?>">
                        <?= e($orderDueStatusText) ?><?= $serviceTurnaroundDays > 0 ? ' · ' . e((string) $serviceTurnaroundDays) . 'd TAT' : '' ?>
                    </div>
                </div>

                <div class="corporate-summary-card rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Assigned Executive</div>
                    <div class="mt-3 truncate text-sm font-black text-slate-900"><?= e($currentAssignedUserName) ?></div>
                    <?php if ($currentAssignedUserDetails['email'] !== ''): ?>
                        <div class="mt-1 truncate text-xs font-semibold text-slate-500"><?= e($currentAssignedUserDetails['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="corporate-summary-card rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Balance Due</div>
                    <div class="mt-3 text-sm font-black text-slate-900"><?= e($money($balanceDueForSummary)) ?></div>
                    <div class="mt-1 text-xs font-semibold text-slate-500">Paid: <?= e($money($totalPaidForSummary)) ?></div>
                </div>
            </div>
        </div>

        <div class="corporate-workflow-overview border-b border-slate-100 bg-white px-5 py-5">
            <div class="mb-5 grid gap-4 xl:grid-cols-[1fr_360px] xl:items-start">
                <div class="corporate-progress-card rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Advanced Workflow</p>
                            <h2 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Step-by-step order control</h2>

                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right shadow-sm">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Progress</div>
                            <div class="mt-1 text-2xl font-black text-slate-900"><?= e((string) $workflowProgressPercent) ?>%</div>
                            <div class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) $workflowCompletedCount) ?> / <?= e((string) $workflowTotalSteps) ?> steps complete</div>
                        </div>
                    </div>
                    <div class="corporate-progress-bar mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-slate-900" style="width: <?= e((string) $workflowProgressPercent) ?>%"></div>
                    </div>
                </div>

                <div class="corporate-next-action rounded-[24px] border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-700">Next Best Action</p>
                    <h3 class="mt-2 text-base font-black text-slate-900"><?= e((string) ($workflowCurrentStep['title'] ?? 'Review Order')) ?></h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600"><?= e((string) ($workflowCurrentStep['action'] ?? 'Open the active workflow section and continue processing.')) ?></p>
                    <button
                        type="button"
                        class="orderStepJump mt-4 inline-flex h-10 items-center justify-center rounded-2xl bg-slate-900 px-4 text-xs font-black uppercase tracking-[0.12em] text-white shadow-sm transition hover:bg-slate-800"
                        data-order-jump-tab="<?= e($defaultWorkflowTabId) ?>"
                    >
                        Open Step
                    </button>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($advancedWorkflowSteps as $workflowStep): ?>
                    <?php
                        $state = (string) ($workflowStep['state'] ?? 'waiting');
                        $tabId = (string) ($workflowStep['tab'] ?? 'overview');
                    ?>
                    <button
                        type="button"
                        class="corporate-workflow-quick orderStepJump group rounded-[22px] border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-[1px] hover:border-indigo-200 hover:bg-indigo-50/40 hover:shadow-md"
                        data-order-jump-tab="<?= e($tabId) ?>"
                    >
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-black <?= e($workflowStateTone($state)) ?>">
                                <?= e($workflowStepIcon($state)) ?>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Step <?= e((string) ($workflowStep['step'] ?? '')) ?></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-[0.12em] <?= e($workflowStateTone($state)) ?>"><?= e($state) ?></span>
                                </span>
                                <span class="mt-1 block truncate text-sm font-black text-slate-900"><?= e((string) ($workflowStep['title'] ?? 'Workflow Step')) ?></span>
                                   </span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="corporate-workflow-body grid gap-5 p-4 sm:p-5 xl:grid-cols-[380px_1fr]">
            <aside class="corporate-workflow-nav rounded-[24px] border border-slate-200 bg-slate-50/80 p-3 xl:sticky xl:top-4 xl:self-start">
                <div class="px-2 py-2">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Workflow List View</p>
                    <h2 class="mt-1 text-base font-black tracking-[-0.03em] text-slate-900">Order Control Tabs</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Select any row below to open the matching workflow section.</p>
                </div>

                <div class="mt-2 grid gap-2" role="tablist" aria-label="Order management steps">
                    <?php foreach ($tabs as $index => $tab): ?>
                        <button
                            type="button"
                            class="corporate-tab-button orderStepTab group flex w-full items-start gap-3 rounded-[20px] border p-4 text-left transition <?= ($tab['id'] ?? '') === $defaultWorkflowTabId ? 'border-slate-900 bg-slate-900 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-brand-200 hover:text-brand-700 hover:shadow-sm' ?>"
                            data-order-tab="<?= e($tab['id']) ?>"
                            role="tab"
                            aria-selected="<?= ($tab['id'] ?? '') === $defaultWorkflowTabId ? 'true' : 'false' ?>"
                        >
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl text-xs font-black <?= ($tab['id'] ?? '') === $defaultWorkflowTabId ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600' ?>">
                                <?= e($tab['step']) ?>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-black"><?= e($tab['label']) ?></span>
                                <span class="mt-1 block text-xs leading-5 opacity-80"><?= e($tab['description'] ?? '') ?></span>
                                <span class="mt-2 inline-flex max-w-full rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.12em] <?= ($tab['id'] ?? '') === $defaultWorkflowTabId ? 'bg-white/15 text-white' : e($workflowStateTone((string) ($tab['workflow_state'] ?? 'waiting'))) ?>" data-workflow-summary>
                                    <span class="truncate"><?= e($tab['summary'] ?? '') ?></span>
                                </span>
                                <span class="mt-2 block text-[11px] leading-5 opacity-80"><?= e((string) ($tab['action'] ?? '')) ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="min-w-0">
                <?php foreach ($tabs as $index => $tab): ?>
                    <div
                        id="orderTabPanel-<?= e($tab['id']) ?>"
                        class="orderStepPanel <?= ($tab['id'] ?? '') === $defaultWorkflowTabId ? '' : 'hidden' ?>"
                        data-order-panel="<?= e($tab['id']) ?>"
                        role="tabpanel"
                    >
                        <?= $panelHtml[$tab['id']] ?? '' ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('.orderStepTab'));
    const panels = Array.from(document.querySelectorAll('.orderStepPanel'));
    const defaultWorkflowTab = <?= json_encode($defaultWorkflowTabId, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function setTab(tabId, updateHash) {
        const selectedTab = tabs.find(function (tab) {
            return tab.getAttribute('data-order-tab') === tabId;
        }) || tabs[0];

        if (!selectedTab) return;

        const selectedId = selectedTab.getAttribute('data-order-tab');

        tabs.forEach(function (tab) {
            const isActive = tab.getAttribute('data-order-tab') === selectedId;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.classList.toggle('border-slate-900', isActive);
            tab.classList.toggle('bg-slate-900', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('shadow-sm', isActive);
            tab.classList.toggle('border-slate-200', !isActive);
            tab.classList.toggle('bg-white', !isActive);
            tab.classList.toggle('text-slate-700', !isActive);

            const bubble = tab.querySelector('span');
            if (bubble) {
                bubble.classList.toggle('bg-white/15', isActive);
                bubble.classList.toggle('text-white', isActive);
                bubble.classList.toggle('bg-slate-100', !isActive);
                bubble.classList.toggle('text-slate-600', !isActive);
            }

            const summaryBadge = tab.querySelector('[data-workflow-summary]');
            if (summaryBadge) {
                summaryBadge.classList.toggle('bg-white/15', isActive);
                summaryBadge.classList.toggle('text-white', isActive);
                summaryBadge.classList.toggle('bg-slate-100', !isActive);
                summaryBadge.classList.toggle('text-slate-500', !isActive);
            }
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-order-panel') !== selectedId);
        });

        try {
            window.localStorage.setItem('adminOrderShowActiveTab', selectedId);
        } catch (e) {}

        if (updateHash) {
            history.replaceState(null, '', '#' + selectedId);
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setTab(tab.getAttribute('data-order-tab'), true);
        });
    });

    document.querySelectorAll('.orderStepJump').forEach(function (button) {
        button.addEventListener('click', function () {
            const tabId = button.getAttribute('data-order-jump-tab') || defaultWorkflowTab || 'overview';
            setTab(tabId, true);
            const targetPanel = document.querySelector('[data-order-panel="' + tabId + '"]');
            if (targetPanel) {
                targetPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        });
    });

    const initialHash = window.location.hash ? window.location.hash.replace('#', '') : '';
    let storedTab = '';
    try {
        storedTab = window.localStorage.getItem('adminOrderShowActiveTab') || '';
    } catch (e) {}

    if (initialHash && tabs.some(function (tab) { return tab.getAttribute('data-order-tab') === initialHash; })) {
        setTab(initialHash, false);
    } else if (storedTab && tabs.some(function (tab) { return tab.getAttribute('data-order-tab') === storedTab; })) {
        setTab(storedTab, false);
    } else if (defaultWorkflowTab && tabs.some(function (tab) { return tab.getAttribute('data-order-tab') === defaultWorkflowTab; })) {
        setTab(defaultWorkflowTab, false);
    }

    const outputFiles = document.getElementById('adminOutputFiles');
    const outputList = document.getElementById('adminOutputPreviewList');
    const outputLabel = document.getElementById('adminOutputFileLabel');

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char];
        });
    }

    if (outputFiles && outputList) {
        outputFiles.addEventListener('change', function () {
            const label = outputLabel ? outputLabel.value : 'Completed File';
            outputList.innerHTML = '';

            Array.from(outputFiles.files || []).forEach(function (file) {
                const isImage = file.type && file.type.startsWith('image/');
                const card = document.createElement('div');
                card.className = 'rounded-2xl border border-slate-200 bg-white p-3 shadow-sm';

                let preview = '<div class="flex h-24 items-center justify-center rounded-xl bg-slate-100 text-xs font-black uppercase tracking-[0.16em] text-slate-500">FILE</div>';
                if (isImage) {
                    preview = '<img src="' + URL.createObjectURL(file) + '" class="h-24 w-full rounded-xl object-cover" alt="Output file preview">';
                }

                card.innerHTML = preview +
                    '<div class="mt-3 truncate text-sm font-black text-slate-900">' + escapeHtml(file.name) + '</div>' +
                    '<div class="mt-1 text-xs font-semibold text-slate-500">' + escapeHtml(label) + '</div>';
                outputList.appendChild(card);
            });
        });
    }
});
</script> 