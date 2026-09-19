<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h1 class="text-2xl font-black text-slate-900">My Orders</h1><p class="mt-1 text-sm text-slate-500">Your partner service orders.</p></div>
            <a href="<?= e(base_url('partner/services')) ?>" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white">New Order</a>
        </div>
    </div>
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Filing Fee</th><th class="px-4 py-3">Coupon</th><th class="px-4 py-3">Payable</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach (($rows??[]) as $row): ?>
                    <?php $canContinuePayment = in_array(strtolower((string) ($row['payment_status'] ?? '')), ['pending', 'waiting_for_payment', 'unpaid'], true); ?>
                    <tr>
                        <td class="px-4 py-3 font-bold"><?= e((string)($row['order_no']??'')) ?></td>
                        <td class="px-4 py-3"><?= e((string)($row['service_title']??'-')) ?></td>
                        <td class="px-4 py-3">₹<?= e(number_format((float)($row['filing_fee']??0),2)) ?></td>
                        <td class="px-4 py-3"><?php if (!empty($row['coupon_code'])): ?><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700"><?= e((string)$row['coupon_code']) ?></span><?php else: ?><span class="text-slate-400">—</span><?php endif; ?></td>
                        <td class="px-4 py-3 font-bold">₹<?= e(number_format((float)($row['payable_amount']??0),2)) ?></td>
                        <td class="px-4 py-3 text-xs"><div>Order: <?= e((string)($row['order_status']??'')) ?></div><div>Payment: <?= e((string)($row['payment_status']??'')) ?></div><div>Filing: <?= e((string)($row['filing_status']??'')) ?></div></td>
                        <td class="px-4 py-3 text-right"><?php if ($canContinuePayment): ?><a class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white" href="<?= e(base_url('partner/orders/payment?order_id='.(int)($row['id']??0))) ?>">Pay</a><?php else: ?><a class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold" href="<?= e(base_url('partner/orders/show?id='.(int)($row['id']??0))) ?>">View</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
