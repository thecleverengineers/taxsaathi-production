<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$exportUrl = (string) ($exportUrl ?? base_url('admin/statements/export'));

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$statusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed', 'approved', 'paid', 'verified', 'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending', 'pending_review', 'processing', 'work_in_progress', 'waiting_for_payment', 'payment_submitted', 'payment_under_review', 'documents_submitted', 'documents_review', 'pending_payment' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'rejected', 'failed', 'cancelled', 'unpaid', 'overdue' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    };
};

$label = static function (string $value): string {
    return ucwords(str_replace('_', ' ', $value));
};

$sourceOptions = [
    'all' => 'All Tables',
    'orders' => 'Client Orders',
    'order_documents' => 'Client Documents',
    'partner_orders' => 'Partner Orders',
    'partner_order_documents' => 'Partner Documents',
];

$periodOptions = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    'custom' => 'Custom',
];
?>

<section class="grid gap-5">
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Single Statements</p>
                <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-900">Orders & Documents Statement</h1>
                <p class="mt-2 text-sm text-slate-500">
                    Combined statement for client orders, client documents, partner orders and partner documents.
                </p>
            </div>

            <a
                href="<?= e($exportUrl) ?>"
                class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-bold text-white shadow-[0_10px_24px_rgba(5,150,105,0.20)] transition hover:-translate-y-[1px] hover:bg-emerald-700"
            >
                Export Excel
            </a>
        </div>

        <form method="get" action="<?= e(base_url('admin/statements')) ?>" class="grid gap-3 px-5 py-5 lg:grid-cols-7">
            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Filter</span>
                <select name="period" id="statementPeriod" class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <?php foreach ($periodOptions as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['period'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= e($text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Date</span>
                <input
                    type="date"
                    name="date"
                    value="<?= e((string) ($filters['date'] ?? date('Y-m-d'))) ?>"
                    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                >
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">From</span>
                <input
                    type="date"
                    name="from_date"
                    value="<?= e((string) ($filters['from_date'] ?? date('Y-m-d'))) ?>"
                    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                >
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">To</span>
                <input
                    type="date"
                    name="to_date"
                    value="<?= e((string) ($filters['to_date'] ?? date('Y-m-d'))) ?>"
                    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                >
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Table</span>
                <select name="source" class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <?php foreach ($sourceOptions as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['source'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= e($text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Status</span>
                <input
                    type="text"
                    name="status"
                    value="<?= e((string) ($filters['status'] ?? '')) ?>"
                    placeholder="paid / completed"
                    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                >
            </label>

            <label class="grid gap-1.5">
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Search</span>
                <input
                    type="text"
                    name="q"
                    value="<?= e((string) ($filters['q'] ?? '')) ?>"
                    placeholder="Order, service, name"
                    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700"
                >
            </label>

            <div class="lg:col-span-7 flex flex-wrap items-center justify-between gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-600">
                    <?= e((string) ($filters['period_label'] ?? 'Daily')) ?>:
                    <?= e((string) ($filters['from_date'] ?? '')) ?>
                    to
                    <?= e((string) ($filters['to_date'] ?? '')) ?>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="<?= e(base_url('admin/statements')) ?>" class="inline-flex h-10 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700">
                        Reset
                    </a>
                    <button class="inline-flex h-10 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white" type="submit">
                        Apply Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Total Rows</div>
            <div class="mt-2 text-2xl font-black text-slate-900"><?= e((string) ($summary['total_rows'] ?? 0)) ?></div>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Client Orders</div>
            <div class="mt-2 text-2xl font-black text-slate-900"><?= e((string) ($summary['client_orders'] ?? 0)) ?></div>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Partner Orders</div>
            <div class="mt-2 text-2xl font-black text-slate-900"><?= e((string) ($summary['partner_orders'] ?? 0)) ?></div>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Documents</div>
            <div class="mt-2 text-2xl font-black text-slate-900">
                <?= e((string) (($summary['client_documents'] ?? 0) + ($summary['partner_documents'] ?? 0))) ?>
            </div>
        </div>

        <div class="rounded-[20px] border border-slate-200 bg-white p-4 shadow-sm md:col-span-2">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Gross Amount</div>
            <div class="mt-2 text-2xl font-black text-slate-900"><?= e($money($summary['gross_amount'] ?? 0)) ?></div>
        </div>

        <div class="rounded-[20px] border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Discount</div>
            <div class="mt-2 text-2xl font-black text-emerald-700"><?= e($money($summary['discount_amount'] ?? 0)) ?></div>
        </div>

        <div class="rounded-[20px] border border-slate-900 bg-slate-900 p-4 text-white shadow-sm">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-300">Payable</div>
            <div class="mt-2 text-2xl font-black"><?= e($money($summary['payable_amount'] ?? 0)) ?></div>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.15em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Record</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Document</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $row): ?>
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-black text-slate-900"><?= e((string) ($row['statement_type'] ?? '')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['source_table'] ?? '')) ?></div>
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900"><?= e((string) ($row['record_no'] ?? '')) ?></div>
                                <div class="mt-1 text-xs text-slate-500">ID: <?= e((string) ($row['record_id'] ?? '')) ?></div>
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800"><?= e((string) ($row['party_name'] ?? '-')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['party_email'] ?? '')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['party_phone'] ?? '')) ?></div>
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                <?= e((string) ($row['service_title'] ?? '-')) ?>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-bold text-slate-900"><?= e($money($row['gross_amount'] ?? 0)) ?></div>
                                <?php if ((float) ($row['discount_amount'] ?? 0) > 0): ?>
                                    <div class="mt-1 text-xs font-bold text-emerald-700">
                                        - <?= e($money($row['discount_amount'] ?? 0)) ?>
                                    </div>
                                    <div class="mt-1 text-xs font-bold text-slate-600">
                                        Net: <?= e($money($row['payable_amount'] ?? 0)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="px-4 py-3">
                                <div class="grid gap-1">
                                    <?php foreach (['order_status' => 'Order', 'payment_status' => 'Payment', 'filing_status' => 'Filing'] as $key => $title): ?>
                                        <?php if (!empty($row[$key])): ?>
                                            <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-bold <?= e($statusTone((string) $row[$key])) ?>">
                                                <?= e($title . ': ' . $label((string) $row[$key])) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <?php if (!empty($row['document_name']) || !empty($row['document_title'])): ?>
                                    <div class="font-semibold text-slate-800"><?= e((string) ($row['document_title'] ?? 'Document')) ?></div>
                                    <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['document_name'] ?? '')) ?></div>
                                    <div class="mt-1 text-xs text-slate-400"><?= e((string) ($row['document_source'] ?? '')) ?></div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="max-w-[320px] px-4 py-3 text-xs leading-5 text-slate-500">
                                <?= e((string) ($row['reference_text'] ?? '')) ?>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">
                                <?= e((string) ($row['created_at'] ?? '')) ?>
                                <?php if (!empty($row['updated_at'])): ?>
                                    <div class="mt-1 text-slate-400">Updated: <?= e((string) $row['updated_at']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-slate-500">
                                No statement data found for the selected filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($rows) >= 1000): ?>
            <div class="border-t border-amber-100 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                Showing latest 1000 rows. Use Export Excel for full filtered data.
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const period = document.getElementById('statementPeriod');
    if (!period) return;

    const fromInput = document.querySelector('input[name="from_date"]');
    const toInput = document.querySelector('input[name="to_date"]');

    function syncCustomFields() {
        const isCustom = period.value === 'custom';
        [fromInput, toInput].forEach(function (input) {
            if (!input) return;
            input.closest('label').style.opacity = isCustom ? '1' : '0.55';
            input.disabled = !isCustom;
        });
    }

    period.addEventListener('change', syncCustomFields);
    syncCustomFields();
});
</script>
