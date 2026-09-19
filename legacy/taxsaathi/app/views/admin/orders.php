<?php
declare(strict_types=1);

$orderStatusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'success', 'verified' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending', 'pending_review', 'pending_clarification', 'clarification', 'processing', 'work_in_progress', 'partial', 'waiting_for_payment' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$orderStatusLabel = static function (string $status): string {
    $key = strtolower(trim($status));

    return match ($key) {
        'submitted', 'submited' => 'Pending',
        'pending_clarification', 'clarification' => 'Pending Clarification',
        default => ucwords(str_replace('_', ' ', $status)),
    };
};

$normalizeOrderStatusKey = static function (mixed $status): string {
    $key = strtolower(trim((string) $status));
    $key = str_replace(['-', ' '], '_', $key);

    return match ($key) {
        '', 'pending' => 'pending_review',
        'submited' => 'submitted',
        'clarification', 'pending_clarification', 'pendingclarification' => 'pending_clarification',
        default => $key,
    };
};

/*
 * Strict user_roles RBAC flags.
 * These values must be passed by OrdersController after validating:
 * session user_id -> user_roles.user_id -> user_roles.role_id.
 * Do not infer admin access from missing role variables and do not read users.role_id here.
 */
$isRoleThreeUser = (bool) ($isRoleThreeUser ?? false);
$isAssignedStaffUser = (bool) ($isAssignedStaffUser ?? $isRoleThreeUser);
$isExecutiveUser = (bool) ($isExecutiveUser ?? $isAssignedStaffUser);
$isPartnerUser = (bool) ($isPartnerUser ?? false);
$isAdminLikeUser = (bool) ($isAdminLikeUser ?? false);
$canAssignOrders = (bool) ($canAssignOrders ?? false);
$currentUserId = (int) ($currentUserId ?? 0);

$rows = is_array($rows ?? null) ? array_values($rows) : [];
$services = is_array($services ?? null) ? array_values($services) : [];
$orderServices = is_array($orderServices ?? null) ? array_values($orderServices) : [];
$clients = is_array($clients ?? null) ? array_values($clients) : [];
$filters = is_array($filters ?? null) ? $filters : [];
$assignees = is_array($assignees ?? null) ? array_values($assignees) : [];
$financialYears = is_array($financialYears ?? null) && $financialYears !== []
    ? array_values($financialYears)
    : ['2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026'];
$allowedFinancialYearSlugs = is_array($allowedFinancialYearSlugs ?? null) && $allowedFinancialYearSlugs !== []
    ? array_values($allowedFinancialYearSlugs)
    : ['itr-filing', 'itr-full-package'];
$orderRequirementsByService = is_array($orderRequirementsByService ?? null) ? $orderRequirementsByService : [];

$assigneeMap = [];
foreach ($assignees as $staff) {
    $staffId = (int) ($staff['id'] ?? 0);
    if ($staffId > 0) {
        $assigneeMap[$staffId] = trim((string) ($staff['name'] ?? ('User #' . $staffId)));
    }
}

$resolveAssigneeName = static function ($assignedUserId) use ($assigneeMap): string {
    $assignedUserId = (int) $assignedUserId;
    if ($assignedUserId <= 0) {
        return 'Unassigned';
    }

    return $assigneeMap[$assignedUserId] ?? ('User #' . $assignedUserId);
};

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$canCreateOrders = (bool) ($canCreateOrders ?? ($isAdminLikeUser || $isExecutiveUser));

$kanbanOrderStatusColumns = [
    'submitted' => [
        'title' => 'Pending Work',
        'description' => 'Client-submitted orders waiting to enter review or approval.',
        'badge' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
    ],
    'pending_clarification' => [
        'title' => 'Pending Clarification',
        'description' => 'Orders waiting for client clarification, missing details or internal query resolution.',
        'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    ],
     'work_in_progress' => [
        'title' => 'Work In Progress',
        'description' => 'Assigned work currently being processed by the team.',
        'badge' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
    ],
    'approved' => [
        'title' => 'Approved',
        'description' => 'Orders approved and ready to move into active execution.',
        'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    ],
   
    'completed' => [
        'title' => 'Completed',
        'description' => 'Finished orders, uploaded deliverables and closed work.',
        'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    ],
    'rejected' => [
        'title' => 'Rejected',
        'description' => 'Rejected, cancelled or blocked orders needing review.',
        'badge' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
    ],
  
];

$kanbanPaymentStatusColumns = [
    'pending' => ['title' => 'Pending', 'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'],
    'pending_review' => ['title' => 'Pending Review', 'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'],
    'verified' => ['title' => 'Verified', 'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'],
    'paid' => ['title' => 'Paid', 'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'],
    'partial' => ['title' => 'Partial', 'badge' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200'],
    'unpaid' => ['title' => 'Unpaid', 'badge' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200'],
    'failed' => ['title' => 'Failed', 'badge' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200'],
];

$kanbanRowsByStatus = [];
$kanbanStatusTotals = [];
foreach ($kanbanOrderStatusColumns as $statusKey => $_meta) {
    $kanbanRowsByStatus[$statusKey] = [];
    $kanbanStatusTotals[$statusKey] = 0.0;
}

$kanbanPaymentSummary = [];
foreach ($kanbanPaymentStatusColumns as $paymentKey => $_meta) {
    $kanbanPaymentSummary[$paymentKey] = [
        'count' => 0,
        'amount' => 0.0,
    ];
}

$kanbanTotalValue = 0.0;
foreach ($rows as $row) {
    $statusKey = $normalizeOrderStatusKey($row['status'] ?? 'pending_review');
    $paymentKey = strtolower(trim((string) ($row['payment_status'] ?? 'pending')));
    $feeAmount = (float) ($row['fee_amount'] ?? 0);

    if ($statusKey === '') {
        $statusKey = 'pending_review';
    }

    if ($paymentKey === '') {
        $paymentKey = 'pending';
    }

    if (!isset($kanbanOrderStatusColumns[$statusKey])) {
        $kanbanOrderStatusColumns[$statusKey] = [
            'title' => $orderStatusLabel($statusKey),
            'description' => 'Additional custom order workflow status.',
            'badge' => $orderStatusTone($statusKey),
        ];
        $kanbanRowsByStatus[$statusKey] = [];
        $kanbanStatusTotals[$statusKey] = 0.0;
    }

    if (!isset($kanbanPaymentStatusColumns[$paymentKey])) {
        $kanbanPaymentStatusColumns[$paymentKey] = [
            'title' => $orderStatusLabel($paymentKey),
            'badge' => $orderStatusTone($paymentKey),
        ];
        $kanbanPaymentSummary[$paymentKey] = [
            'count' => 0,
            'amount' => 0.0,
        ];
    }

    $row['status'] = $statusKey;
    $kanbanRowsByStatus[$statusKey][] = $row;
    $kanbanStatusTotals[$statusKey] += $feeAmount;
    $kanbanPaymentSummary[$paymentKey]['count']++;
    $kanbanPaymentSummary[$paymentKey]['amount'] += $feeAmount;
    $kanbanTotalValue += $feeAmount;
}

$kanbanPaymentBorder = static function (string $paymentStatus): string {
    return match (strtolower(trim($paymentStatus))) {
        'paid', 'verified', 'success' => 'border-l-emerald-500',
        'partial' => 'border-l-blue-500',
        'pending', 'pending_review', 'processing', 'waiting_for_payment' => 'border-l-amber-500',
        'unpaid', 'failed', 'overdue', 'rejected', 'cancelled' => 'border-l-rose-500',
        default => 'border-l-slate-300',
    };
};

$kanbanPaymentSignal = static function (string $paymentStatus): string {
    return match (strtolower(trim($paymentStatus))) {
        'paid', 'verified', 'success' => 'bg-emerald-500',
        'partial' => 'bg-blue-500',
        'pending', 'pending_review', 'processing', 'waiting_for_payment' => 'bg-amber-500',
        'unpaid', 'failed', 'overdue', 'rejected', 'cancelled' => 'bg-rose-500',
        default => 'bg-slate-400',
    };
};

$kanbanDateFormat = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d M Y', $timestamp);
};

$kanbanDueDateBadge = static function (mixed $dueDaysLeft, mixed $dueDate, string $orderStatus): string {
    $status = strtolower(trim($orderStatus));
    $rawDueDate = trim((string) $dueDate);

    if (in_array($status, ['completed', 'approved'], true)) {
        return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
    }

    if ($rawDueDate === '' || $rawDueDate === '0000-00-00' || $rawDueDate === '0000-00-00 00:00:00') {
        return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    }

    $days = is_numeric($dueDaysLeft) ? (int) $dueDaysLeft : null;
    if ($days === null) {
        return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    }

    if ($days < 0) {
        return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
    }

    if ($days === 0) {
        return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    }

    if ($days <= 2) {
        return 'bg-orange-50 text-orange-700 ring-1 ring-orange-200';
    }

    return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200';
};

$kanbanDueDateLabel = static function (mixed $dueDaysLeft, mixed $dueDate, string $orderStatus, mixed $turnaroundDays): string {
    $status = strtolower(trim($orderStatus));
    $rawDueDate = trim((string) $dueDate);
    $days = is_numeric($dueDaysLeft) ? (int) $dueDaysLeft : null;
    $tat = is_numeric($turnaroundDays) ? (int) $turnaroundDays : 0;

    if (in_array($status, ['completed', 'approved'], true)) {
        return 'Closed';
    }

    if ($rawDueDate === '' || $rawDueDate === '0000-00-00' || $rawDueDate === '0000-00-00 00:00:00' || $tat <= 0) {
        return 'No TAT';
    }

    if ($days === null) {
        return $tat . 'd TAT';
    }

    if ($days < 0) {
        return abs($days) . 'd overdue';
    }

    if ($days === 0) {
        return 'Due today';
    }

    return $days . 'd left';
};

$renderExcelKanbanTable = static function (array $items, string $emptyMessage = 'No orders found.') use (
    $orderStatusTone,
    $orderStatusLabel,
    $kanbanPaymentBorder,
    $kanbanPaymentSignal,
    $resolveAssigneeName,
    $money,
    $kanbanDateFormat,
    $kanbanDueDateBadge,
    $kanbanDueDateLabel,
    $normalizeOrderStatusKey
): void {
    $items = array_values($items);

    $dueSortScore = static function (array $row) use ($normalizeOrderStatusKey): array {
        $status = $normalizeOrderStatusKey($row['status'] ?? '');
        $turnaroundDays = (int) ($row['service_turnaround_days'] ?? 0);
        $dueDateRaw = trim((string) (($row['order_due_date'] ?? $row['due_date'] ?? '') ?: ''));
        $dueDaysLeft = $row['order_due_days_left'] ?? null;
        $days = is_numeric($dueDaysLeft) ? (int) $dueDaysLeft : null;

        if (in_array($status, ['completed', 'approved'], true)) {
            return [4, 0, PHP_INT_MAX, -((int) ($row['id'] ?? 0))];
        }

        if ($dueDateRaw === '' || $dueDateRaw === '0000-00-00' || $dueDateRaw === '0000-00-00 00:00:00' || $turnaroundDays <= 0) {
            return [3, 0, PHP_INT_MAX, -((int) ($row['id'] ?? 0))];
        }

        if ($days === null) {
            try {
                $today = new DateTimeImmutable('today');
                $dueDate = new DateTimeImmutable(substr($dueDateRaw, 0, 10));
                $days = (int) $today->diff($dueDate)->format('%r%a');
            } catch (Throwable $e) {
                return [3, 0, PHP_INT_MAX, -((int) ($row['id'] ?? 0))];
            }
        }

        $dueTimestamp = strtotime(substr($dueDateRaw, 0, 10)) ?: PHP_INT_MAX;

        if ($days === 0) {
            return [0, 0, $dueTimestamp, -((int) ($row['id'] ?? 0))];
        }

        if ($days < 0) {
            return [1, abs($days), $dueTimestamp, -((int) ($row['id'] ?? 0))];
        }

        return [2, $days, $dueTimestamp, -((int) ($row['id'] ?? 0))];
    };

    usort($items, static function (array $left, array $right) use ($dueSortScore): int {
        return $dueSortScore($left) <=> $dueSortScore($right);
    });
?>
    <div class="overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm" data-excel-kanban-pager data-default-per-page="20">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/80 px-2 py-2 lg:flex-row lg:items-center lg:justify-between" data-kanban-pagination-toolbar>
            <div class="flex flex-wrap items-center gap-2">
                <label class="inline-flex items-center gap-2 text-[12px] font-normal text-slate-600">
                    <span>Filter / Sort</span>
                    <select data-kanban-sort aria-label="Filter and sort records" class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-[12px] font-normal text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <optgroup label="Filters">
                            <option value="all" selected>All</option>
                            <option value="due_priority">Due Today</option>
                            <option value="overdue">Overdue</option>
                            <option value="closed">Closed</option>
                            <option value="pending">Pending</option>
                            <option value="pending_clarification">Pending Clarification</option>
                            <option value="approved">Approved</option>
                            <option value="waiting_for_payment">Waiting for payment</option>
                            <option value="documents_missing">Documents missing</option>
                            <option value="work_in_progress">Work In Progress</option>
                            <option value="completed">Completed</option>
                        </optgroup>
                        <optgroup label="Sort">
                            <option value="newest">Newest first</option>
                            <option value="client_asc">Client A-Z</option>
                            <option value="service_asc">Service A-Z</option>
                            <option value="order_no_asc">Order No A-Z</option>
                        </optgroup>
                    </select>
                </label>

                <label class="inline-flex items-center gap-2 text-[12px] font-normal text-slate-600">
                    <span>Rows</span>
                    <select data-kanban-page-size class="h-8 rounded-lg border border-slate-200 bg-white px-2 text-[12px] font-normal text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                        <option value="custom">Custom</option>
                    </select>
                </label>

                <input data-kanban-custom-page-size type="number" min="1" max="500" step="1" value="20" class="hidden h-8 w-24 rounded-lg border border-slate-200 bg-white px-2 text-[12px] font-normal text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" aria-label="Custom rows per page">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span data-kanban-page-info class="rounded-full bg-white px-2.5 py-1 text-[12px] font-normal text-slate-600 ring-1 ring-slate-200">Showing 0 records</span>
                <button type="button" data-kanban-prev class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-[12px] font-normal text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Prev</button>
                <span data-kanban-page-number class="min-w-16 text-center text-[12px] font-normal text-slate-500">1 / 1</span>
                <button type="button" data-kanban-next class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-[12px] font-normal text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-[12px]" style="min-width: 1420px;">
                <thead class="sticky top-0 z-20 bg-slate-900 text-[11px] font-normal uppercase tracking-[0.10em] text-white">
                    <tr>
                        <th class="sticky left-0 z-30 border-r border-white/10 bg-slate-900 px-2 py-2">#</th>
                        <th class="border-r border-white/10 px-2 py-2">Order ID / No.</th>
                        <th class="border-r border-white/10 px-2 py-2">Name as per PAN</th>
                       
                        <th class="border-r border-white/10 px-2 py-2">Submitted By</th>
                        <th class="border-r border-white/10 px-2 py-2">Service</th>
                        <th class="border-r border-white/10 px-2 py-2">Due Date</th>
                        <th class="border-r border-white/10 px-2 py-2">Status</th>
                        <th class="border-r border-white/10 px-2 py-2">Payment</th>
                        <th class="border-r border-white/10 px-2 py-2 text-right">Payment Value</th>
                        <th class="border-r border-white/10 px-2 py-2">Assigned</th>
                        <th class="px-2 py-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="13" class="px-4 py-6 text-center text-sm font-normal text-slate-400">
                                <?= e($emptyMessage) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $index => $row): ?>
                        <?php
                            $paymentStatus = (string) ($row['payment_status'] ?? 'pending');
                            $orderStatus = $normalizeOrderStatusKey($row['status'] ?? 'pending_review');
                            $displayOrderStatus = $orderStatus;
                            $turnaroundDays = (int) ($row['service_turnaround_days'] ?? 0);
                            $dueDateRaw = trim((string) (($row['order_due_date'] ?? $row['due_date'] ?? '') ?: ''));
                            $dueDaysLeft = $row['order_due_days_left'] ?? null;
                            $dueDateDisplay = $kanbanDateFormat($dueDateRaw);
                            $dueDateBadge = $kanbanDueDateBadge($dueDaysLeft, $dueDateRaw, $orderStatus);
                            $dueDateLabel = $kanbanDueDateLabel($dueDaysLeft, $dueDateRaw, $orderStatus, $turnaroundDays);
                            $rowNo = $index + 1;
                            $customerNameAsPerPan = trim((string) ($row['customer_name_as_per_pan'] ?? ''));
                            $customerPanNumber = trim((string) ($row['customer_pan_number'] ?? ''));
                            $customerMobile = trim((string) ($row['customer_mobile'] ?? ''));
                            $submittedByUserId = (int) ($row['submitted_by_user_id'] ?? 0);
                            $submittedByUserExists = (int) ($row['submitted_by_user_exists'] ?? 0) === 1;
                            $submittedByUserName = trim((string) ($row['submitted_by_user_name'] ?? ''));
                        ?>
                        <?php
                            $dueSort = $dueSortScore($row);
                            $dueRank = (int) ($dueSort[0] ?? 9);
                            $dueDistance = (int) ($dueSort[1] ?? 0);
                            $dueTimestamp = (int) ($dueSort[2] ?? 0);
                            $orderIdForSort = (int) ($row['id'] ?? 0);
                            $orderUrl = base_url('admin/orders/show?id=' . $orderIdForSort);
                            $createdTimestamp = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
                            $clientSortValue = strtolower((string) ($row['client_name'] ?? ''));
                            $serviceSortValue = strtolower((string) ($row['service_title'] ?? ''));
                            $orderNoSortValue = strtolower((string) ($row['order_no'] ?? ''));
                            $orderStatusSortValue = $normalizeOrderStatusKey($orderStatus);
                            $paymentStatusSortValue = strtolower(trim((string) $paymentStatus));
                            $missingDocumentsCount = (int) (
                                $row['missing_documents_count']
                                ?? $row['documents_missing_count']
                                ?? $row['required_documents_missing_count']
                                ?? $row['missing_required_documents_count']
                                ?? $row['missing_required_documents']
                                ?? 0
                            );
                            $missingDocumentsFlag = strtolower(trim((string) (
                                $row['documents_missing']
                                ?? $row['has_missing_documents']
                                ?? $row['missing_documents']
                                ?? ''
                            )));
                            $documentsMissingSortValue = ($missingDocumentsCount > 0 || in_array($missingDocumentsFlag, ['1', 'true', 'yes', 'missing', 'required'], true)) ? '1' : '0';
                        ?>
                        <tr class="group h-8 cursor-pointer align-middle transition hover:bg-indigo-50/50 focus:outline-none focus-visible:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-400" data-order-row data-order-url="<?= e($orderUrl) ?>" tabindex="0" role="link" aria-label="Open order <?= e((string) ($row['order_no'] ?? $orderIdForSort)) ?>" data-kanban-record-row data-original-index="<?= e((string) $index) ?>" data-due-rank="<?= e((string) $dueRank) ?>" data-due-distance="<?= e((string) $dueDistance) ?>" data-due-timestamp="<?= e((string) $dueTimestamp) ?>" data-created-timestamp="<?= e((string) $createdTimestamp) ?>" data-order-id="<?= e((string) $orderIdForSort) ?>" data-client-sort="<?= e($clientSortValue) ?>" data-service-sort="<?= e($serviceSortValue) ?>" data-order-no-sort="<?= e($orderNoSortValue) ?>" data-order-status="<?= e($orderStatusSortValue) ?>" data-payment-status="<?= e($paymentStatusSortValue) ?>" data-documents-missing="<?= e($documentsMissingSortValue) ?>">
                            <td data-kanban-row-number class="sticky left-0 z-10 whitespace-nowrap border-r border-slate-100 bg-white px-2 py-1 text-[12px] font-normal text-slate-500 group-hover:bg-indigo-50 <?= e($kanbanPaymentBorder($paymentStatus)) ?> border-l-4">
                                <?= e((string) $rowNo) ?>
                            </td>
                            <td class="whitespace-nowrap border-r border-slate-100 px-2 py-1">
                                <div class="flex items-center gap-2">
                                    <a href="<?= e($orderUrl) ?>" class="inline-flex h-6 items-center justify-center rounded-lg px-2.5 text-[11px]">
                                        <span class="font-normal text-slate-900"><?= e((string) ($row['order_no'] ?? '-')) ?></span>
                                    </a>
                                </div>
                                <p class="px-2.5 text-[10px] font-normal text-slate-500">Phone: <?= e($customerMobile !== '' ? $customerMobile : '-') ?></p>
                            </td>
                            <td class="max-w-[210px] truncate whitespace-nowrap border-r border-slate-100 px-2 py-1 font-normal text-slate-800">
                                
                                
                                
                                 <div class="flex items-center gap-2">
                                    <a href="<?= e($orderUrl) ?>" class="inline-flex h-6 items-center justify-center rounded-lg px-2.5 text-[11px]">
                                        <span class="font-normal text-slate-900">
                                <?= e($customerNameAsPerPan !== '' ? $customerNameAsPerPan : '-') ?></span>
                                    </a>
                                </div>
                                <p class="px-2.5 text-[10px] font-normal text-slate-500">PAN: 
                                <?= e($customerPanNumber !== '' ? $customerPanNumber : '-') ?></p>
                                
                                
                                
                                
                                
                            </td>
                           
                           
                            <td class="max-w-[170px] truncate whitespace-nowrap border-r border-slate-100 px-2 py-1 font-normal text-slate-800">
                                <div class="truncate"> <?= e($submittedByUserName) ?></div>
                               
                            </td>
                            <td class="max-w-[190px] truncate whitespace-nowrap border-r border-slate-100 px-2 py-1 font-normal text-slate-700">
                                <?= e((string) ($row['service_title'] ?? '-')) ?>
                            </td>
                            <td class="whitespace-nowrap border-r border-slate-100 px-2 py-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-normal text-slate-800"><?= e($dueDateDisplay) ?></span>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-normal <?= e($dueDateBadge) ?>">
                                        <?= e($dueDateLabel) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap border-r border-slate-100 px-2 py-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-normal <?= e($orderStatusTone($displayOrderStatus)) ?>">
                                    <?= e($orderStatusLabel($displayOrderStatus)) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap border-r border-slate-100 px-2 py-1">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-normal <?= e($orderStatusTone($paymentStatus)) ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= e($kanbanPaymentSignal($paymentStatus)) ?>"></span>
                                    <?= e($orderStatusLabel($paymentStatus)) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap border-r border-slate-100 px-2 py-1 text-right font-normal text-slate-800">
                                <?= e($money($row['fee_amount'] ?? 0)) ?>
                            </td>
                            <td class="max-w-[160px] truncate whitespace-nowrap border-r border-slate-100 px-2 py-1">
                                <span class="inline-flex max-w-full rounded-full bg-indigo-50 px-2 py-0.5 text-[12px] font-normal text-indigo-700 ring-1 ring-indigo-100">
                                    <span class="truncate"><?= e($resolveAssigneeName($row['assigned_user_id'] ?? 0)) ?></span>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-2 py-1 text-center">
                                <a href="<?= e($orderUrl) ?>" class="inline-flex h-6 items-center justify-center rounded-lg bg-slate-900 px-2.5 text-[11px] font-normal uppercase tracking-[0.10em] text-white shadow-sm transition hover:bg-slate-800">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
};


$workflowDisplayStatusLabel = static function (string $status) use ($orderStatusLabel): string {
    $normalized = strtolower(trim($status));
    if (in_array($normalized, ['submitted', 'submited'], true)) {
        return 'Pending';
    }

    return $orderStatusLabel($status);
};

$workflowDueBucket = static function (array $row): string {
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if (in_array($status, ['completed', 'approved'], true)) {
        return 'closed';
    }

    $turnaroundDays = (int) ($row['service_turnaround_days'] ?? 0);
    $dueDateRaw = trim((string) (($row['order_due_date'] ?? $row['due_date'] ?? '') ?: ''));

    if ($turnaroundDays <= 0 || $dueDateRaw === '' || $dueDateRaw === '0000-00-00' || $dueDateRaw === '0000-00-00 00:00:00') {
        return 'no_tat';
    }

    $dueDaysLeft = $row['order_due_days_left'] ?? null;
    $days = is_numeric($dueDaysLeft) ? (int) $dueDaysLeft : null;

    if ($days === null) {
        try {
            $today = new DateTimeImmutable('today');
            $dueDate = new DateTimeImmutable(substr($dueDateRaw, 0, 10));
            $days = (int) $today->diff($dueDate)->format('%r%a');
        } catch (Throwable $e) {
            return 'no_tat';
        }
    }

    if ($days < 0) {
        return 'overdue';
    }

    if ($days === 0) {
        return 'due_today';
    }

    if ($days <= 2) {
        return 'due_soon';
    }

    return 'healthy';
};

$workflowStatusCounts = [
    'submitted' => 0,
    'pending_review' => 0,
    'pending_clarification' => 0,
    'approved' => 0,
    'work_in_progress' => 0,
    'completed' => 0,
    'rejected' => 0,
];

$workflowPaymentAttention = 0;
$workflowPaidOrVerified = 0;
$workflowAssigned = 0;
$workflowUnassigned = 0;
$workflowOverdue = 0;
$workflowDueToday = 0;
$workflowDueSoon = 0;
$workflowNoTat = 0;
$workflowClosed = 0;
$workflowStageScore = 0;

foreach ($rows as $workflowRow) {
    $status = $normalizeOrderStatusKey($workflowRow['status'] ?? 'pending_review');
    if ($status === '') {
        $status = 'pending_review';
    }

    if (!array_key_exists($status, $workflowStatusCounts)) {
        $workflowStatusCounts[$status] = 0;
    }
    $workflowStatusCounts[$status]++;

    $paymentStatus = strtolower(trim((string) ($workflowRow['payment_status'] ?? 'pending')));
    if (in_array($paymentStatus, ['paid', 'verified', 'success'], true)) {
        $workflowPaidOrVerified++;
    }
    if (in_array($paymentStatus, ['pending', 'pending_review', 'partial', 'unpaid', 'failed', 'overdue', 'waiting_for_payment'], true)) {
        $workflowPaymentAttention++;
    }

    if ((int) ($workflowRow['assigned_user_id'] ?? 0) > 0) {
        $workflowAssigned++;
    } else {
        $workflowUnassigned++;
    }

    $dueBucket = $workflowDueBucket($workflowRow);
    if ($dueBucket === 'overdue') {
        $workflowOverdue++;
    } elseif ($dueBucket === 'due_today') {
        $workflowDueToday++;
    } elseif ($dueBucket === 'due_soon') {
        $workflowDueSoon++;
    } elseif ($dueBucket === 'no_tat') {
        $workflowNoTat++;
    } elseif ($dueBucket === 'closed') {
        $workflowClosed++;
    }

    $statusScore = match ($status) {
        'submitted' => 18,
        'pending_review' => 32,
        'pending_clarification' => 38,
        'approved' => 58,
        'work_in_progress' => 76,
        'completed' => 100,
        'rejected' => 100,
        default => 28,
    };

    if ((int) ($workflowRow['assigned_user_id'] ?? 0) > 0 && !in_array($status, ['completed', 'rejected'], true)) {
        $statusScore += 7;
    }
    if (in_array($paymentStatus, ['paid', 'verified', 'success'], true) && !in_array($status, ['completed', 'rejected'], true)) {
        $statusScore += 8;
    }

    $workflowStageScore += min(100, $statusScore);
}

$workflowTotalOrders = count($rows);
$workflowProgressPercent = $workflowTotalOrders > 0 ? (int) round($workflowStageScore / $workflowTotalOrders) : 0;
$workflowActiveOrders = max(0, $workflowTotalOrders - (int) ($workflowStatusCounts['completed'] ?? 0) - (int) ($workflowStatusCounts['rejected'] ?? 0));

if ($workflowOverdue > 0) {
    $workflowNextActionTitle = 'Clear overdue orders first';
    $workflowNextActionBody = $workflowOverdue . ' order(s) have crossed turnaround time. Review due date, payment and assignment before moving ahead.';
    $workflowNextActionTab = 'all';
    $workflowNextActionTone = 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
} elseif ($workflowDueToday > 0) {
    $workflowNextActionTitle = 'Finish orders due today';
    $workflowNextActionBody = $workflowDueToday . ' order(s) are due today. Prioritize active execution, deliverables and final client updates.';
    $workflowNextActionTab = 'all';
    $workflowNextActionTone = 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
} elseif ((int) ($workflowStatusCounts['pending_review'] ?? 0) > 0) {
    $workflowNextActionTitle = 'Review pending orders';
    $workflowNextActionBody = (int) ($workflowStatusCounts['pending_review'] ?? 0) . ' order(s) need document/payment review before approval.';
    $workflowNextActionTab = 'pending_review';
    $workflowNextActionTone = 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
} elseif ((int) ($workflowStatusCounts['pending_clarification'] ?? 0) > 0) {
    $workflowNextActionTitle = 'Resolve pending clarification';
    $workflowNextActionBody = (int) ($workflowStatusCounts['pending_clarification'] ?? 0) . ' order(s) are waiting for clarification. Check client query, missing details or internal notes.';
    $workflowNextActionTab = 'pending_clarification';
    $workflowNextActionTone = 'bg-orange-50 text-orange-700 ring-1 ring-orange-200';
} elseif ($workflowPaymentAttention > 0) {
    $workflowNextActionTitle = 'Resolve payment attention items';
    $workflowNextActionBody = $workflowPaymentAttention . ' order(s) still have pending, partial or failed payment status.';
    $workflowNextActionTab = 'all';
    $workflowNextActionTone = 'bg-blue-50 text-blue-700 ring-1 ring-blue-200';
} elseif ($workflowUnassigned > 0) {
    $workflowNextActionTitle = 'Assign remaining orders';
    $workflowNextActionBody = $workflowUnassigned . ' order(s) are not assigned to an executive yet.';
    $workflowNextActionTab = 'all';
    $workflowNextActionTone = 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200';
} elseif ((int) ($workflowStatusCounts['work_in_progress'] ?? 0) > 0) {
    $workflowNextActionTitle = 'Push work-in-progress orders';
    $workflowNextActionBody = (int) ($workflowStatusCounts['work_in_progress'] ?? 0) . ' order(s) are active. Upload deliverables or update completion notes.';
    $workflowNextActionTab = 'work_in_progress';
    $workflowNextActionTone = 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200';
} else {
    $workflowNextActionTitle = 'Workflow is stable';
    $workflowNextActionBody = 'No urgent workflow blockers found in the current filtered list.';
    $workflowNextActionTab = 'all';
    $workflowNextActionTone = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
}

$workflowSteps = [
    [
        'step' => '01',
        'title' => 'Client Intake',
        'count' => (int) ($workflowStatusCounts['submitted'] ?? 0),
        'tab' => 'submitted',
        'description' => 'New client orders received and waiting for first action.',
        'summary' => 'Display submitted as Pending',
        'tone' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
    ],
    [
        'step' => '02',
        'title' => 'Review Queue',
        'count' => (int) ($workflowStatusCounts['pending_review'] ?? 0),
        'tab' => 'pending_review',
        'description' => 'Check client documents, PAN/contact details and service request.',
        'summary' => 'Needs verification',
        'tone' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    ],
    [
        'step' => '03',
        'title' => 'Pending Clarification',
        'count' => (int) ($workflowStatusCounts['pending_clarification'] ?? 0),
        'tab' => 'pending_clarification',
        'description' => 'Orders waiting for clarification before approval or execution.',
        'summary' => 'Clarification required',
        'tone' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
    ],
    [
        'step' => '04',
        'title' => 'Payment Check',
        'count' => $workflowPaymentAttention,
        'tab' => 'all',
        'description' => 'Track pending, partial, failed or unverified payment statuses.',
        'summary' => $workflowPaidOrVerified . ' paid/verified',
        'tone' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
    ],
    [
        'step' => '05',
        'title' => 'Approval Gate',
        'count' => (int) ($workflowStatusCounts['approved'] ?? 0),
        'tab' => 'approved',
        'description' => 'Approved orders ready for assignment or active execution.',
        'summary' => 'Approved queue',
        'tone' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    ],
    [
        'step' => '06',
        'title' => 'Assignment',
        'count' => $workflowUnassigned,
        'tab' => 'all',
        'description' => 'Make sure every active order has a responsible executive.',
        'summary' => $workflowAssigned . ' assigned',
        'tone' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
    ],
    [
        'step' => '07',
        'title' => 'Due Control',
        'count' => $workflowOverdue + $workflowDueToday + $workflowDueSoon,
        'tab' => 'all',
        'description' => 'Prioritize turnaround time using due today, overdue and due soon.',
        'summary' => $workflowOverdue . ' overdue · ' . $workflowDueToday . ' due today',
        'tone' => $workflowOverdue > 0 ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
    ],
    [
        'step' => '08',
        'title' => 'Execution',
        'count' => (int) ($workflowStatusCounts['work_in_progress'] ?? 0),
        'tab' => 'work_in_progress',
        'description' => 'Orders currently being processed by the assigned team.',
        'summary' => 'Active processing',
        'tone' => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
    ],
    [
        'step' => '09',
        'title' => 'Completion',
        'count' => (int) ($workflowStatusCounts['completed'] ?? 0),
        'tab' => 'completed',
        'description' => 'Finished orders with deliverables and completion notes.',
        'summary' => $workflowClosed . ' closed by TAT',
        'tone' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    ],
    [
        'step' => '10',
        'title' => 'Exception Review',
        'count' => (int) ($workflowStatusCounts['rejected'] ?? 0),
        'tab' => 'rejected',
        'description' => 'Rejected, blocked or cancelled orders needing final audit.',
        'summary' => 'Exception queue',
        'tone' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
    ],
];

?>

<section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Order Pipeline</p>
            <h2 class="mt-0.5 text-xl font-black tracking-[-0.04em] text-slate-900">
                <?= $isAssignedStaffUser && !$isAdminLikeUser ? 'My Assigned Orders' : 'Manage Orders' ?>
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                <?= $isAssignedStaffUser && !$isAdminLikeUser ? 'Create and manage only orders assigned to you.' : 'Full admin access for orders, documents, payments, assignment and progress.' ?>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <?php if ($canCreateOrders): ?>
                <button
                    type="button"
                    id="adminAddOrderToggle"
                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800"
                >
                    + Add Order
                </button>
            <?php endif; ?>

            <div class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">
                <?= $isAssignedStaffUser && !$isAdminLikeUser ? 'Assigned Access' : 'Full Access' ?>
            </div>
        </div>
    </div>



    <?php if ($canCreateOrders): ?>
        <div id="adminAddOrderModal" class="fixed inset-0 z-[9999] hidden" aria-labelledby="adminAddOrderModalTitle" aria-modal="true" role="dialog">
            <div class="admin-order-modal-backdrop absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>

            <div class="relative flex min-h-screen items-center justify-center p-4">
                <div class="admin-order-modal-panel max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-[28px] border border-white/70 bg-white shadow-[0_30px_90px_rgba(15,23,42,0.28)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Admin Action</p>
                            <h3 id="adminAddOrderModalTitle" class="mt-0.5 text-lg font-bold tracking-[-0.03em] text-slate-900">Add Order for User / Client</h3>
                            <p class="mt-1 text-sm text-slate-500">Create order, add contact details, select FY only for ITR services and upload client documents before final submission.</p>
                        </div>

                        <button type="button" class="admin-order-modal-close inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" aria-label="Close add order modal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"></path></svg>
                        </button>
                    </div>

                    <div class="max-h-[calc(92vh-86px)] overflow-y-auto px-5 py-5">
                        <form id="adminAddOrderForm" method="post" action="<?= e(base_url('admin/orders/store')) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-5">
                            <?= csrf_field() ?>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Client / User</span>
                                    <select id="adminOrderClient" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" name="client_id" required>
                                        <option value="">Choose client/user</option>
                                        <?php foreach ($clients as $client): ?>
                                            <?php
                                                $clientName = trim((string) ($client['company_name'] ?? '')) ?: trim((string) ($client['name'] ?? ('Client #' . ($client['id'] ?? ''))));
                                                $clientPhone = trim((string) ($client['phone'] ?? $client['mobile'] ?? ''));
                                                $clientEmail = trim((string) ($client['email'] ?? ''));
                                                $clientCity = trim((string) ($client['city'] ?? ''));
                                                $clientLabel = $clientName;
                                                if ($clientPhone !== '') {
                                                    $clientLabel .= ' · ' . $clientPhone;
                                                } elseif ($clientEmail !== '') {
                                                    $clientLabel .= ' · ' . $clientEmail;
                                                }
                                                if ($clientCity !== '') {
                                                    $clientLabel .= ' · ' . $clientCity;
                                                }
                                            ?>
                                            <option value="<?= e((string) ($client['id'] ?? '')) ?>" data-name="<?= e($clientName) ?>" data-pan="<?= e((string) ($client['pan_number'] ?? $client['pan'] ?? '')) ?>" data-phone="<?= e($clientPhone) ?>" data-email="<?= e($clientEmail) ?>">
                                                <?= e($clientLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Service</span>
                                    <select id="adminOrderService" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" name="service_id" required>
                                        <option value="">Choose service</option>
                                        <?php foreach ($orderServices as $service): ?>
                                            <option
                                                value="<?= e((string) ($service['id'] ?? '')) ?>"
                                                data-fee="<?= e((string) ($service['filing_fee'] ?? 0)) ?>"
                                                data-slug="<?= e((string) ($service['slug'] ?? '')) ?>"
                                            >
                                                <?= e((string) ($service['title'] ?? 'Service')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label id="adminOrderFinancialYearWrap" class="hidden grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Financial Year</span>
                                    <select id="adminOrderFinancialYear" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" name="financial_year" disabled>
                                        <option value="">Select Financial Year</option>
                                        <?php foreach ($financialYears as $year): ?>
                                            <option value="<?= e((string) $year) ?>"><?= e((string) $year) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Fee Amount</span>
                                    <input id="adminOrderFee" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" type="number" step="0.01" min="0" name="fee_amount" placeholder="0.00" required>
                                </label>
                            </div>

                            <div class="rounded-[24px] border border-indigo-100 bg-indigo-50/50 p-4">
                                <div class="mb-3">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Customer Contact Details</p>
                                    <p class="mt-1 text-sm text-slate-500">Name as per PAN, PAN number, mobile and email are required for all services before document upload. These details are saved in customer_details.</p>
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Name as per PAN <span class="text-rose-500">*</span></span>
                                        <input id="adminOrderCustomerNameAsPerPan" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" type="text" name="customer_name_as_per_pan" placeholder="Enter name exactly as per PAN" required>
                                    </label>
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">PAN Number <span class="text-rose-500">*</span></span>
                                        <input id="adminOrderCustomerPanNumber" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium uppercase tracking-[0.08em] text-slate-700 outline-none transition placeholder:normal-case placeholder:tracking-normal focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" type="text" name="customer_pan_number" maxlength="10" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" placeholder="ABCDE1234F" required oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10)">
                                    </label>
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Mobile Number <span class="text-rose-500">*</span></span>
                                        <input id="adminOrderContactMobile" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" type="text" name="contact_mobile" placeholder="Enter customer mobile number" required>
                                    </label>
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Email Address <span class="text-rose-500">*</span></span>
                                        <input id="adminOrderContactEmail" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" type="email" name="contact_email" placeholder="Enter customer email" required>
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Order Status</span>
                                    <select class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700" name="status">
                                        <option value="pending_review">Pending Review</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="pending_clarification">Pending Clarification</option>
                                        <option value="approved">Approved</option>
                                        <option value="work_in_progress">Work In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Payment Method</span>
                                    <select class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700" name="payment_method">
                                        <option value="manual">Manual</option>
                                        <option value="upi">UPI</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="razorpay">Razorpay</option>
                                        <option value="cash">Cash</option>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Payment Status</span>
                                    <select class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700" name="payment_status">
                                        <option value="pending">Pending</option>
                                        <option value="pending_review">Pending Review</option>
                                        <option value="verified">Verified</option>
                                        <option value="paid">Paid</option>
                                        <option value="partial">Partial</option>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-[12px] font-semibold text-slate-600">Payment Reference</span>
                                    <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700" type="text" name="payment_reference" placeholder="UPI/Bank ref">
                                </label>

                                <?php if ($canAssignOrders): ?>
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Assign Staff</span>
                                        <select class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700" name="assigned_user_id">
                                            <option value="0">Unassigned</option>
                                            <?php foreach ($assignees as $staff): ?>
                                                <option value="<?= e((string) ($staff['id'] ?? 0)) ?>"><?= e((string) ($staff['name'] ?? ('User #' . ($staff['id'] ?? '')))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                <?php endif; ?>
                            </div>

                            <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-4">
                                <div class="mb-3">
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Upload Documents</p>
                                    <p class="mt-1 text-sm text-slate-500">Select document type, upload files, preview thumbnails/list, then submit the order.</p>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-[1fr_1.2fr_auto]">
                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Document Type</span>
                                        <select id="adminOrderDocumentType" class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                                            <option value="0" data-label="General Document">General Document</option>
                                        </select>
                                    </label>

                                    <label class="grid gap-1.5">
                                        <span class="text-[12px] font-semibold text-slate-600">Choose File(s)</span>
                                        <input id="adminOrderDocumentPicker" class="block w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" multiple>
                                    </label>

                                    <div class="flex items-end">
                                        <button id="adminOrderClearDocuments" type="button" class="h-11 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700">Clear List</button>
                                    </div>
                                </div>

                                <input id="adminOrderDocumentsInput" class="hidden" type="file" name="admin_order_documents[]" multiple>
                                <div id="adminOrderDocumentMeta"></div>
                                <div id="adminOrderDocumentList" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
                            </div>

                            <label class="grid gap-1.5">
                                <span class="text-[12px] font-semibold text-slate-600">Notes</span>
                                <textarea class="min-h-[90px] rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60" name="notes" rows="3" placeholder="Internal context or client request"></textarea>
                            </label>

                            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                                <button type="button" class="admin-order-modal-close inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700">Cancel</button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-6 text-sm font-bold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)]">Create Order</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>


 
    <div class="border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-indigo-50/40 px-3 py-3">
        <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Excel Kanban Workflow</p>
                <h3 class="mt-1 text-lg font-black tracking-[-0.04em] text-slate-900">Orders by Status & Payment Health</h3>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Each order is displayed as a compact Excel-style single-line record with due priority, status, payment, assignment and manage action.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e((string) count($rows)) ?> Orders</span>
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e($money($kanbanTotalValue)) ?></span>
            </div>
        </div>

        <div class="mb-3 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($kanbanPaymentStatusColumns as $paymentKey => $paymentMeta): ?>
                <?php
                    $paymentCount = (int) ($kanbanPaymentSummary[$paymentKey]['count'] ?? 0);
                    $paymentAmount = (float) ($kanbanPaymentSummary[$paymentKey]['amount'] ?? 0);
                ?>
                <div class="rounded-[18px] border border-slate-200 bg-white p-2.5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full px-2 py-0.5 text-[11px] font-black <?= e((string) ($paymentMeta['badge'] ?? $orderStatusTone($paymentKey))) ?>">
                            <span class="h-2 w-2 rounded-full <?= e($kanbanPaymentSignal((string) $paymentKey)) ?>"></span>
                            <?= e((string) ($paymentMeta['title'] ?? $orderStatusLabel($paymentKey))) ?>
                        </span>
                        <span class="text-sm font-black text-slate-900"><?= e((string) $paymentCount) ?></span>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between gap-2">
                        <p class="text-[10px] font-normal uppercase tracking-[0.12em] text-slate-400">Payment Value</p>
                        <p class="text-xs font-normal text-slate-900"><?= e($money($paymentAmount)) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rounded-[22px] border border-slate-200 bg-white/90 p-2 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex gap-2 overflow-x-auto rounded-[18px] bg-slate-100/80 p-1.5" role="tablist" aria-label="Order Kanban Status Tabs">
                <button
                    type="button"
                    class="order-kanban-tab inline-flex shrink-0 items-center gap-2 rounded-2xl bg-slate-900 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-white shadow-sm transition"
                    data-order-kanban-tab="all"
                    aria-selected="true"
                    role="tab"
                >
                    <span>All Orders</span>
                    <span class="rounded-full bg-white/15 px-2 py-0.5 text-[10px]"><?= e((string) count($rows)) ?></span>
                </button>

                <?php foreach ($kanbanOrderStatusColumns as $statusKey => $statusMeta): ?>
                    <?php $tabCount = count($kanbanRowsByStatus[$statusKey] ?? []); ?>
                    <button
                        type="button"
                        class="order-kanban-tab inline-flex shrink-0 items-center gap-2 rounded-2xl px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-slate-600 transition hover:bg-white hover:text-slate-900"
                        data-order-kanban-tab="<?= e((string) $statusKey) ?>"
                        aria-selected="false"
                        role="tab"
                    >
                        <span><?= e((string) ($statusMeta['title'] ?? $orderStatusLabel($statusKey))) ?></span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-700 ring-1 ring-slate-200"><?= e((string) $tabCount) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="mt-4">
                <div class="order-kanban-panel" data-order-kanban-panel="all" role="tabpanel">
                    <div class="mb-4 flex flex-col gap-2 rounded-[22px] border border-slate-200 bg-slate-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">All Statuses</p>
                            <h4 class="mt-1 text-base font-black text-slate-900">Complete Excel Kanban View</h4>
                            <p class="mt-1 text-xs text-slate-500">All filtered orders are shown as single-line spreadsheet records.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e((string) count($rows)) ?> Orders</span>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e($money($kanbanTotalValue)) ?></span>
                        </div>
                    </div>

                    <?php $renderExcelKanbanTable($rows, 'No orders found.'); ?>
                </div>

                <?php foreach ($kanbanOrderStatusColumns as $statusKey => $statusMeta): ?>
                    <?php
                        $kanbanItems = $kanbanRowsByStatus[$statusKey] ?? [];
                        $kanbanColumnTotal = (float) ($kanbanStatusTotals[$statusKey] ?? 0);
                    ?>

                    <div class="order-kanban-panel hidden" data-order-kanban-panel="<?= e((string) $statusKey) ?>" role="tabpanel">
                        <div class="mb-4 flex flex-col gap-2 rounded-[22px] border border-slate-200 bg-slate-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Order Status Tab</p>
                                <h4 class="mt-1 text-base font-black text-slate-900"><?= e((string) ($statusMeta['title'] ?? $orderStatusLabel($statusKey))) ?></h4>
                                <p class="mt-1 text-xs text-slate-500"><?= e((string) ($statusMeta['description'] ?? 'Order workflow stage.')) ?></p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e((string) count($kanbanItems)) ?> Orders</span>
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-normal text-slate-700 ring-1 ring-slate-200"><?= e($money($kanbanColumnTotal)) ?></span>
                            </div>
                        </div>

                        <?php $renderExcelKanbanTable($kanbanItems, 'No orders in this status.'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('adminAddOrderModal');
    const openBtn = document.getElementById('adminAddOrderToggle');
    const closeBtns = document.querySelectorAll('.admin-order-modal-close, .admin-order-modal-backdrop');
    const serviceSelect = document.getElementById('adminOrderService');
    const feeInput = document.getElementById('adminOrderFee');
    const fyWrap = document.getElementById('adminOrderFinancialYearWrap');
    const fySelect = document.getElementById('adminOrderFinancialYear');
    const clientSelect = document.getElementById('adminOrderClient');
    const customerNameAsPerPan = document.getElementById('adminOrderCustomerNameAsPerPan');
    const customerPanNumber = document.getElementById('adminOrderCustomerPanNumber');
    const contactMobile = document.getElementById('adminOrderContactMobile');
    const contactEmail = document.getElementById('adminOrderContactEmail');
    const docType = document.getElementById('adminOrderDocumentType');
    const docPicker = document.getElementById('adminOrderDocumentPicker');
    const docInput = document.getElementById('adminOrderDocumentsInput');
    const docList = document.getElementById('adminOrderDocumentList');
    const docMeta = document.getElementById('adminOrderDocumentMeta');
    const clearDocs = document.getElementById('adminOrderClearDocuments');

    const allowedFySlugs = <?= json_encode($allowedFinancialYearSlugs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const requirementsByService = <?= json_encode($orderRequirementsByService, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let documentItems = [];

    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    closeBtns.forEach(function (btn) { btn.addEventListener('click', closeModal); });

    function isContactRequirement(label) {
        const text = String(label || '').toLowerCase();
        return text.includes('email') || text.includes('mobile') || text.includes('phone') || text.includes('contact');
    }

    function refreshDocumentTypes() {
        if (!docType || !serviceSelect) return;
        const serviceId = serviceSelect.value || '';
        const requirements = requirementsByService[serviceId] || [];
        docType.innerHTML = '<option value="0" data-label="General Document">General Document</option>';

        requirements.forEach(function (item) {
            const label = item.label || 'Document';
            if (isContactRequirement(label)) return;
            const option = document.createElement('option');
            option.value = String(item.id || 0);
            option.dataset.label = label;
            option.textContent = label + (Number(item.is_required || 0) === 1 ? ' *' : '');
            docType.appendChild(option);
        });
    }

    function refreshFinancialYear() {
        if (!serviceSelect || !fyWrap || !fySelect) return;
        const selected = serviceSelect.options[serviceSelect.selectedIndex];
        const slug = (selected ? selected.dataset.slug : '') || '';
        const showFy = allowedFySlugs.includes(slug);

        if (showFy) {
            fyWrap.classList.remove('hidden');
            fySelect.disabled = false;
            fySelect.required = true;
        } else {
            fyWrap.classList.add('hidden');
            fySelect.value = '';
            fySelect.disabled = true;
            fySelect.required = false;
        }
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            const selected = serviceSelect.options[serviceSelect.selectedIndex];
            if (selected && feeInput && selected.dataset.fee !== undefined) {
                feeInput.value = selected.dataset.fee || '';
            }
            refreshFinancialYear();
            refreshDocumentTypes();
        });
        refreshFinancialYear();
        refreshDocumentTypes();
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            const selected = clientSelect.options[clientSelect.selectedIndex];
            if (!selected) return;
            if (customerNameAsPerPan && !customerNameAsPerPan.value) customerNameAsPerPan.value = selected.dataset.name || '';
            if (customerPanNumber && !customerPanNumber.value) customerPanNumber.value = (selected.dataset.pan || '').toUpperCase();
            if (contactMobile && !contactMobile.value) contactMobile.value = selected.dataset.phone || '';
            if (contactEmail && !contactEmail.value) contactEmail.value = selected.dataset.email || '';
        });
    }

    function rebuildFileInput() {
        if (!docInput || !docMeta || !docList) return;
        const dt = new DataTransfer();
        docMeta.innerHTML = '';
        docList.innerHTML = '';

        documentItems.forEach(function (item, index) {
            dt.items.add(item.file);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'admin_order_document_requirement_ids[]';
            idInput.value = item.requirementId;
            docMeta.appendChild(idInput);

            const labelInput = document.createElement('input');
            labelInput.type = 'hidden';
            labelInput.name = 'admin_order_document_labels[]';
            labelInput.value = item.label;
            docMeta.appendChild(labelInput);

            const card = document.createElement('div');
            card.className = 'rounded-2xl border border-slate-200 bg-white p-3 shadow-sm';

            const isImage = item.file.type && item.file.type.startsWith('image/');
            let preview = '<div class="flex h-24 items-center justify-center rounded-xl bg-slate-100 text-xs font-black uppercase tracking-[0.16em] text-slate-500">FILE</div>';
            if (isImage) {
                const url = URL.createObjectURL(item.file);
                preview = '<img src="' + url + '" class="h-24 w-full rounded-xl object-cover" alt="Document preview">';
            }

            card.innerHTML = preview +
                '<div class="mt-3 min-w-0">' +
                    '<div class="truncate text-sm font-black text-slate-900">' + escapeHtml(item.file.name) + '</div>' +
                    '<div class="mt-1 text-xs font-semibold text-slate-500">' + escapeHtml(item.label) + '</div>' +
                    '<button type="button" data-remove="' + index + '" class="mt-2 inline-flex h-8 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-bold text-rose-700">Remove</button>' +
                '</div>';
            docList.appendChild(card);
        });

        docInput.files = dt.files;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char];
        });
    }

    if (docPicker) {
        docPicker.addEventListener('change', function () {
            const selectedType = docType ? docType.options[docType.selectedIndex] : null;
            const label = selectedType ? (selectedType.dataset.label || selectedType.textContent || 'Document') : 'Document';
            const requirementId = selectedType ? selectedType.value : '0';

            Array.from(docPicker.files || []).forEach(function (file) {
                documentItems.push({file: file, label: label.replace(/\s+\*$/, ''), requirementId: requirementId});
            });

            docPicker.value = '';
            rebuildFileInput();
        });
    }

    if (docList) {
        docList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove]');
            if (!button) return;
            const index = Number(button.dataset.remove);
            documentItems.splice(index, 1);
            rebuildFileInput();
        });
    }

    if (clearDocs) {
        clearDocs.addEventListener('click', function () {
            documentItems = [];
            rebuildFileInput();
        });
    }

    const orderKanbanTabs = document.querySelectorAll('[data-order-kanban-tab]');
    const orderKanbanPanels = document.querySelectorAll('[data-order-kanban-panel]');

    function activateOrderKanbanTab(tabKey) {
        orderKanbanTabs.forEach(function (tab) {
            const isActive = tab.dataset.orderKanbanTab === tabKey;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.classList.toggle('bg-slate-900', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('shadow-sm', isActive);
            tab.classList.toggle('text-slate-600', !isActive);
            tab.classList.toggle('hover:bg-white', !isActive);
            tab.classList.toggle('hover:text-slate-900', !isActive);
        });

        orderKanbanPanels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.dataset.orderKanbanPanel !== tabKey);
        });
    }

    function setupExcelKanbanPagination(root) {
        const rows = Array.from(root.querySelectorAll('[data-kanban-record-row]'));
        const tbody = root.querySelector('tbody');
        const sortSelect = root.querySelector('[data-kanban-sort]');
        const pageSizeSelect = root.querySelector('[data-kanban-page-size]');
        const customPageSize = root.querySelector('[data-kanban-custom-page-size]');
        const pageInfo = root.querySelector('[data-kanban-page-info]');
        const pageNumber = root.querySelector('[data-kanban-page-number]');
        const prevButton = root.querySelector('[data-kanban-prev]');
        const nextButton = root.querySelector('[data-kanban-next]');

        if (!tbody || rows.length === 0) {
            if (pageInfo) pageInfo.textContent = 'Showing 0 records';
            if (pageNumber) pageNumber.textContent = '0 / 0';
            if (prevButton) prevButton.disabled = true;
            if (nextButton) nextButton.disabled = true;
            return;
        }

        const emptyFilterRow = document.createElement('tr');
        const columnCount = root.querySelectorAll('thead th').length || 1;
        emptyFilterRow.setAttribute('data-kanban-filter-empty', 'true');
        emptyFilterRow.className = 'hidden';
        emptyFilterRow.innerHTML = '<td colspan="' + columnCount + '" class="px-4 py-6 text-center text-sm font-normal text-slate-400">No records match this filter.</td>';
        tbody.appendChild(emptyFilterRow);

        const state = {
            page: 1,
            perPage: Number(root.dataset.defaultPerPage || 20) || 20,
            sort: 'all'
        };

        function numberValue(row, key, fallback) {
            const value = Number(row.dataset[key]);
            return Number.isFinite(value) ? value : fallback;
        }

        function textValue(row, key) {
            return String(row.dataset[key] || '').toLowerCase();
        }

        function currentSortLabel() {
            if (!sortSelect) {
                return 'records';
            }

            const selectedOption = sortSelect.options[sortSelect.selectedIndex];
            return selectedOption ? selectedOption.textContent.trim() : 'records';
        }

        function shouldIncludeRow(row) {
            const selected = state.sort || 'all';
            const orderStatus = textValue(row, 'orderStatus');
            const paymentStatus = textValue(row, 'paymentStatus');

            if (selected === 'all' || selected === 'newest' || selected === 'client_asc' || selected === 'service_asc' || selected === 'order_no_asc') {
                return true;
            }

            if (selected === 'due_priority') {
                return numberValue(row, 'dueRank', 9) === 0;
            }

            if (selected === 'overdue') {
                return numberValue(row, 'dueRank', 9) === 1;
            }

            if (selected === 'closed') {
                return ['completed', 'approved', 'rejected', 'cancelled', 'closed'].includes(orderStatus);
            }

            if (selected === 'pending') {
                return ['submitted', 'submited', 'pending', 'pending_review'].includes(orderStatus);
            }

            if (selected === 'pending_clarification') {
                return ['pending_clarification', 'clarification'].includes(orderStatus);
            }

            if (selected === 'approved') {
                return orderStatus === 'approved';
            }

            if (selected === 'waiting_for_payment') {
                return ['pending', 'pending_review', 'waiting_for_payment', 'unpaid', 'partial', 'failed', 'overdue'].includes(paymentStatus);
            }

            if (selected === 'documents_missing') {
                return String(row.dataset.documentsMissing || '0') === '1';
            }

            if (selected === 'work_in_progress') {
                return orderStatus === 'work_in_progress';
            }

            if (selected === 'completed') {
                return orderStatus === 'completed';
            }

            return true;
        }

        function compareRows(a, b) {
            const sort = state.sort;

            if (sort === 'overdue') {
                return (numberValue(a, 'dueDistance', 999999) - numberValue(b, 'dueDistance', 999999))
                    || (numberValue(a, 'dueTimestamp', 9999999999999) - numberValue(b, 'dueTimestamp', 9999999999999))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'due_date_asc') {
                return (numberValue(a, 'dueTimestamp', 9999999999999) - numberValue(b, 'dueTimestamp', 9999999999999))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'due_date_desc') {
                return (numberValue(b, 'dueTimestamp', 0) - numberValue(a, 'dueTimestamp', 0))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'newest') {
                return (numberValue(b, 'createdTimestamp', 0) - numberValue(a, 'createdTimestamp', 0))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'oldest') {
                return (numberValue(a, 'createdTimestamp', 0) - numberValue(b, 'createdTimestamp', 0))
                    || (numberValue(a, 'orderId', 0) - numberValue(b, 'orderId', 0));
            }

            if (sort === 'client_asc') {
                return textValue(a, 'clientSort').localeCompare(textValue(b, 'clientSort'))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'service_asc') {
                return textValue(a, 'serviceSort').localeCompare(textValue(b, 'serviceSort'))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            if (sort === 'order_no_asc') {
                return textValue(a, 'orderNoSort').localeCompare(textValue(b, 'orderNoSort'))
                    || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
            }

            return (numberValue(a, 'dueRank', 9) - numberValue(b, 'dueRank', 9))
                || (numberValue(a, 'dueDistance', 999999) - numberValue(b, 'dueDistance', 999999))
                || (numberValue(a, 'dueTimestamp', 9999999999999) - numberValue(b, 'dueTimestamp', 9999999999999))
                || (numberValue(b, 'orderId', 0) - numberValue(a, 'orderId', 0));
        }

        function readPerPage() {
            const selected = pageSizeSelect ? pageSizeSelect.value : '20';
            if (selected === 'all') {
                return rows.length;
            }

            if (selected === 'custom') {
                const customValue = customPageSize ? Number(customPageSize.value) : 20;
                return Math.max(1, Math.min(500, Number.isFinite(customValue) ? Math.floor(customValue) : 20));
            }

            const numericValue = Number(selected);
            return Math.max(1, Number.isFinite(numericValue) ? Math.floor(numericValue) : 20);
        }

        function renderPage() {
            const sortedRows = rows.slice().filter(shouldIncludeRow).sort(compareRows);
            const perPage = readPerPage();
            const totalPages = Math.max(1, Math.ceil(sortedRows.length / perPage));
            const activeLabel = currentSortLabel();

            rows.forEach(function (row) {
                tbody.appendChild(row);
                row.classList.add('hidden');
            });

            emptyFilterRow.classList.add('hidden');

            if (sortedRows.length === 0) {
                tbody.appendChild(emptyFilterRow);
                emptyFilterRow.classList.remove('hidden');

                if (pageInfo) {
                    pageInfo.textContent = 'No records for ' + activeLabel;
                }

                if (pageNumber) {
                    pageNumber.textContent = '0 / 0';
                }

                if (prevButton) prevButton.disabled = true;
                if (nextButton) nextButton.disabled = true;
                return;
            }

            if (state.page > totalPages) state.page = totalPages;
            if (state.page < 1) state.page = 1;

            const startIndex = (state.page - 1) * perPage;
            const endIndex = Math.min(startIndex + perPage, sortedRows.length);

            sortedRows.slice(startIndex, endIndex).forEach(function (row, visibleIndex) {
                tbody.appendChild(row);
                row.classList.remove('hidden');
                const serialCell = row.querySelector('[data-kanban-row-number]');
                if (serialCell) {
                    serialCell.textContent = String(startIndex + visibleIndex + 1);
                }
            });

            tbody.appendChild(emptyFilterRow);

            if (pageInfo) {
                pageInfo.textContent = 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + sortedRows.length + ' records';
            }

            if (pageNumber) {
                pageNumber.textContent = state.page + ' / ' + totalPages;
            }

            if (prevButton) prevButton.disabled = state.page <= 1;
            if (nextButton) nextButton.disabled = state.page >= totalPages;
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                state.sort = sortSelect.value || 'all';
                state.page = 1;
                renderPage();
            });
        }

        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function () {
                const isCustom = pageSizeSelect.value === 'custom';
                if (customPageSize) {
                    customPageSize.classList.toggle('hidden', !isCustom);
                    if (isCustom) customPageSize.focus();
                }
                state.page = 1;
                renderPage();
            });
        }

        if (customPageSize) {
            customPageSize.addEventListener('input', function () {
                state.page = 1;
                renderPage();
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                state.page -= 1;
                renderPage();
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                state.page += 1;
                renderPage();
            });
        }

        renderPage();
    }

    document.querySelectorAll('[data-excel-kanban-pager]').forEach(setupExcelKanbanPagination);

    document.querySelectorAll('[data-order-row]').forEach(function (row) {
        const openOrder = function () {
            const url = row.getAttribute('data-order-url');
            if (url) {
                window.location.assign(url);
            }
        };

        row.addEventListener('click', function (event) {
            const target = event.target;
            if (target && typeof target.closest === 'function' && target.closest('a,button,input,select,textarea,label,form,[role="button"]')) {
                return;
            }

            openOrder();
        });

        row.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            openOrder();
        });
    });

    orderKanbanTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateOrderKanbanTab(tab.dataset.orderKanbanTab || 'all');
        });
    });

    document.querySelectorAll('[data-order-kanban-jump]').forEach(function (button) {
        button.addEventListener('click', function () {
            const requestedTab = button.getAttribute('data-order-kanban-jump') || 'all';
            const hasRequestedTab = Array.from(orderKanbanTabs).some(function (tab) {
                return tab.dataset.orderKanbanTab === requestedTab;
            });
            activateOrderKanbanTab(hasRequestedTab ? requestedTab : 'all');

            const kanbanArea = document.querySelector('[aria-label="Order Kanban Status Tabs"]');
            if (kanbanArea) {
                kanbanArea.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        });
    });

});
</script>
