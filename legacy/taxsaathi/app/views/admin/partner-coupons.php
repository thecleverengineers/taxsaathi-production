<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Partner Coupons</h1><p class="mt-2 text-sm text-slate-500">Create flat or percentage coupon codes that partners can apply while placing orders.</p>
    </div>
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-900">Generate Coupon</h2>
        <form method="post" action="<?= e(base_url('admin/partner-coupons/store')) ?>" class="mt-4 grid gap-3 md:grid-cols-4">
            <?= csrf_field() ?>
            <input name="code" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm uppercase" placeholder="Coupon Code" required>
            <input name="title" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Title">
            <select name="service_id" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"><option value="">All Services</option><?php foreach (($services??[]) as $service): ?><option value="<?= e((string)($service['id']??0)) ?>"><?= e((string)($service['title']??'Service')) ?></option><?php endforeach; ?></select>
            <select name="discount_type" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"><option value="percent">Percentage</option><option value="flat">Flat</option></select>
            <input name="discount_value" type="number" step="0.01" min="0" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Discount Value" required>
            <input name="max_discount_amount" type="number" step="0.01" min="0" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Max Discount optional">
            <input name="usage_limit" type="number" min="0" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Total Usage Limit">
            <input name="partner_usage_limit" type="number" min="0" value="1" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Per Partner Limit">
            <input name="starts_at" type="date" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
            <input name="expires_at" type="date" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm">
            <textarea name="description" class="min-h-[70px] rounded-2xl border border-slate-200 px-3 py-2 text-sm md:col-span-2" placeholder="Description"></textarea>
            <label class="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 px-3 text-sm font-bold"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="h-11 rounded-2xl bg-slate-900 text-sm font-bold text-white md:col-span-3" type="submit">Create Coupon</button>
        </form>
    </div>
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-4 py-3">Coupon</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Validity</th><th class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
            <?php foreach (($rows??[]) as $row): ?><tr><td class="px-4 py-3"><div class="font-black"><?= e((string)($row['code']??'')) ?></div><div class="text-xs text-slate-500"><?= e((string)($row['title']??'')) ?></div></td><td class="px-4 py-3"><?= e((string)($row['service_title']??'All Services')) ?></td><td class="px-4 py-3"><?= e((string)($row['discount_type']??'')) ?>: <?= e((string)($row['discount_value']??0)) ?></td><td class="px-4 py-3"><?= e((string)($row['used_count']??0)) ?> / <?= e(((int)($row['usage_limit']??0))>0?(string)$row['usage_limit']:'Unlimited') ?></td><td class="px-4 py-3 text-xs"><?= e((string)($row['starts_at']??'-')) ?> to <?= e((string)($row['expires_at']??'-')) ?></td><td class="px-4 py-3 text-right"><form method="post" action="<?= e(base_url('admin/partner-coupons/toggle')) ?>" class="inline-flex"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold" type="submit"><?= (int)($row['is_active']??0)===1?'Deactivate':'Activate' ?></button></form> <form method="post" action="<?= e(base_url('admin/partner-coupons/delete')) ?>" class="inline-flex" onsubmit="return confirm('Delete coupon? Used coupons will be deactivated instead.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string)($row['id']??0)) ?>"><button class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No coupons yet.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</section>
