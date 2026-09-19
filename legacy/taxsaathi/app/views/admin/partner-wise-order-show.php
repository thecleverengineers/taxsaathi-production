<?php
$partner = is_array($partner ?? null) ? $partner : [];
$orders = is_array($orders ?? null) ? $orders : [];
$partnerId = (int) ($partner['partner_id'] ?? 0);

$statusClass = static function (string $status): string {
    $value = strtolower(trim($status));

    if (in_array($value, ['completed', 'delivered', 'approved'], true)) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (in_array($value, ['rejected', 'cancelled', 'failed'], true)) {
        return 'bg-rose-50 text-rose-700';
    }

    if (in_array($value, ['in_progress', 'work_in_progress'], true)) {
        return 'bg-blue-50 text-blue-700';
    }

    return 'bg-amber-50 text-amber-700';
};
?>

<section class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="<?= e(base_url('admin/partner-wise-orders')) ?>" class="text-sm font-bold text-brand-700">← All Partners</a>
            <h1 class="mt-3 text-3xl font-black tracking-[-0.04em] text-slate-900">
                <?= e((string) ($partner['partner_name'] ?? ('Partner #' . $partnerId))) ?>
            </h1>
            <p class="mt-2 text-sm text-slate-500">All orders submitted by Partner ID <?= e((string) $partnerId) ?></p>
        </div>

        <a href="<?= e(base_url('admin/orders?partner_id=' . $partnerId)) ?>" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">
            Open in Orders
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="text-2xl font-black text-slate-900"><?= e((string) ($partner['total_orders'] ?? 0)) ?></div>
            <div class="mt-1 text-sm font-semibold text-slate-500">Total orders</div>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
            <div class="text-2xl font-black text-emerald-700"><?= e((string) ($partner['completed_orders'] ?? 0)) ?></div>
            <div class="mt-1 text-sm font-semibold text-emerald-700">Completed</div>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
            <div class="text-2xl font-black text-amber-700"><?= e((string) ($partner['payment_pending_orders'] ?? 0)) ?></div>
            <div class="mt-1 text-sm font-semibold text-amber-700">Payment pending</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-black text-slate-900">Partner Orders</h2>
        </div>

        <?php if ($orders === []): ?>
            <div class="px-6 py-12 text-center text-sm font-semibold text-slate-500">No orders found for this partner.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-[0.14em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Order</th>
                            <th class="px-5 py-4">Customer / Service</th>
                            <th class="px-5 py-4">Amount</th>
                            <th class="px-5 py-4">Workflow status</th>
                            <th class="px-5 py-4">Payment</th>
                            <th class="px-5 py-4">Created</th>
                            <th class="px-5 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($orders as $order): ?>
                            <?php
                                $orderId = (int) ($order['id'] ?? 0);
                                $status = (string) ($order['status'] ?? 'pending');
                                $paymentStatus = (string) ($order['payment_status'] ?? 'pending');
                            ?>
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="font-black text-brand-700"><?= e((string) ($order['order_no'] ?? ('#' . $orderId))) ?></div>
                                    <div class="mt-1 text-xs font-semibold text-slate-400">ID <?= e((string) $orderId) ?></div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-900"><?= e((string) ($order['customer_name'] ?? 'Customer details unavailable')) ?></div>
                                    <div class="mt-1 text-xs font-semibold text-slate-500"><?= e((string) ($order['service_title'] ?? 'Service')) ?></div>
                                    <?php if (!empty($order['customer_mobile'])): ?>
                                        <div class="mt-1 text-xs text-slate-400"><?= e((string) $order['customer_mobile']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-black text-slate-900">
                                    <?= e(function_exists('format_money') ? format_money((float) ($order['fee_amount'] ?? 0)) : number_format((float) ($order['fee_amount'] ?? 0), 2)) ?>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-black <?= e($statusClass($status)) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $status))) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-black <?= e($statusClass($paymentStatus)) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $paymentStatus))) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs font-semibold text-slate-500">
                                    <?= e((string) ($order['created_at'] ?? '—')) ?>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="<?= e(base_url('admin/orders/show?id=' . $orderId)) ?>" class="font-bold text-brand-700 hover:underline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section> 