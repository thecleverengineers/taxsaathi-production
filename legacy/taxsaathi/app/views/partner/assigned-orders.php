<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? array_values($rows) : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filters = is_array($filters ?? null) ? $filters : [];

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

    return $ts ? date('d M Y', $ts) : $value;
};

$dueText = static function (array $row): string {
    $status = strtolower(trim((string) ($row['status'] ?? '')));

    if (in_array($status, ['completed', 'rejected', 'cancelled'], true)) {
        return 'Closed';
    }

    $tat = (int) ($row['service_turnaround_days'] ?? 0);
    if ($tat <= 0) {
        return 'No TAT';
    }

    $days = $row['order_due_days_left'] ?? null;

    if (!is_numeric($days)) {
        return $tat . 'd TAT';
    }

    $days = (int) $days;

    if ($days < 0) {
        return abs($days) . 'd overdue';
    }

    if ($days === 0) {
        return 'Due today';
    }

    return $days . 'd left';
};

$dueTone = static function (array $row): string {
    $status = strtolower(trim((string) ($row['status'] ?? '')));

    if (in_array($status, ['completed', 'rejected', 'cancelled'], true)) {
        return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    }

    $days = $row['order_due_days_left'] ?? null;

    if (!is_numeric($days)) {
        return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
    }

    $days = (int) $days;

    if ($days < 0) {
        return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
    }

    if ($days === 0) {
        return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    }

    if ($days <= 2) {
        return 'bg-orange-50 text-orange-700 ring-1 ring-orange-200';
    }

    return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
};
?>

<section class="space-y-6">
    <div class="relative overflow-hidden rounded-[28px] border border-slate-200 bg-slate-950 p-5 shadow-sm sm:p-7">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-indigo-200">Partner Workflow</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-white sm:text-3xl">
                    Assigned Orders
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    Manage only the client orders assigned to your partner account. View documents, request re-upload, update work status and upload deliverables.
                </p>
            </div>

            <a href="<?= e(base_url('partner/dashboard')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white transition hover:bg-white/15">
                ← Partner Dashboard
            </a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <?php
        $cards = [
            'Total' => $stats['total'] ?? 0,
            'Pending' => $stats['pending'] ?? 0,
            'Clarification' => $stats['clarification'] ?? 0,
            'In Progress' => $stats['work_in_progress'] ?? 0,
            'Completed' => $stats['completed'] ?? 0,
        ];
        ?>

        <?php foreach ($cards as $label => $value): ?>
            <div class="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400"><?= e($label) ?></div>
                <div class="mt-2 text-3xl font-black text-slate-950"><?= e((string) $value) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm">
        <form method="get" action="<?= e(base_url('partner/assigned-orders')) ?>" class="grid gap-3 lg:grid-cols-[1fr_220px_220px_auto]">
            <input
                type="search"
                name="q"
                value="<?= e((string) ($filters['q'] ?? '')) ?>"
                placeholder="Search order no, client, PAN, mobile, service..."
                class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
            >

            <select name="status" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                <?php
                $statusOptions = [
                    '' => 'All Status',
                    'submitted' => 'Pending',
                    'pending_review' => 'Pending Review',
                    'clarification' => 'Pending Clarification',
                    'approved' => 'Approved',
                    'work_in_progress' => 'Work In Progress',
                    'completed' => 'Completed',
                    'rejected' => 'Rejected',
                ];
                ?>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="payment_status" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100">
                <?php
                $paymentOptions = [
                    '' => 'All Payment',
                    'pending' => 'Pending',
                    'pending_review' => 'Pending Review',
                    'verified' => 'Verified',
                    'paid' => 'Paid',
                    'partial' => 'Partial',
                    'unpaid' => 'Unpaid',
                    'failed' => 'Failed',
                ];
                ?>
                <?php foreach ($paymentOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) ($filters['payment_status'] ?? '') === (string) $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="h-12 rounded-2xl bg-slate-950 px-6 text-sm font-black text-white transition hover:bg-slate-800" type="submit">
                Filter
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-600">Assigned Order List</p>
            <h2 class="mt-1 text-lg font-black text-slate-950">Partner Work Queue</h2>
        </div>

        <?php if ($rows === []): ?>
            <div class="px-5 py-16 text-center">
                <div class="text-base font-black text-slate-900">No assigned orders found</div>
                <p class="mt-2 text-sm text-slate-500">Orders assigned to your partner account will appear here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                    <thead class="bg-slate-950 text-[11px] font-black uppercase tracking-[0.12em] text-white">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-center">Docs</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rows as $row): ?>
                            <tr class="transition hover:bg-indigo-50/40">
                                <td class="px-4 py-3">
                                    <a href="<?= e(base_url('partner/assigned-orders-show?id=' . (int) ($row['id'] ?? 0))) ?>" class="font-black text-slate-950 hover:text-indigo-700">
                                        <?= e((string) ($row['order_no'] ?? '-')) ?>
                                    </a>
                                    <div class="mt-1 text-xs font-semibold text-slate-400">
                                        <?= e($dateText($row['created_at'] ?? '')) ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-800"><?= e((string) ($row['client_name'] ?? '-')) ?></div>
                                    <div class="mt-1 text-xs font-semibold text-slate-400"><?= e((string) ($row['client_phone'] ?? '-')) ?></div>
                                </td>

                                <td class="px-4 py-3 font-semibold text-slate-700">
                                    <?= e((string) ($row['service_title'] ?? '-')) ?>
                                </td>

                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-700"><?= e($dateText($row['order_due_date'] ?? '')) ?></div>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-black <?= e($dueTone($row)) ?>">
                                        <?= e($dueText($row)) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone((string) ($row['status'] ?? ''))) ?>">
                                        <?= e($statusLabel((string) ($row['status'] ?? ''))) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black <?= e($statusTone((string) ($row['payment_status'] ?? ''))) ?>">
                                        <?= e($statusLabel((string) ($row['payment_status'] ?? ''))) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-right font-black text-slate-900">
                                    <?= e($money($row['fee_amount'] ?? 0)) ?>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">
                                        C: <?= e((string) ($row['client_documents_count'] ?? 0)) ?>
                                        · O: <?= e((string) ($row['output_documents_count'] ?? 0)) ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-center">
                                    <a href="<?= e(base_url('partner/assigned-orders-show?id=' . (int) ($row['id'] ?? 0))) ?>" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-slate-800">
                                        Manage
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