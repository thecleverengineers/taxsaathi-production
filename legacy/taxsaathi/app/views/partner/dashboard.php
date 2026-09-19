<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Partner Dashboard</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900">Place service orders with coupon discounts</h1>
        <p class="mt-2 text-sm text-slate-500">Select service, apply coupon, upload documents, place order, then pay manually through UPI.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-4">
        <?php foreach (['Total Orders'=>$stats['orders']??0,'Waiting for Payment'=>$stats['waiting_for_payment']??0,'Payment Review'=>$stats['pending_review']??0,'Completed'=>$stats['completed']??0] as $label=>$value): ?>
            <div class="rounded-[20px] border border-slate-200 bg-white p-4">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400"><?= e($label) ?></div>
                <div class="mt-2 text-2xl font-black text-slate-900"><?= e((string)$value) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        <a href="<?= e(base_url('partner/services')) ?>" class="rounded-2xl bg-slate-900 px-5 py-4 text-sm font-bold text-white">Select Service</a>
        <a href="<?= e(base_url('partner/orders')) ?>" class="rounded-2xl bg-brand-700 px-5 py-4 text-sm font-bold text-white">My Orders</a>
    </div>
</section>
