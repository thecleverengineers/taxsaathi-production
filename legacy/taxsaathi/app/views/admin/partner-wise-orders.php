<?php
$partners = is_array($partners ?? null) ? $partners : [];
$search = (string) ($search ?? '');
?>

<section class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.22em] text-brand-700">Admin</p>
            <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-900">
                Partner-wise Orders
            </h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Select a partner to see every order submitted through the partner account and its current status.
            </p>
        </div>

        <form method="get" action="<?= e(base_url('admin/partner-wise-orders')) ?>" class="flex gap-2">
            <input
                type="search"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search partner"
                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none focus:border-brand-400 sm:w-64"
            >
            <button
                type="submit"
                class="rounded-xl bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-slate-700"
            >
                Search
            </button>
        </form>
    </div>

    <?php if ($partners === []): ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm font-semibold text-slate-500">
            No partner-submitted orders found.
        </div>
    <?php else: ?>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden border-b border-slate-200 bg-slate-50 px-5 py-4 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500 lg:grid lg:grid-cols-[1.6fr_0.7fr_0.8fr_0.7fr_1fr_1.2fr_30px] lg:items-center lg:gap-4">
                <div>Partner</div>
                <div>Total Orders</div>
                <div>Completed</div>
                <div>Active</div>
                <div>Payment Pending</div>
                <div>Last Order</div>
                <div></div>
            </div>

            <div class="divide-y divide-slate-100">
                <?php foreach ($partners as $partner): ?>
                    <?php $partnerId = (int) ($partner['partner_id'] ?? 0); ?>

                    <a
                        href="<?= e(base_url('admin/partner-wise-orders/show?partner_id=' . $partnerId)) ?>"
                        class="group block px-5 py-5 transition hover:bg-slate-50"
                    >
                        <div class="grid gap-4 lg:grid-cols-[1.6fr_0.7fr_0.8fr_0.7fr_1fr_1.2fr_30px] lg:items-center lg:gap-4">

                            <div class="min-w-0">
                                <h2 class="truncate text-base font- text-slate-600 group-hover:text-brand-400" style="font-size: 14px;">
                                    <?= e((string) ($partner['partner_name'] ?? ('Partner #' . $partnerId))) ?>
                                </h2>
                                <p class="mt-1 text-xs font-bold uppercase tracking-[0.14em] text-slate-400">
                                    Partner ID: <?= e((string) $partnerId) ?>
                                </p>
                            </div>

                            <div class="flex items-center justify-between lg:block">
                                <span class="text-xs font-bold text-slate-500 lg:hidden">Total Orders</span>
                                <span class="text-lg font-black text-slate-900">
                                    <?= e((string) ($partner['total_orders'] ?? 0)) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between lg:block">
                                <span class="text-xs font-bold text-slate-500 lg:hidden">Completed</span>
                                <span class="text-lg font-black text-emerald-600">
                                    <?= e((string) ($partner['completed_orders'] ?? 0)) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between lg:block">
                                <span class="text-xs font-bold text-slate-500 lg:hidden">Active</span>
                                <span class="text-lg font-black text-blue-600">
                                    <?= e((string) ($partner['active_orders'] ?? 0)) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between lg:block">
                                <span class="text-xs font-bold text-slate-500 lg:hidden">Payment Pending</span>
                                <span class="text-lg font-black text-amber-600">
                                    <?= e((string) ($partner['payment_pending_orders'] ?? 0)) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between lg:block">
                                <span class="text-xs font-bold text-slate-500 lg:hidden">Last Order</span>
                                <span class="text-sm font-semibold text-slate-600">
                                    <?= e((string) ($partner['last_order_at'] ?? '—')) ?>
                                </span>
                            </div>

                            <div class="hidden text-xl text-brand-700 transition group-hover:translate-x-1 lg:block">
                                →
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>
</section>