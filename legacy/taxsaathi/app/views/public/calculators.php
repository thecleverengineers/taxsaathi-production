<?php
$type   = (string) ($type ?? 'income_tax');
$result = is_array($result ?? null) ? $result : [];
?>

<section class="relative overflow-hidden px-4 pb-8 pt-8 sm:px-6 lg:px-10">
    <div class="absolute inset-x-0 top-0 -z-10 h-[360px] bg-gradient-to-b from-brand-50/70 via-white/50 to-transparent"></div>
    <div class="absolute -left-16 top-0 -z-10 h-64 w-64 rounded-full bg-brand-200/30 blur-3xl"></div>
    <div class="absolute -right-16 top-12 -z-10 h-64 w-64 rounded-full bg-accent-200/30 blur-3xl"></div>

    <div class="overflow-hidden rounded-[34px] border border-white/70 bg-gradient-to-br from-[#f7fbff] via-white to-[#fff8f1] shadow-[0_24px_70px_rgba(15,23,42,0.08)]">
        <div class="grid gap-8 px-6 py-10 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:px-12 lg:py-14">
            <div class="relative z-10">
                <span class="inline-flex items-center rounded-full border border-brand-100 bg-white/80 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-brand-700 shadow-sm">
                    Tax Tools
                </span>

                <h1 class="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-[-0.05em] text-slate-900 sm:text-5xl">
                    Dynamic tax calculators
                </h1>

                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                    Income tax slabs and related settings are managed from the admin panel.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <span class="rounded-full border border-brand-100 bg-white/85 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-700 shadow-sm">
                        Admin Managed
                    </span>
                    <span class="rounded-full border border-accent-200 bg-accent-100/50 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-[#b86a1d] shadow-sm">
                        Accurate Inputs
                    </span>
                    <span class="rounded-full border border-brand-100 bg-white/85 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-brand-700 shadow-sm">
                        Instant Results
                    </span>
                </div>
            </div>

            <div class="relative z-10 grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-brand-700">3</div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">Core calculators</div>
                </div>

                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-[#b86a1d]">Dynamic</div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">Tax settings support</div>
                </div>

                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-brand-700">Fast</div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">Live calculation output</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="px-4 pb-12 pt-4 sm:px-6 lg:px-10">
    <div class="grid gap-6 xl:grid-cols-[1.04fr_0.96fr]">
        <div class="rounded-[32px] border border-white/80 bg-white/90 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] sm:p-8">
            <div class="flex flex-wrap gap-3">
                <a
                    class="<?= $type === 'income_tax' ? 'bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] text-white shadow-[0_18px_36px_rgba(56,82,180,0.18)]' : 'border border-accent-200 bg-accent-100/40 text-[#b86a1d]' ?> inline-flex items-center justify-center rounded-full px-5 py-3 text-sm font-extrabold transition hover:-translate-y-0.5"
                    href="<?= e(base_url('tax-calculators')) ?>"
                >
                    Income Tax
                </a>

                <a
                    class="inline-flex items-center justify-center rounded-full border border-accent-200 bg-accent-100/40 px-5 py-3 text-sm font-extrabold text-[#b86a1d] transition hover:-translate-y-0.5"
                    href="<?= e(base_url('tax-calculators')) ?>#gst"
                >
                    GST
                </a>

                <a
                    class="inline-flex items-center justify-center rounded-full border border-accent-200 bg-accent-100/40 px-5 py-3 text-sm font-extrabold text-[#b86a1d] transition hover:-translate-y-0.5"
                    href="<?= e(base_url('tax-calculators')) ?>#hra"
                >
                    HRA
                </a>
            </div>

            <div class="mt-8 rounded-[28px] border border-brand-100/60 bg-gradient-to-br from-brand-50/50 to-white p-5 sm:p-6">
                <h3 class="text-2xl font-black tracking-[-0.03em] text-slate-900">Income Tax</h3>
                <form method="post" action="<?= e(base_url('tax-calculators')) ?>" class="mt-6 grid gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="calc_type" value="income_tax">

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="text"
                        name="financial_year"
                        value="2025-26"
                        placeholder="Financial Year"
                    >

                    <select
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                        name="regime"
                    >
                        <option value="new">New Regime</option>
                        <option value="old">Old Regime</option>
                    </select>

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="gross_income"
                        placeholder="Gross Income"
                    >

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="deductions"
                        placeholder="Eligible Deductions"
                    >

                    <button
                        class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5"
                        type="submit"
                    >
                        Calculate Income Tax
                    </button>
                </form>
            </div>

            <div id="gst" class="mt-6 rounded-[28px] border border-accent-200/60 bg-gradient-to-br from-accent-100/20 to-white p-5 sm:p-6">
                <h3 class="text-2xl font-black tracking-[-0.03em] text-slate-900">GST</h3>
                <form method="post" action="<?= e(base_url('tax-calculators')) ?>" class="mt-6 grid gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="calc_type" value="gst">

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="amount"
                        placeholder="Amount"
                    >

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="gst_rate"
                        value="18"
                        placeholder="GST Rate %"
                    >

                    <select
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                        name="gst_mode"
                    >
                        <option value="exclusive">Exclusive</option>
                        <option value="inclusive">Inclusive</option>
                    </select>

                    <button
                        class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#F08D39] to-[#F3BE7A] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(240,141,57,0.22)] transition hover:-translate-y-0.5"
                        type="submit"
                    >
                        Calculate GST
                    </button>
                </form>
            </div>

            <div id="hra" class="mt-6 rounded-[28px] border border-brand-100/60 bg-gradient-to-br from-brand-50/40 to-white p-5 sm:p-6">
                <h3 class="text-2xl font-black tracking-[-0.03em] text-slate-900">HRA</h3>
                <form method="post" action="<?= e(base_url('tax-calculators')) ?>" class="mt-6 grid gap-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="calc_type" value="hra">

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="basic_salary"
                        placeholder="Basic Salary"
                    >

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="hra_received"
                        placeholder="HRA Received"
                    >

                    <input
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                        type="number"
                        step="0.01"
                        name="rent_paid"
                        placeholder="Rent Paid"
                    >

                    <select
                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                        name="city_type"
                    >
                        <option value="metro">Metro</option>
                        <option value="non_metro">Non-Metro</option>
                    </select>

                    <button
                        class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5"
                        type="submit"
                    >
                        Calculate HRA
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-[32px] bg-gradient-to-br from-[#3852B4] via-[#5E7AC4] to-[#7f96d4] p-[1px] shadow-[0_24px_60px_rgba(56,82,180,0.20)]">
            <div class="h-full rounded-[31px] bg-white/95 p-6 sm:p-8">
                <div class="max-w-md">
                    <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d]">
                        Result Panel
                    </span>
                    <h3 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900">Calculation Result</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        Run any calculator to see a clean breakdown of the output here.
                    </p>
                </div>

                <div class="mt-8 space-y-3">
                    <?php if (!empty($result)): ?>
                        <?php foreach ($result as $key => $value): ?>
                            <?php if (in_array($key, ['title', 'type'], true)) continue; ?>
                            <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                                <span class="text-sm font-bold tracking-[-0.01em] text-slate-600">
                                    <?= e(ucwords(str_replace('_', ' ', (string) $key))) ?>
                                </span>
                                <strong class="text-sm font-black text-brand-700 sm:text-base">
                                    <?= is_numeric($value) ? e(format_money((float) $value)) : e((string) $value) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-[24px] border border-dashed border-brand-200 bg-brand-50/30 px-5 py-8 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-[18px] bg-brand-100 text-brand-700 shadow-sm">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m6 6V7m-9 12h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="mt-4 text-sm font-semibold text-slate-500">
                                Run any calculator to see the result here.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>