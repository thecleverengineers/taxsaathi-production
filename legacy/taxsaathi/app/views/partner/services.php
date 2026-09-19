<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Select Service</h1>
        <p class="mt-2 text-sm text-slate-500">Partners place orders directly like normal clients. No client assignment and no subscription.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-3">
        <?php foreach (($services ?? []) as $service): ?>
            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-slate-900"><?= e((string)($service['title'] ?? 'Service')) ?></h2>
                <p class="mt-2 text-sm text-slate-500"><?= e((string)($service['excerpt'] ?? '')) ?></p>
                <div class="mt-4 text-2xl font-black text-slate-900">₹<?= e(number_format((float)($service['filing_fee'] ?? 0),2)) ?></div>
                <a href="<?= e(base_url('partner/orders/create?service_id=' . (int)($service['id'] ?? 0))) ?>" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 text-sm font-bold text-white">Place Order</a>
            </div>
        <?php endforeach; ?>
        <?php if (empty($services)): ?><div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">No active services found.</div><?php endif; ?>
    </div>
</section>
