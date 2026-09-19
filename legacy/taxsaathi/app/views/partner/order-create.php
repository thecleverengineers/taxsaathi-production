<?php
declare(strict_types=1);

$fee = (float) ($service['filing_fee'] ?? 0);
$couponDiscount = (float) ($couponResult['discount_amount'] ?? 0);
$payable = max(0, $fee - $couponDiscount);
$couponMessage = (string) ($couponResult['message'] ?? '');
$couponValid = (bool) ($couponResult['valid'] ?? false);
$requirements = is_array($requirements ?? null) ? $requirements : [];
?>

<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">
            <?= e((string) ($service['title'] ?? 'Service')) ?>
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            Apply coupon, choose a payment method, and upload required documents loaded from service_requirements.
        </p>
    </div>

    <form method="get" action="<?= e(base_url('partner/orders/create')) ?>" class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <input type="hidden" name="service_id" value="<?= e((string) ($service['id'] ?? 0)) ?>">

        <label class="grid gap-2">
            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Coupon Code</span>

            <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                <input
                    name="coupon_code"
                    value="<?= e((string) ($couponCode ?? '')) ?>"
                    class="h-11 rounded-2xl border border-slate-200 px-3 text-sm uppercase"
                    placeholder="Enter admin generated coupon"
                >

                <button class="h-11 rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white" type="submit">
                    Apply Coupon
                </button>
            </div>

            <?php if (($couponCode ?? '') !== ''): ?>
                <span class="text-sm font-bold <?= $couponValid ? 'text-emerald-700' : 'text-rose-700' ?>">
                    <?= e($couponMessage) ?>
                </span>
            <?php endif; ?>
        </label>
    </form>

    <form method="post" action="<?= e(base_url('partner/orders/store')) ?>" enctype="multipart/form-data" class="grid gap-5">
        <?= csrf_field() ?>

        <input type="hidden" name="service_id" value="<?= e((string) ($service['id'] ?? 0)) ?>">
        <input type="hidden" name="coupon_code" value="<?= e((string) ($couponCode ?? '')) ?>">

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-[20px] border border-slate-200 bg-white p-4">
                <div class="text-xs font-bold text-slate-400">Filing Fee</div>
                <div class="mt-2 text-xl font-black">₹<?= e(number_format($fee, 2)) ?></div>
            </div>

            <div class="rounded-[20px] border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-bold text-emerald-700">Coupon Discount</div>
                <div class="mt-2 text-xl font-black text-emerald-700">₹<?= e(number_format($couponDiscount, 2)) ?></div>
            </div>

            <div class="rounded-[20px] border border-slate-900 bg-slate-900 p-4 text-white">
                <div class="text-xs font-bold text-slate-300">Payable</div>
                <div class="mt-2 text-xl font-black">₹<?= e(number_format($payable, 2)) ?></div>
            </div>

            <div class="rounded-[20px] border border-slate-200 bg-white p-4">
                <div class="text-xs font-bold text-slate-400">Payment</div>
                <div class="mt-2 text-sm font-black">Choose a method below</div>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black text-slate-900">Order Details</h2>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="grid gap-2 md:col-span-2">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Payment Method</span>
                    <select
                        name="payment_method"
                        required
                        class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
                    >
                        <option value="upi">UPI / QR</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash_on_delivery">Cash on Delivery (Pay Later)</option>
                    </select>
                    <span class="text-xs leading-5 text-slate-500">Bank details or UPI instructions will be shown on the next step.</span>
                </label>

               <?php
$currentYear = (int) date('Y');
$currentMonth = (int) date('n');

// Indian financial year logic: Apr-Mar
$startYear = $currentMonth >= 4 ? $currentYear : $currentYear - 1;

$selectedFinancialYear = $startYear . '-' . date('y', strtotime(($startYear + 1) . '-01-01'));
?>

<select
    name="financial_year"
    class="h-11 rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
>
    <option value="">Select Financial Year</option>

    <?php for ($year = $startYear + 1; $year >= $startYear - 5; $year--): ?>
        <?php
            $fy = $year . '-' . date('y', strtotime(($year + 1) . '-01-01'));
        ?>
        <option value="<?= e($fy) ?>" <?= $fy === $selectedFinancialYear ? 'selected' : '' ?>>
            <?= e($fy) ?>
        </option>
    <?php endfor; ?>
</select>

                <textarea
                    name="notes"
                    class="min-h-[76px] rounded-2xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                    placeholder="Notes / Instructions"
                ></textarea>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h2 class="font-black text-slate-900">Required Documents</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Loaded from service_requirements table for service_id:
                        <strong><?= e((string) ($service['id'] ?? 0)) ?></strong>
                    </p>
                </div>

                <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                    <?= e((string) count($requirements)) ?> requirement(s)
                </span>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <?php foreach ($requirements as $req): ?>
                    <?php
                        $requirementId = (int) ($req['id'] ?? 0);
                        $requirementLabel = (string) ($req['label'] ?? 'Document');
                        $requirementIcon = trim((string) ($req['icon'] ?? ''));
                        $helpText = (string) ($req['help_text'] ?? '');
                        $isRequired = (int) ($req['is_required'] ?? 1) === 1;
                        $allowMultiple = (int) ($req['allow_multiple'] ?? 0) === 1;
                        $inputName = 'requirement_' . $requirementId . ($allowMultiple ? '[]' : '');
                    ?>

                    <label class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-sm font-black text-slate-700">
                                <?php if ($requirementIcon !== ''): ?>
                                    <i class="<?= e($requirementIcon) ?>"></i>
                                <?php else: ?>
                                    DOC
                                <?php endif; ?>
                            </span>

                            <span class="min-w-0">
                                <span class="block text-sm font-black text-slate-900">
                                    <?= e($requirementLabel) ?>
                                    <?php if ($isRequired): ?>
                                        <span class="text-rose-600">*</span>
                                    <?php endif; ?>
                                </span>

                                <?php if ($helpText !== ''): ?>
                                    <span class="mt-1 block text-xs leading-5 text-slate-500">
                                        <?= e($helpText) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($allowMultiple): ?>
                                    <span class="mt-1 inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-bold text-sky-700">
                                        Multiple files allowed
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <input
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            type="file"
                            name="<?= e($inputName) ?>"
                            <?= $allowMultiple ? 'multiple' : '' ?>
                            <?= $isRequired ? 'required' : '' ?>
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                        >
                    </label>
                <?php endforeach; ?>

                <?php if (empty($requirements)): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800 md:col-span-2">
                        No active required documents configured for this service_id in service_requirements.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <button class="h-12 rounded-2xl bg-brand-700 px-5 text-sm font-bold text-white" type="submit">
            Place Order & Continue to Payment
        </button>
    </form>
</section>
