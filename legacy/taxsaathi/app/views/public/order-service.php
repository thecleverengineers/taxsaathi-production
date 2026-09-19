<?php
$service = is_array($service ?? null) ? $service : [];
$order = is_array($order ?? null) ? $order : [];
$requirements = is_array($requirements ?? null) ? array_values($requirements) : [];

$step = (string) ($step ?? 'order');
$isPaymentStep = $step === 'payment' && !empty($order);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$isLoggedInForOrder = (bool) ($isLoggedInForOrder ?? (
    !empty($_SESSION['user_id'])
    || !empty($_SESSION['auth_user_id'])
    || !empty($_SESSION['user']['id'])
    || !empty($_SESSION['auth_user']['id'])
));

// This flag is supplied by the controller after server-side role detection.
// Never trust a client-posted value to decide whether an order is a partner order.
$isPartnerOrder = (bool) ($isPartnerOrder ?? !empty($order['partner_id']));

/*
|--------------------------------------------------------------------------
| Fixed Financial Year options
|--------------------------------------------------------------------------
| These options are used only when the service slug is itr-filing or
| itr-full-package. For all other services, the field is not rendered.
*/
$financialYears = [
    ['value' => '1 Year (FY-2025-26)', 'label' => '1 Year (FY-2025-26)'],
    ['value' => '2 Years (FY-2024-25 & FY-2025-26)', 'label' => '2 Years (FY-2024-25 & FY-2025-26)'],
    ['value' => '3 Years (FY-2023-24, FY-2024-25 & FY-2025-26)', 'label' => '3 Years (FY-2023-24, FY-2024-25 & FY-2025-26)'],
    ['value' => '4 Years (FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)', 'label' => '4 Years (FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)'],
    ['value' => '5 Years (FY-2021-22, FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)', 'label' => '5 Years (FY-2021-22, FY-2022-23, FY-2023-24, FY-2024-25 & FY-2025-26)'],
];

// No whitelist validation in the view. Keep the posted/controller value as-is.
$selectedFinancialYear = html_entity_decode(
    trim((string) ($selectedFinancialYear ?? '1 Year (FY-2025-26)')),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
);

if ($selectedFinancialYear === '') {
    $selectedFinancialYear = '1 Year (FY-2025-26)';
}

$serviceTitle = (string) ($service['title'] ?? $order['service_title'] ?? 'Service Order');
$serviceDescription = (string) ($service['description'] ?? $order['service_description'] ?? 'Submit your order and complete payment securely.');
$orderPayableAmount = $order['payable_amount'] ?? null;
$feeAmount = $orderPayableAmount !== null && $orderPayableAmount !== ''
    ? (float) $orderPayableAmount
    : (float) ($order['fee_amount'] ?? $service['filing_fee'] ?? 0);
$turnaroundDays = (string) ($service['turnaround_days'] ?? $order['service_turnaround_days'] ?? '0');
$serviceIcon = (string) (($service['icon'] ?? $order['service_icon'] ?? '') !== '' ? ($service['icon'] ?? $order['service_icon']) : 'TS');

$orderId = (int) ($order['id'] ?? 0);
$orderNo = (string) ($order['order_no'] ?? '');
$paymentReference = (string) ($order['payment_reference'] ?? '');

$paymentUpiId = (string) ($paymentUpiId ?? 'taxsaathi@upi');
$paymentReceiverName = (string) ($paymentReceiverName ?? 'Tax Saathi');
$paymentQrImage = trim((string) ($paymentQrImage ?? ''));
$paymentBankName = trim((string) ($paymentBankName ?? ''));
$paymentBankIfsc = trim((string) ($paymentBankIfsc ?? ''));
$paymentBankAccountNumber = trim((string) ($paymentBankAccountNumber ?? ''));

if ($paymentQrImage !== '' && !preg_match('~^(https?:)?//|^data:~i', $paymentQrImage)) {
    $paymentQrImage = base_url(ltrim($paymentQrImage, '/'));
}

$paymentMethods = [
    'upi' => [
        'label' => 'UPI',
        'description' => 'Pay through UPI and upload the transaction reference and proof.',
    ],
    'bank_transfer' => [
        'label' => 'Bank Transfer',
        'description' => 'Transfer the amount to the provided bank account and upload the receipt.',
    ],
    'cash_on_delivery' => [
        'label' => 'Cash on Delivery (Pay Later)',
        'description' => 'Request cash payment now and pay later after admin confirmation.',
    ],
];

$selectedPaymentMethod = strtolower(trim((string) (
    $order['payment_method']
        ?? $_POST['payment_method']
        ?? $_GET['payment_method']
        ?? 'upi'
)));

// Keep old records using `cash` readable while storing new submissions as
// the clearer `cash_on_delivery` value.
if ($selectedPaymentMethod === 'cash') {
    $selectedPaymentMethod = 'cash_on_delivery';
}

if (!isset($paymentMethods[$selectedPaymentMethod])) {
    $selectedPaymentMethod = 'upi';
}

$paymentMethodLabel = $paymentMethods[$selectedPaymentMethod]['label'];
$requiresPaymentProof = in_array($selectedPaymentMethod, ['upi', 'bank_transfer'], true);

$upiAmount = number_format($feeAmount, 2, '.', '');
$upiNote = $orderNo !== '' ? ('Order ' . $orderNo) : 'Tax Saathi Service Order';

$upiPayUrl = 'upi://pay?pa=' . rawurlencode($paymentUpiId)
    . '&pn=' . rawurlencode($paymentReceiverName)
    . '&am=' . rawurlencode($upiAmount)
    . '&cu=INR'
    . '&tn=' . rawurlencode($upiNote);

/*
|--------------------------------------------------------------------------
| Back button URL
|--------------------------------------------------------------------------
| Uses an optional controller-provided $backUrl first, then safe referrer,
| then a stable fallback route. Kept here so the view works standalone.
*/
$serviceSlugForBack = trim((string) ($service['slug'] ?? $order['service_slug'] ?? $serviceSlug ?? ''));

$serviceOrderBackUrl = $serviceSlugForBack !== ''
    ? (base_url('service-order') . '?service=' . rawurlencode($serviceSlugForBack))
    : base_url('services');

$defaultBackUrl = $isPaymentStep ? $serviceOrderBackUrl : base_url('services');

$incomingBackUrl = trim((string) ($backUrl ?? ''));

if ($incomingBackUrl === '' && !empty($_SERVER['HTTP_REFERER'])) {
    $incomingBackUrl = trim((string) $_SERVER['HTTP_REFERER']);
}

if ($incomingBackUrl === '' || preg_match('/[\r\n]/', $incomingBackUrl)) {
    $incomingBackUrl = $defaultBackUrl;
}

$backUrl = $incomingBackUrl;
$backButtonLabel = $isPaymentStep ? 'Back to Order Details' : 'Back to Services';

/*
|--------------------------------------------------------------------------
| Financial Year visibility - HARD LOCK
|--------------------------------------------------------------------------
| Show Financial Year ONLY for these exact service slugs:
|   1) itr-filing
|   2) itr-full-package
|
| No category fallback. No title fallback. No Income Tax Services fallback.
| For every other service, the field is not printed in HTML at all.
*/
$financialYearAllowedSlugMap = [
    'itr-filing' => true,
    'itr-full-package' => true,
];

$serviceSlugForFinancialYear = strtolower(trim((string) ($service['slug'] ?? $serviceSlug ?? '')));
$serviceSlugForFinancialYear = str_replace('_', '-', $serviceSlugForFinancialYear);
$serviceSlugForFinancialYear = preg_replace('/[^a-z0-9-]+/', '-', $serviceSlugForFinancialYear) ?: '';
$serviceSlugForFinancialYear = trim((string) preg_replace('/-+/', '-', $serviceSlugForFinancialYear), '-');

$showFinancialYear = isset($financialYearAllowedSlugMap[$serviceSlugForFinancialYear]);

$sessionUserForContact = is_array($_SESSION['user'] ?? null)
    ? $_SESSION['user']
    : (is_array($_SESSION['auth_user'] ?? null) ? $_SESSION['auth_user'] : []);

$prefillCustomerNameAsPerPan = (string) ($_POST['customer_name_as_per_pan'] ?? $sessionUserForContact['name'] ?? $sessionUserForContact['full_name'] ?? '');
$prefillCustomerPanNumber = strtoupper(trim((string) ($_POST['customer_pan_number'] ?? $_POST['pan_number'] ?? '')));
$prefillCustomerMobile = (string) ($_POST['customer_mobile'] ?? $sessionUserForContact['mobile'] ?? $sessionUserForContact['phone'] ?? '');
$prefillCustomerEmail = (string) ($_POST['customer_email'] ?? $sessionUserForContact['email'] ?? '');


$requirementInputType = static function (array $requirement): string {
    $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($requirement['label'] ?? '')) ?? ''));

    if (str_contains($label, 'email') || str_contains($label, 'e-mail')) {
        return 'email';
    }

    if (str_contains($label, 'mobile') || str_contains($label, 'phone') || str_contains($label, 'whatsapp') || str_contains($label, 'contact number')) {
        return 'mobile';
    }

    return 'file';
};

$fieldRequirements = [];
$fileRequirements = [];

foreach ($requirements as $requirement) {
    $requirementId = (int) ($requirement['id'] ?? 0);

    if ($requirementId <= 0) {
        continue;
    }

    $type = $requirementInputType($requirement);
    $requirement['input_type'] = $type;

    if ($type === 'file') {
        $fileRequirements[] = $requirement;
    } else {
        $fieldRequirements[] = $requirement;
    }
}
?>

<style>
    .ts-order-premium {
        position: relative;
        min-height: 100vh;
        background:
            radial-gradient(circle at 8% 8%, rgba(94, 122, 196, 0.22), transparent 30%),
            radial-gradient(circle at 92% 12%, rgba(232, 178, 101, 0.22), transparent 32%),
            linear-gradient(180deg, #f8fbff 0%, #ffffff 48%, #fff8ef 100%);
    }

    .ts-order-premium::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: 0.42;
        background-image:
            linear-gradient(rgba(56, 82, 180, 0.055) 1px, transparent 1px),
            linear-gradient(90deg, rgba(56, 82, 180, 0.055) 1px, transparent 1px);
        background-size: 42px 42px;
        mask-image: linear-gradient(to bottom, rgba(0,0,0,0.86), transparent 72%);
    }

    .ts-glass-bar {
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        background: rgba(255, 255, 255, 0.78);
        box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08);
    }

    .ts-premium-ring {
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,0.8),
            0 28px 80px rgba(15, 23, 42, 0.10);
    }

    .ts-back-button {
        transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    .ts-back-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12);
    }

    .ts-back-button:focus-visible,
    .ts-focusable:focus-visible {
        outline: 3px solid rgba(94, 122, 196, 0.32);
        outline-offset: 3px;
    }

    @media (max-width: 640px) {
        .ts-order-premium::before {
            background-size: 28px 28px;
        }
    }
</style>

<div class="ts-order-premium">
    <div class="relative z-10 px-4 pt-5 sm:px-6 lg:px-10">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 rounded-[26px] border border-white/80 px-3 py-3 ts-glass-bar sm:px-4">
            <a
                href="<?= e($backUrl) ?>"
                data-smart-back="1"
                data-fallback-url="<?= e($defaultBackUrl) ?>"
                class="ts-back-button ts-focusable group inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white/90 px-4 py-2.5 text-sm font-black text-slate-800 shadow-sm hover:border-brand-200 hover:text-brand-700"
                aria-label="<?= e($backButtonLabel) ?>"
            >
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-base leading-none text-white transition group-hover:bg-brand-700">
                    ←
                </span>
                <span class="hidden sm:inline"><?= e($backButtonLabel) ?></span>
                <span class="sm:hidden">Back</span>
            </a>

            <div class="min-w-0 text-right">
                <div class="truncate text-[11px] font-black uppercase tracking-[0.24em] text-slate-400">
                    Tax Saathi Secure Desk
                </div>
                <div class="mt-0.5 truncate text-sm font-extrabold text-slate-900">
                    <?= $isPaymentStep ? 'Payment Verification' : 'Professional Service Order' ?>
                </div>
            </div>
        </div>
    </div>

<section class="relative overflow-hidden px-4 pb-8 pt-6 sm:px-6 lg:px-10">
    <div class="absolute inset-x-0 top-0 -z-10 h-[360px] bg-gradient-to-b from-brand-50/70 via-white/50 to-transparent"></div>
    <div class="absolute -left-16 top-0 -z-10 h-64 w-64 rounded-full bg-brand-200/30 blur-3xl"></div>
    <div class="absolute -right-16 top-12 -z-10 h-64 w-64 rounded-full bg-accent-200/30 blur-3xl"></div>

    <div class="mx-auto max-w-7xl overflow-hidden rounded-[34px] border border-white/80 bg-gradient-to-br from-[#f7fbff]/95 via-white/95 to-[#fff8f1]/95 ts-premium-ring">
        <div class="grid gap-8 px-6 py-10 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:px-12 lg:py-14">
            <div class="relative z-10">
                <span class="inline-flex items-center rounded-full border border-brand-100 bg-white/80 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-brand-700 shadow-sm">
                    <?= $isPaymentStep ? 'Complete Payment' : 'Order Service' ?>
                </span>

                <h1 class="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-[-0.05em] text-slate-900 sm:text-5xl">
                    <?= e($serviceTitle) ?>
                </h1>

                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">
                    <?= e($serviceDescription) ?>
                </p>

                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[22px] border <?= !$isPaymentStep ? 'border-brand-200 bg-brand-50 text-brand-800' : 'border-slate-200 bg-white text-slate-500' ?> px-5 py-4 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.22em]">Step 1</div>
                        <div class="mt-1 text-sm font-extrabold">Submit Order & Documents</div>
                    </div>

                    <div class="rounded-[22px] border <?= $isPaymentStep ? 'border-[#e8b265] bg-[#fff7e8] text-[#9a5c13]' : 'border-slate-200 bg-white text-slate-500' ?> px-5 py-4 shadow-sm">
                        <div class="text-xs font-black uppercase tracking-[0.22em]">Step 2</div>
                        <div class="mt-1 text-sm font-extrabold">Payment Method &amp; Confirmation</div>
                    </div>
                </div>
            </div>

            <div class="relative z-10 grid gap-4 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-brand-700">
                        <?= e(format_money($feeAmount)) ?>
                    </div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">Filing fee</div>
                </div>

                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-[#b86a1d]">
                        <?= e($turnaroundDays) ?> Days
                    </div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">Turnaround time</div>
                </div>

                <div class="rounded-[24px] border border-white/70 bg-white/85 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <div class="text-lg font-black tracking-[-0.02em] text-brand-700">
                        <?= $isPaymentStep ? e($orderNo) : count($fileRequirements) ?>
                    </div>
                    <div class="mt-1 text-sm leading-6 text-slate-600">
                        <?= $isPaymentStep ? 'Order number' : 'Required documents' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="relative z-10 px-4 pb-12 pt-4 sm:px-6 lg:px-10">
    <div class="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[0.92fr_1.08fr]">
        <div class="rounded-[32px] border border-white/80 bg-white/90 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-2xl font-black tracking-[-0.03em] text-slate-900">Service Summary</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">
                        <?= $isPaymentStep
                            ? 'Your order has been submitted. Complete payment and upload proof for admin verification.'
                            : 'Review the service details and document checklist before placing your order.' ?>
                    </p>
                </div>

                <div class="inline-flex h-14 w-14 items-center justify-center rounded-[20px] bg-gradient-to-br from-[#5E7AC4] to-[#3852B4] text-sm font-black tracking-[0.14em] text-white shadow-[0_14px_30px_rgba(56,82,180,0.18)]">
                    <?= e($serviceIcon) ?>
                </div>
            </div>

            <div class="mt-8 space-y-3">
                <?php if ($isPaymentStep): ?>
                    <?php if ($isPartnerOrder): ?>
                        <div class="flex items-center justify-between gap-4 rounded-[20px] border border-amber-200 bg-amber-50 px-4 py-4">
                            <span class="text-sm font-bold text-amber-800">Order Type</span>
                            <strong class="text-right text-base font-black text-amber-900">Partner Order</strong>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <span class="text-sm font-bold text-slate-600">Order No</span>
                        <strong class="text-right text-base font-black text-brand-700"><?= e($orderNo) ?></strong>
                    </div>

                    <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <span class="text-sm font-bold text-slate-600">Order Status</span>
                        <strong class="text-right text-base font-black text-brand-700"><?= e((string) ($order['status'] ?? 'submitted')) ?></strong>
                    </div>

                    <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <span class="text-sm font-bold text-slate-600">Payment Status</span>
                        <strong class="text-right text-base font-black text-[#b86a1d]"><?= e((string) ($order['payment_status'] ?? 'pending')) ?></strong>
                    </div>

                    <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <span class="text-sm font-bold text-slate-600">Payment Method</span>
                        <strong class="text-right text-base font-black text-brand-700"><?= e($paymentMethodLabel) ?></strong>
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                    <span class="text-sm font-bold text-slate-600">Filing Fee</span>
                    <strong class="text-base font-black text-brand-700"><?= e(format_money($feeAmount)) ?></strong>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                    <span class="text-sm font-bold text-slate-600">Turnaround</span>
                    <strong class="text-base font-black text-brand-700"><?= e($turnaroundDays) ?> days</strong>
                </div>

                <?php if (!$isPaymentStep && $showFinancialYear): ?>
                    <div data-financial-year-block="summary" class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <span class="text-sm font-bold text-slate-600">Financial Year</span>
                        <strong class="text-right text-base font-black text-brand-700">
                            <?= e($selectedFinancialYear) ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$isPaymentStep): ?>
                <div class="mt-8 rounded-[28px] border border-brand-100/60 bg-gradient-to-br from-brand-50/40 to-white p-5 sm:p-6">
                    <h4 class="text-xl font-black tracking-[-0.03em] text-slate-900">Required documents</h4>

                    <div class="mt-5 space-y-4">
                        <?php if (!empty($fileRequirements)): ?>
                            <?php foreach ($fileRequirements as $requirement): ?>
                                <div class="flex items-start gap-3 rounded-[20px] border border-slate-200 bg-white px-4 py-4">
                                    <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-black text-brand-700">✓</span>

                                    <div class="min-w-0">
                                        <div class="text-sm font-extrabold text-slate-900">
                                            <?= e((string) ($requirement['label'] ?? 'Document')) ?>
                                            <?php if ((int) ($requirement['is_required'] ?? 0) === 1): ?>
                                                <span class="text-[#b86a1d]">*</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($requirement['help_text'])): ?>
                                            <div class="mt-1 text-sm leading-6 text-slate-500">
                                                <?= e((string) $requirement['help_text']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">
                                            <?= (int) ($requirement['allow_multiple'] ?? 0) === 1 ? 'Multiple files allowed' : 'Single file upload' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="rounded-[20px] border border-dashed border-brand-200 bg-white px-4 py-5 text-sm font-semibold text-slate-500">
                                No requirements configured for this service yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-8 rounded-[28px] border border-amber-200 bg-amber-50 p-5 sm:p-6">
                    <h4 class="text-xl font-black tracking-[-0.03em] text-slate-900">Payment Instructions</h4>
                    <div class="mt-4 space-y-3 text-sm leading-7 text-amber-900">
                                <p>1. Select UPI, Bank Transfer, or Cash on Delivery (Pay Later) in the order form.</p>
                        <p>2. Submit your order and review the selected method on Step 2.</p>
                        <p>3. Upload payment proof for UPI or Bank Transfer.</p>
                                <p>4. For Cash on Delivery (Pay Later), confirm the request and wait for admin confirmation.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="rounded-[32px] bg-gradient-to-br from-[#3852B4] via-[#5E7AC4] to-[#7f96d4] p-[1px] shadow-[0_24px_60px_rgba(56,82,180,0.20)]">
            <div class="h-full rounded-[31px] bg-white/95 p-6 sm:p-8">
                <?php if (!$isPaymentStep): ?>
                    <div class="max-w-md">
                        <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d]">
                            Step 1
                        </span>

                        <h3 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900">Submit order details</h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            <?= $isPartnerOrder
                                ? 'You are submitting this order as a partner. Your order will be recorded under your partner profile for administrative tracking.'
                                : 'Upload all required documents and submit your order. If you are not logged in, you can login or sign up here without losing the form.' ?>
                        </p>

                        <?php if ($isPartnerOrder): ?>
                            <div class="mt-4 inline-flex w-fit items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-amber-800">
                                Partner order record
                            </div>
                        <?php endif; ?>
                    </div>

                    <form id="serviceOrderForm" method="post" action="<?= e(base_url('service-order')) ?>" enctype="multipart/form-data" class="mt-8 grid gap-4">
                        <?= csrf_field() ?>

                        <input type="hidden" name="service_id" value="<?= e((string) ($service['id'] ?? '')) ?>">
                        <input type="hidden" name="service_slug" value="<?= e((string) ($service['slug'] ?? $serviceSlug ?? '')) ?>">

                        <?php if ($showFinancialYear): ?>
                            <label data-financial-year-block="form" class="grid gap-2 rounded-[24px] border border-brand-100 bg-brand-50/70 p-4">
                                <span class="text-sm font-bold text-slate-700">
                                    Financial Year <span class="text-[#b86a1d]">*</span>
                                </span>

                                <select
                                    name="financial_year"
                                    class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                                >
                                    <option value="" style="font-size: 13px;">Select Financial Year</option>
                                    <?php foreach ($financialYears as $fy): ?>
                                        <?php
                                            $value = (string) ($fy['value'] ?? '');
                                            $label = (string) ($fy['label'] ?? $value);
                                        ?>
                                        <option style="font-size: 13px;" value="<?= e($value) ?>" <?= $selectedFinancialYear === $value ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <small class="text-xs leading-5 text-slate-500">
                                    Visible only for itr-filing and itr-full-package.
                                </small>
                            </label>
                        <?php endif; ?>

                        <label class="grid gap-2 rounded-[24px] border border-brand-100 bg-brand-50/70 p-4">
                            <span class="text-sm font-bold text-slate-700">
                                Payment Method <span class="text-[#b86a1d]">*</span>
                            </span>

                            <select
                                id="paymentMethodSelect"
                                name="payment_method"
                                required
                                class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                            >
                                <?php foreach ($paymentMethods as $methodValue => $method): ?>
                                    <option value="<?= e($methodValue) ?>" <?= $selectedPaymentMethod === $methodValue ? 'selected' : '' ?>>
                                        <?= e($method['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small id="paymentMethodHelp" class="text-xs leading-5 text-slate-500">
                                <?= e($paymentMethods[$selectedPaymentMethod]['description']) ?>
                            </small>
                        </label>

                        <div class="rounded-[24px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-7 text-amber-800">
                            <strong class="font-black">Payment method will be confirmed in Step 2.</strong>
                            <br>
                            After this form is submitted, your order will be created first. Follow the instructions for your selected method.
                        </div>

                        <label class="grid gap-2">
                            <span class="text-sm font-bold text-slate-700">Additional Notes</span>
                            <textarea
                                class="min-h-[120px] rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                                name="notes"
                                rows="4"
                                placeholder="Mention any important context for this filing"
                            ></textarea>
                        </label>

                        <div class="grid gap-4 rounded-[28px] border border-slate-200 bg-white p-4 sm:p-5">
                            <div>
                                <h4 class="text-lg font-black tracking-[-0.03em] text-slate-900">Customer Contact Details</h4>
                                <p class="mt-1 text-sm leading-6 text-slate-500">
                                    Enter PAN identity, mobile number and email for this order. These details are saved into customer_details before document upload.
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">
                                        Name as per PAN <span class="text-[#b86a1d]">*</span>
                                    </span>
                                    <input
                                        class="h-14 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                                        type="text"
                                        name="customer_name_as_per_pan"
                                        value="<?= e($prefillCustomerNameAsPerPan) ?>"
                                        required
                                        autocomplete="name"
                                        placeholder="Enter name exactly as per PAN"
                                    >
                                </label>

                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">
                                        PAN Number <span class="text-[#b86a1d]">*</span>
                                    </span>
                                    <input
                                        class="h-14 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-medium uppercase tracking-[0.08em] text-slate-800 outline-none transition placeholder:normal-case placeholder:tracking-normal placeholder:text-slate-400 focus:border-brand-300"
                                        type="text"
                                        name="customer_pan_number"
                                        value="<?= e($prefillCustomerPanNumber) ?>"
                                        required
                                        maxlength="10"
                                        pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}"
                                        autocomplete="off"
                                        placeholder="ABCDE1234F"
                                        oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10)"
                                    >
                                </label>

                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">
                                        Mobile Number <span class="text-[#b86a1d]">*</span>
                                    </span>
                                    <input
                                        class="h-14 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                                        type="tel"
                                        name="customer_mobile"
                                        value="<?= e($prefillCustomerMobile) ?>"
                                        required
                                        inputmode="tel"
                                        autocomplete="tel"
                                        placeholder="Enter mobile number"
                                    >
                                </label>

                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">
                                        Email Address <span class="text-[#b86a1d]">*</span>
                                    </span>
                                    <input
                                        class="h-14 rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                                        type="email"
                                        name="customer_email"
                                        value="<?= e($prefillCustomerEmail) ?>"
                                        required
                                        autocomplete="email"
                                        placeholder="Enter email address"
                                    >
                                </label>
                            </div>
                        </div>

                        <?php if (!empty($fileRequirements)): ?>
                        <div
                            id="dynamicDocumentUploader"
                            class="grid gap-4 rounded-[28px] border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
                        >
                            <div>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="text-lg font-black tracking-[-0.03em] text-slate-900">
                                            Upload Documents
                                        </h4>
                                        <p class="mt-1 text-sm leading-6 text-slate-500">
                                            Select the document name from the dropdown, choose file, add it to the list, then submit.
                                        </p>
                                    </div>

                                    <span
                                        id="documentUploadCount"
                                        class="inline-flex w-fit rounded-full border border-brand-100 bg-white px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] text-brand-700"
                                    >
                                        0 Added
                                    </span>
                                </div>

                                <div
                                    id="documentUploadAlert"
                                    class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"
                                ></div>
                            </div>

                            <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">Select Document</span>

                                    <select
                                        id="documentTypeSelect"
                                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition focus:border-brand-300"
                                    >
                                        <option value="">Choose document type</option>

                                        <?php foreach ($fileRequirements as $requirement): ?>
                                            <?php
                                                $requirementId = (int) ($requirement['id'] ?? 0);
                                                $allowMultiple = (int) ($requirement['allow_multiple'] ?? 0) === 1;
                                                $isRequired = (int) ($requirement['is_required'] ?? 0) === 1;
                                                $label = trim((string) ($requirement['label'] ?? 'Document'));
                                                $helpText = trim((string) ($requirement['help_text'] ?? ''));
                                            ?>

                                            <?php if ($requirementId > 0): ?>
                                                <option
                                                    value="<?= e((string) $requirementId) ?>"
                                                    data-requirement-id="<?= e((string) $requirementId) ?>"
                                                    data-label="<?= e($label) ?>"
                                                    data-required="<?= $isRequired ? '1' : '0' ?>"
                                                    data-multiple="<?= $allowMultiple ? '1' : '0' ?>"
                                                    data-help="<?= e($helpText) ?>"
                                                >
                                                    <?= e($label . ($isRequired ? ' *' : '') . ($allowMultiple ? ' — Multiple' : '')) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">Choose File</span>

                                    <input
                                        id="documentPicker"
                                        class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-accent-100 file:px-4 file:py-2 file:text-sm file:font-extrabold file:text-[#b86a1d] hover:file:bg-accent-200"
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                    >
                                </label>

                                <div class="flex items-end">
                                    <button
                                        id="addDocumentButton"
                                        type="button"
                                        class="inline-flex h-14 w-full items-center justify-center rounded-full bg-slate-900 px-5 text-sm font-black text-white shadow-[0_14px_30px_rgba(15,23,42,0.16)] transition hover:-translate-y-0.5 sm:w-auto"
                                    >
                                        Add Document
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-[24px] border border-dashed border-slate-300 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h5 class="text-sm font-black uppercase tracking-[0.16em] text-slate-500">
                                            Documents added before final submission
                                        </h5>
                                        <p class="mt-1 text-xs leading-5 text-slate-400">
                                            Image files show thumbnails. PDF, Word and Excel files show file cards.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    id="documentUploadList"
                                    class="mt-4 grid gap-3"
                                >
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm font-semibold text-slate-500">
                                        No documents added yet.
                                    </div>
                                </div>
                            </div>

                            <div id="documentHiddenInputs" class="hidden">
                                <?php foreach ($fileRequirements as $requirement): ?>
                                    <?php
                                        $requirementId = (int) ($requirement['id'] ?? 0);
                                        $isRequired = (int) ($requirement['is_required'] ?? 0) === 1;
                                        $allowMultiple = (int) ($requirement['allow_multiple'] ?? 0) === 1;
                                    ?>

                                    <?php if ($requirementId > 0): ?>
                                        <input
                                            id="requirementInput_<?= e((string) $requirementId) ?>"
                                            data-requirement-input="1"
                                            data-requirement-id="<?= e((string) $requirementId) ?>"
                                            data-required="<?= $isRequired ? '1' : '0' ?>"
                                            data-multiple="<?= $allowMultiple ? '1' : '0' ?>"
                                            type="file"
                                            name="requirement_<?= e((string) $requirementId) ?>[]"
                                            multiple
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                                        >
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button
                            id="placeOrderButton"
                            class="mt-2 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70"
                            type="submit"
                        >
                            Submit Order & Continue to Payment
                        </button>
                    </form>
                <?php else: ?>
                    <div class="max-w-md">
                        <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d]">
                            Step 2
                        </span>

                        <h3 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900">
                            <?= e($paymentMethodLabel) ?>
                        </h3>

                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            <?php if ($selectedPaymentMethod === 'upi'): ?>
                                Scan the QR code, complete payment, then submit the transaction ID and screenshot.
                            <?php elseif ($selectedPaymentMethod === 'bank_transfer'): ?>
                                Complete the bank transfer, then submit the transfer reference and receipt.
                            <?php else: ?>
                                Confirm your cash-on-delivery request. Admin will review and confirm the collection details.
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="mt-8 grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
                        <?php if ($selectedPaymentMethod === 'upi'): ?>
                            <div class="rounded-[28px] border border-slate-200 bg-slate-50 p-5">
                                <div class="rounded-[24px] border border-white bg-white p-4 shadow-sm">
                                    <?php if ($paymentQrImage !== ''): ?>
                                        <img
                                            src="<?= e($paymentQrImage) ?>"
                                            alt="UPI QR Code"
                                            class="mx-auto aspect-square w-full max-w-[260px] rounded-2xl object-contain"
                                        >
                                    <?php else: ?>
                                        <div class="flex aspect-square w-full max-w-[260px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-center text-xs font-black uppercase tracking-[0.16em] text-slate-400">
                                            UPI QR<br>Not Configured
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">UPI ID</div>
                                        <div class="mt-1 break-all text-sm font-black text-slate-900"><?= e($paymentUpiId) ?></div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Amount</div>
                                        <div class="mt-1 text-lg font-black text-brand-700"><?= e(format_money($feeAmount)) ?></div>
                                    </div>

                                    <a
                                        href="<?= e($upiPayUrl) ?>"
                                        class="inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-extrabold text-white shadow-[0_14px_30px_rgba(15,23,42,0.18)] transition hover:-translate-y-0.5"
                                    >
                                        Open UPI App
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($selectedPaymentMethod === 'bank_transfer'): ?>
                            <div class="rounded-[28px] border border-slate-200 bg-slate-50 p-5">
                                <div class="rounded-[24px] border border-blue-100 bg-blue-50 p-5">
                                    <h4 class="text-xl font-black tracking-[-0.03em] text-slate-900">Bank Transfer</h4>
                                    <p class="mt-3 text-sm leading-7 text-slate-600">
                                        Transfer the amount below to the Tax Saathi account, then upload the UTR/reference and receipt for approval.
                                    </p>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Bank Name</div>
                                        <div class="mt-1 break-words text-sm font-black text-slate-900"><?= e($paymentBankName !== '' ? $paymentBankName : 'Not configured') ?></div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Account Number</div>
                                        <div class="mt-1 break-all text-sm font-black text-slate-900"><?= e($paymentBankAccountNumber !== '' ? $paymentBankAccountNumber : 'Not configured') ?></div>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">IFSC</div>
                                        <div class="mt-1 break-words text-sm font-black text-slate-900"><?= e($paymentBankIfsc !== '' ? $paymentBankIfsc : 'Not configured') ?></div>
                                    </div>
                                </div>

                                <div class="mt-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Amount to Transfer</div>
                                    <div class="mt-1 text-lg font-black text-brand-700"><?= e(format_money($feeAmount)) ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="rounded-[28px] border border-amber-200 bg-amber-50 p-5">
                                <h4 class="text-xl font-black tracking-[-0.03em] text-slate-900">Cash on Delivery (Pay Later)</h4>
                                <p class="mt-3 text-sm leading-7 text-amber-900">
                                    You have selected Cash on Delivery (Pay Later). Confirm the request below; the admin team will review the order and coordinate the collection details.
                                </p>
                                <div class="mt-5 rounded-2xl border border-amber-200 bg-white px-4 py-3">
                                    <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Amount Due</div>
                                    <div class="mt-1 text-lg font-black text-brand-700"><?= e(format_money($feeAmount)) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($requiresPaymentProof): ?>
                            <form id="paymentProofForm" method="post" action="<?= e(base_url('service-order/payment')) ?>" enctype="multipart/form-data" class="grid content-start gap-4">
                                <?= csrf_field() ?>

                                <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">
                                <input type="hidden" name="payment_method" value="<?= e($selectedPaymentMethod) ?>">

                                <div class="rounded-[24px] border border-brand-100 bg-brand-50/70 px-4 py-4 text-sm leading-7 text-brand-800">
                                    <strong class="font-black">Order already submitted.</strong>
                                    <br>
                                    Now submit your <?= e($paymentMethodLabel) ?> proof. Admin will verify and approve the payment.
                                </div>

                                <label class="grid gap-2">
                                    <span class="text-sm font-bold text-slate-700">
                                        <?= $selectedPaymentMethod === 'bank_transfer' ? 'Bank Transfer UTR / Reference No' : 'UPI Transaction ID / Reference No' ?>
                                    </span>
                                    <input
                                        class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300"
                                        type="text"
                                        name="transaction_id"
                                        value="<?= e($paymentReference) ?>"
                                        required
                                        placeholder="Example: 412345678901"
                                    >
                                </label>

                                <label class="grid gap-2 rounded-[24px] border border-slate-200 bg-slate-50/60 p-4">
                                    <span class="text-sm font-bold text-slate-700">Payment Screenshot / Receipt <span class="text-[#b86a1d]">*</span></span>
                                    <small class="text-sm leading-6 text-slate-500">
                                        Upload the <?= $selectedPaymentMethod === 'bank_transfer' ? 'bank transfer receipt' : 'UPI success screenshot' ?> or PDF receipt.
                                    </small>

                                    <input
                                        class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-accent-100 file:px-4 file:py-2 file:text-sm file:font-extrabold file:text-[#b86a1d] hover:file:bg-accent-200"
                                        type="file"
                                        name="payment_screenshot"
                                        required
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    >
                                </label>

                                <button
                                    id="paymentProofButton"
                                    data-busy-text="Submitting Payment Proof..."
                                    class="mt-2 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70"
                                    type="submit"
                                >
                                    Submit <?= e($paymentMethodLabel) ?> Proof
                                </button>
                            </form>
                        <?php else: ?>
                            <form id="paymentProofForm" method="post" action="<?= e(base_url('service-order/payment')) ?>" class="grid content-start gap-4">
                                <?= csrf_field() ?>

                                <input type="hidden" name="order_id" value="<?= e((string) $orderId) ?>">
                                <input type="hidden" name="payment_method" value="cash_on_delivery">

                                <div class="rounded-[24px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-7 text-amber-900">
                                    <strong class="font-black">Cash on Delivery (Pay Later) selected.</strong>
                                    <br>
                                    Click the button below to submit the request. No online payment proof is required.
                                </div>

                                <button
                                    id="paymentProofButton"
                                    data-busy-text="Confirming Cash on Delivery..."
                                    class="mt-2 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70"
                                    type="submit"
                                >
                                    Confirm Cash on Delivery (Pay Later)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if (!$isLoggedInForOrder && !$isPaymentStep): ?>
    <div
        id="inlineOrderAuthModal"
        class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur-sm"
    >
        <div class="w-full max-w-lg overflow-hidden rounded-[30px] border border-white/20 bg-white shadow-[0_30px_90px_rgba(15,23,42,0.35)]">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.24em] text-brand-700">
                            Continue Securely
                        </p>
                        <h3 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900">
                            Login or Sign Up
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Your form and uploaded documents will stay on this page.
                        </p>
                    </div>

                    <button
                        type="button"
                        id="closeInlineOrderAuth"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-lg font-black text-slate-500 hover:bg-slate-50"
                    >
                        ×
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 border-b border-slate-100">
                <button
                    type="button"
                    data-auth-tab="login"
                    class="inlineOrderAuthTab border-b-2 border-slate-900 px-4 py-3 text-sm font-black text-slate-900"
                >
                    Login
                </button>

                <button
                    type="button"
                    data-auth-tab="signup"
                    class="inlineOrderAuthTab border-b-2 border-transparent px-4 py-3 text-sm font-black text-slate-500"
                >
                    Sign Up
                </button>
            </div>

            <div class="p-6">
                <div
                    id="inlineOrderAuthMessage"
                    class="mb-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"
                ></div>

                <form id="inlineOrderLoginForm" class="grid gap-4">
                    <?= csrf_field() ?>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Mobile or Email</span>
                        <input
                            type="text"
                            name="identifier"
                            required
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Enter mobile number or email"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Password</span>
                        <input
                            type="password"
                            name="password"
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Leave blank if your account uses mobile login"
                        >
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-12 items-center justify-center rounded-full bg-slate-900 px-5 text-sm font-black text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        Login & Submit Order
                    </button>
                </form>

                <form id="inlineOrderSignupForm" class="hidden grid gap-4">
                    <?= csrf_field() ?>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Full Name</span>
                        <input
                            type="text"
                            name="name"
                            required
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Enter your full name"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Mobile Number</span>
                        <input
                            type="text"
                            name="mobile"
                            required
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Enter mobile number"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Email</span>
                        <input
                            type="email"
                            name="email"
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Enter email address"
                        >
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Password</span>
                        <input
                            type="password"
                            name="password"
                            class="h-14 rounded-2xl border border-slate-200 px-4 text-sm font-semibold outline-none focus:border-brand-300"
                            placeholder="Optional"
                        >
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-12 items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-5 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        Sign Up & Submit Order
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-smart-back="1"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const fallbackUrl = button.getAttribute('data-fallback-url') || button.getAttribute('href') || '/';

            if (window.history.length > 1) {
                event.preventDefault();
                window.history.back();
                return;
            }

            button.setAttribute('href', fallbackUrl);
        });
    });

    let orderAuthReady = <?= $isLoggedInForOrder ? 'true' : 'false' ?>;
    let submitAfterAuth = false;

    const orderForm = document.getElementById('serviceOrderForm');
    const orderButton = document.getElementById('placeOrderButton');

    const paymentForm = document.getElementById('paymentProofForm');
    const paymentButton = document.getElementById('paymentProofButton');

    const modal = document.getElementById('inlineOrderAuthModal');
    const closeBtn = document.getElementById('closeInlineOrderAuth');
    const loginForm = document.getElementById('inlineOrderLoginForm');
    const signupForm = document.getElementById('inlineOrderSignupForm');
    const tabs = document.querySelectorAll('.inlineOrderAuthTab');
    const messageBox = document.getElementById('inlineOrderAuthMessage');
    const shouldShowFinancialYear = <?= $showFinancialYear ? 'true' : 'false' ?>;
    const paymentMethodSelect = document.getElementById('paymentMethodSelect');
    const paymentMethodHelp = document.getElementById('paymentMethodHelp');
    const paymentMethodDescriptions = {
        upi: 'Pay through UPI and upload the transaction reference and proof.',
        bank_transfer: 'Transfer the amount to the provided bank account and upload the receipt.',
        cash_on_delivery: 'Request cash payment now and pay later after admin confirmation.'
    };

    if (paymentMethodSelect && paymentMethodHelp) {
        paymentMethodSelect.addEventListener('change', function () {
            paymentMethodHelp.textContent = paymentMethodDescriptions[paymentMethodSelect.value] || '';
        });
    }

    // Hard safety cleanup: if any old cached/legacy financial-year input exists for other services, remove it.
    if (!shouldShowFinancialYear) {
        document.querySelectorAll('[data-financial-year-block], input[name="financial_year"], select[name="financial_year"]').forEach(function (element) {
            const wrapper = element.closest('[data-financial-year-block], label');
            (wrapper || element).remove();
        });
    }

    function openAuthModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAuthModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function showMessage(type, message) {
        if (!messageBox) return;

        messageBox.classList.remove('hidden');
        messageBox.textContent = message;

        if (type === 'success') {
            messageBox.className = 'mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700';
        } else {
            messageBox.className = 'mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700';
        }
    }

    function switchTab(tab) {
        if (!loginForm || !signupForm) return;

        if (tab === 'signup') {
            loginForm.classList.add('hidden');
            signupForm.classList.remove('hidden');
        } else {
            signupForm.classList.add('hidden');
            loginForm.classList.remove('hidden');
        }

        tabs.forEach(function (btn) {
            const active = btn.getAttribute('data-auth-tab') === tab;
            btn.classList.toggle('border-slate-900', active);
            btn.classList.toggle('text-slate-900', active);
            btn.classList.toggle('border-transparent', !active);
            btn.classList.toggle('text-slate-500', !active);
        });
    }

    const documentUploader = document.getElementById('dynamicDocumentUploader');
    const documentTypeSelect = document.getElementById('documentTypeSelect');
    const documentPicker = document.getElementById('documentPicker');
    const addDocumentButton = document.getElementById('addDocumentButton');
    const documentUploadList = document.getElementById('documentUploadList');
    const documentUploadAlert = document.getElementById('documentUploadAlert');
    const documentUploadCount = document.getElementById('documentUploadCount');

    const allowedDocumentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
    const maxDocumentBytes = 10 * 1024 * 1024;
    const selectedDocuments = new Map();

    function getRequirementOptions() {
        if (!documentTypeSelect) return [];

        return Array.from(documentTypeSelect.querySelectorAll('option[data-requirement-id]')).map(function (option) {
            return {
                id: String(option.dataset.requirementId || option.value || ''),
                label: String(option.dataset.label || option.textContent || 'Document').trim(),
                required: option.dataset.required === '1',
                multiple: option.dataset.multiple === '1',
                help: String(option.dataset.help || '').trim()
            };
        }).filter(function (requirement) {
            return requirement.id !== '';
        });
    }

    const documentRequirements = getRequirementOptions();

    function getRequirement(requirementId) {
        return documentRequirements.find(function (requirement) {
            return requirement.id === String(requirementId);
        }) || null;
    }

    function showDocumentMessage(type, message) {
        if (!documentUploadAlert) return;

        documentUploadAlert.classList.remove('hidden');
        documentUploadAlert.textContent = message;

        if (type === 'success') {
            documentUploadAlert.className = 'mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700';
        } else if (type === 'warning') {
            documentUploadAlert.className = 'mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800';
        } else {
            documentUploadAlert.className = 'mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700';
        }
    }

    function hideDocumentMessage() {
        if (!documentUploadAlert) return;
        documentUploadAlert.classList.add('hidden');
        documentUploadAlert.textContent = '';
    }

    function formatFileSize(bytes) {
        const value = Number(bytes || 0);

        if (value >= 1024 * 1024) {
            return (value / (1024 * 1024)).toFixed(2) + ' MB';
        }

        if (value >= 1024) {
            return (value / 1024).toFixed(1) + ' KB';
        }

        return value + ' B';
    }

    function getFileExtension(fileName) {
        const parts = String(fileName || '').split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function validatePickedFile(file) {
        const extension = getFileExtension(file.name);

        if (!allowedDocumentExtensions.includes(extension)) {
            return 'Invalid file type: ' + file.name;
        }

        if (Number(file.size || 0) <= 0) {
            return 'Empty file cannot be uploaded: ' + file.name;
        }

        if (Number(file.size || 0) > maxDocumentBytes) {
            return 'File is larger than 10 MB: ' + file.name;
        }

        return '';
    }

    function syncRequirementInput(requirementId) {
        if (typeof DataTransfer === 'undefined') {
            showDocumentMessage('error', 'This browser does not support prepared file uploads. Please update your browser.');
            return false;
        }

        const input = document.getElementById('requirementInput_' + requirementId);

        if (!input) {
            showDocumentMessage('error', 'Upload input not found for selected document.');
            return false;
        }

        const dataTransfer = new DataTransfer();
        const files = selectedDocuments.get(String(requirementId)) || [];

        files.forEach(function (file) {
            dataTransfer.items.add(file);
        });

        input.files = dataTransfer.files;
        return true;
    }

    function syncAllRequirementInputs() {
        let ok = true;

        documentRequirements.forEach(function (requirement) {
            if (!syncRequirementInput(requirement.id)) {
                ok = false;
            }
        });

        return ok;
    }

    function createFilePreview(file) {
        const extension = getFileExtension(file.name);
        const preview = document.createElement('div');
        preview.className = 'flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 text-[11px] font-black uppercase tracking-[0.12em] text-slate-500';

        if (file.type && file.type.indexOf('image/') === 0) {
            const img = document.createElement('img');
            img.alt = file.name;
            img.className = 'h-full w-full object-cover';
            img.src = URL.createObjectURL(file);
            img.onload = function () {
                URL.revokeObjectURL(img.src);
            };
            preview.appendChild(img);
            return preview;
        }

        preview.textContent = extension || 'FILE';
        return preview;
    }

    function createDocumentRow(requirement, file, index) {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm';
        row.dataset.requirementId = requirement.id;
        row.dataset.fileIndex = String(index);

        row.appendChild(createFilePreview(file));

        const body = document.createElement('div');
        body.className = 'min-w-0 flex-1';

        const label = document.createElement('div');
        label.className = 'text-xs font-black uppercase tracking-[0.16em] text-brand-700';
        label.textContent = requirement.label + (requirement.required ? ' *' : '');

        const fileName = document.createElement('div');
        fileName.className = 'mt-1 truncate text-sm font-extrabold text-slate-900';
        fileName.textContent = file.name;

        const meta = document.createElement('div');
        meta.className = 'mt-1 text-xs font-semibold text-slate-400';
        meta.textContent = formatFileSize(file.size) + (requirement.multiple ? ' • Multiple allowed' : ' • Single file');

        body.appendChild(label);
        body.appendChild(fileName);
        body.appendChild(meta);
        row.appendChild(body);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-100 bg-rose-50 text-lg font-black text-rose-600 hover:bg-rose-100';
        removeButton.setAttribute('aria-label', 'Remove document');
        removeButton.textContent = '×';
        removeButton.addEventListener('click', function () {
            const files = selectedDocuments.get(requirement.id) || [];
            files.splice(index, 1);

            if (files.length > 0) {
                selectedDocuments.set(requirement.id, files);
            } else {
                selectedDocuments.delete(requirement.id);
            }

            syncRequirementInput(requirement.id);
            renderDocumentList();
        });

        row.appendChild(removeButton);
        return row;
    }

    function renderDocumentList() {
        if (!documentUploadList) return;

        documentUploadList.innerHTML = '';

        let totalFiles = 0;

        documentRequirements.forEach(function (requirement) {
            const files = selectedDocuments.get(requirement.id) || [];
            totalFiles += files.length;

            files.forEach(function (file, index) {
                documentUploadList.appendChild(createDocumentRow(requirement, file, index));
            });
        });

        if (documentUploadCount) {
            documentUploadCount.textContent = totalFiles + (totalFiles === 1 ? ' Added' : ' Added');
        }

        if (totalFiles === 0) {
            const empty = document.createElement('div');
            empty.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm font-semibold text-slate-500';
            empty.textContent = 'No documents added yet.';
            documentUploadList.appendChild(empty);
        }
    }

    function addPickedDocuments() {
        if (!documentTypeSelect || !documentPicker) return;

        hideDocumentMessage();

        const requirementId = String(documentTypeSelect.value || '');
        const requirement = getRequirement(requirementId);
        const files = Array.from(documentPicker.files || []);

        if (!requirement) {
            showDocumentMessage('error', 'Please select document type first.');
            documentTypeSelect.focus();
            return;
        }

        if (files.length === 0) {
            showDocumentMessage('error', 'Please choose a file to upload.');
            documentPicker.focus();
            return;
        }

        for (const file of files) {
            const error = validatePickedFile(file);

            if (error !== '') {
                showDocumentMessage('error', error);
                return;
            }
        }

        const existingFiles = selectedDocuments.get(requirement.id) || [];
        let nextFiles = [];

        if (requirement.multiple) {
            nextFiles = existingFiles.slice();

            files.forEach(function (file) {
                const duplicate = nextFiles.some(function (existing) {
                    return existing.name === file.name
                        && existing.size === file.size
                        && existing.lastModified === file.lastModified;
                });

                if (!duplicate) {
                    nextFiles.push(file);
                }
            });
        } else {
            nextFiles = [files[0]];

            if (files.length > 1 || existingFiles.length > 0) {
                showDocumentMessage('warning', requirement.label + ' allows one file only. The latest selected file is kept.');
            }
        }

        selectedDocuments.set(requirement.id, nextFiles);

        if (!syncRequirementInput(requirement.id)) {
            return;
        }

        renderDocumentList();

        if (requirement.multiple || (files.length === 1 && existingFiles.length === 0)) {
            showDocumentMessage('success', 'Document added. Review the list before final submission.');
        }

        documentPicker.value = '';
    }

    function validateDocumentUploads() {
        if (!documentUploader) return true;

        hideDocumentMessage();

        if (!syncAllRequirementInputs()) {
            return false;
        }

        const missing = documentRequirements.filter(function (requirement) {
            const files = selectedDocuments.get(requirement.id) || [];
            return requirement.required && files.length === 0;
        });

        if (missing.length > 0) {
            showDocumentMessage(
                'error',
                'Please add required document: ' + missing.map(function (requirement) {
                    return requirement.label;
                }).join(', ')
            );

            if (documentTypeSelect) {
                documentTypeSelect.value = missing[0].id;
                documentTypeSelect.focus();
            }

            return false;
        }

        return true;
    }

    if (addDocumentButton) {
        addDocumentButton.addEventListener('click', addPickedDocuments);
    }

    if (documentPicker) {
        documentPicker.addEventListener('change', function () {
            if (documentPicker.files && documentPicker.files.length > 0 && documentTypeSelect && documentTypeSelect.value !== '') {
                addPickedDocuments();
            }
        });
    }

    renderDocumentList();

  async function submitAuthForm(form, url) {
    const button = form.querySelector('button[type="submit"]');
    const originalText = button ? button.textContent : '';

    if (button) {
        button.disabled = true;
        button.textContent = 'Please wait...';
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const raw = await response.text();
        let data = null;

        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            console.error('Inline auth returned non-JSON response:', raw);

            showMessage(
                'error',
                'Server returned HTML/error instead of JSON. Check route/controller. HTTP ' + response.status
            );

            return;
        }

        if (!response.ok || !data.ok) {
            showMessage('error', data.message || 'Authentication failed.');
            return;
        }

        showMessage('success', data.message || 'Authenticated successfully.');

        orderAuthReady = true;
        closeAuthModal();

        if (submitAfterAuth && orderForm) {
            submitAfterAuth = false;

            if (orderButton) {
                orderButton.disabled = true;
                orderButton.textContent = 'Submitting Order...';
            }

            orderForm.submit();
        }
    } catch (error) {
        console.error('Inline auth fetch failed:', error);
        showMessage('error', 'Request failed. Check Network tab for the exact error.');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
}
    if (orderForm && orderButton) {
        orderForm.addEventListener('submit', function (event) {
            if (!validateDocumentUploads()) {
                event.preventDefault();
                return;
            }

            if (!orderAuthReady) {
                event.preventDefault();
                submitAfterAuth = true;
                openAuthModal();
                return;
            }

            orderButton.disabled = true;
            orderButton.textContent = 'Submitting Order...';
        });
    }

    if (paymentForm && paymentButton) {
        paymentForm.addEventListener('submit', function () {
            paymentButton.disabled = true;
            paymentButton.textContent = paymentButton.getAttribute('data-busy-text') || 'Submitting...';
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            submitAfterAuth = false;
            closeAuthModal();
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            switchTab(tab.getAttribute('data-auth-tab') || 'login');
        });
    });

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(loginForm, '<?= e(rtrim(base_url(''), '/') . '/order-inline-login') ?>');
        });
    }

    if (signupForm) {
        signupForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAuthForm(signupForm, '<?= e(rtrim(base_url(''), '/') . '/order-inline-signup') ?>');
        });
    }
});
</script>
