<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900"><?= e((string)($partner['name']??'Partner')) ?></h1>
        <p class="mt-2 text-sm text-slate-500"><?= e((string)($partner['email']??'')) ?> · <?= e((string)($partner['phone']??'')) ?></p>
    </div>
    <div class="grid gap-4 md:grid-cols-4">
        <?php foreach (['Total Orders'=>$stats['total_orders']??0,'Payable'=>'₹'.number_format((float)($stats['total_payable']??0),2),'Discount'=>'₹'.number_format((float)($stats['total_discount']??0),2),'Completed'=>$stats['completed_filings']??0] as $label=>$value): ?>
            <div class="rounded-[20px] border border-slate-200 bg-white p-4"><div class="text-xs font-bold text-slate-400"><?= e($label) ?></div><div class="mt-2 text-xl font-black"><?= e((string)$value) ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-900">Manage Profile</h2>
        <form method="post" action="<?= e(base_url('admin/partners/update')) ?>" class="mt-4 grid gap-3 md:grid-cols-4">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($partner['id']??0)) ?>">
            <input name="name" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" value="<?= e((string)($partner['name']??'')) ?>" placeholder="Name">
            <input name="email" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" value="<?= e((string)($partner['email']??'')) ?>" placeholder="Email">
            <input name="phone" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" value="<?= e((string)($partner['phone']??'')) ?>" placeholder="Phone">
            <select name="is_active" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"><option value="1" <?= (int)($partner['is_active']??1)===1?'selected':'' ?>>Active</option><option value="0" <?= (int)($partner['is_active']??1)===0?'selected':'' ?>>Inactive</option></select>
            <button class="h-11 rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white md:col-span-4" type="submit">Save Partner Profile</button>
        </form>
    </div>
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3 font-black">Partner Orders</div>
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-4 py-3">Order</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Payable</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Filing</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach (($orders??[]) as $row): ?><tr><td class="px-4 py-3 font-bold"><?= e((string)($row['order_no']??'')) ?></td><td class="px-4 py-3"><?= e((string)($row['service_title']??'-')) ?></td><td class="px-4 py-3">₹<?= e(number_format((float)($row['payable_amount']??0),2)) ?></td><td class="px-4 py-3"><?= e((string)($row['payment_status']??'')) ?></td><td class="px-4 py-3"><?= e((string)($row['filing_status']??'')) ?></td><td class="px-4 py-3 text-right"><a class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold" href="<?= e(base_url('admin/partner-orders/show?id='.(int)($row['id']??0))) ?>">Review</a></td></tr><?php endforeach; ?>
                <?php if (empty($orders)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No orders for this partner.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
