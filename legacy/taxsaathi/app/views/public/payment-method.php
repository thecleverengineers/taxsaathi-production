<?php
declare(strict_types=1);

$application = is_array($application ?? null) ? $application : [];
$service = is_array($service ?? null) ? $service : [];
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];

$serviceTitle = trim((string) ($service['title'] ?? $application['service_title'] ?? 'Service'));
$serviceSlug = trim((string) ($service['slug'] ?? $application['service_slug'] ?? ''));
$serviceFee = (float) ($service['filing_fee'] ?? 0);
$applicationId = (int) ($application['id'] ?? 0);

$paymentSettings = is_array($paymentSettings ?? null) ? $paymentSettings : [];

$upiId = trim((string) (
    $paymentSettings['upi_id']
    ?? $paymentSettings['upi']
    ?? 'taxsaathi@upi'
));

$upiQrImage = trim((string) (
    $paymentSettings['upi_qr']
    ?? $paymentSettings['upi_qr_image']
    ?? $paymentSettings['upi_qr_path']
    ?? ''
));

if ($upiQrImage !== '' && !preg_match('~^(https?:)?//~i', $upiQrImage) && !str_starts_with($upiQrImage, 'data:')) {
    $upiQrImage = base_url(ltrim($upiQrImage, '/'));
}

$bankName = trim((string) ($paymentSettings['bank_name'] ?? 'Add Bank Name'));
$bankIfsc = trim((string) ($paymentSettings['ifsc'] ?? $paymentSettings['bank_ifsc'] ?? 'ADDIFSC0000'));
$bankAccountNumber = trim((string) ($paymentSettings['account_number'] ?? $paymentSettings['bank_account_number'] ?? '000000000000'));

$razorpayConfig = [];
if (function_exists('config')) {
    try {
        $tmpRazorpayConfig = config('razorpay');
        if (is_array($tmpRazorpayConfig)) {
            $razorpayConfig = $tmpRazorpayConfig;
        }
    } catch (\Throwable $e) {
    }
}

$razorpayKeyId = trim((string) (
    $paymentSettings['razorpay_key_id']
    ?? $paymentSettings['key_id']
    ?? $razorpayConfig['key_id']
    ?? getenv('RAZORPAY_KEY_ID')
    ?? ''
));

$razorpayCreateOrderUrl = trim((string) (
    $paymentSettings['razorpay_create_order_url']
    ?? $razorpayConfig['create_order_url']
    ?? base_url('razorpay/create-order')
));

$razorpayCurrency = trim((string) (
    $paymentSettings['razorpay_currency']
    ?? $razorpayConfig['currency']
    ?? 'INR'
));

$razorpayCompanyName = trim((string) (
    $paymentSettings['razorpay_company_name']
    ?? $razorpayConfig['company_name']
    ?? 'Tax Saathi'
));

$razorpayThemeColor = trim((string) (
    $paymentSettings['razorpay_theme_color']
    ?? $razorpayConfig['theme_color']
    ?? '#3852B4'
));

$razorpayAmountPaise = max(0, (int) round($serviceFee * 100));
$razorpayDescription = 'Payment for ' . $serviceTitle;
$applicantName = trim((string) ($application['full_name'] ?? ''));
$applicantEmail = trim((string) ($application['email'] ?? ''));
$applicantMobile = trim((string) ($application['mobile'] ?? ''));

$getOld = static function (string $key, string $default = '') use ($old): string {
    return trim((string) ($old[$key] ?? $default));
};
?>

<style>
    .payment-method-details { display: none; }
    .payment-method-details.is-visible { display: block; }
    .copy-payment-value.is-copied { background: #16a34a !important; color: #ffffff !important; }
    .razorpay-message { display: none; }
    .razorpay-message.is-visible { display: block; }
</style>

<section class="bg-white py-10">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <a href="<?= e(base_url('')) ?>" class="transition hover:text-slate-900">Home</a>
            <span>/</span>
            <a href="<?= e(base_url('services')) ?>" class="transition hover:text-slate-900">Services</a>
            <span>/</span>
            <span class="font-medium text-slate-900">Payment Method</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Application Summary</div>
                <h1 class="mt-2 text-2xl font-bold text-slate-900">Choose Payment Method</h1>

                <div class="mt-6 space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Application ID</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">#<?= e((string) $applicationId) ?></div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Service</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900"><?= e($serviceTitle) ?></div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Applicant</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900"><?= e((string) ($application['full_name'] ?? '')) ?></div>
                        <div class="mt-1 text-sm text-slate-600"><?= e((string) ($application['email'] ?? '')) ?></div>
                        <div class="mt-1 text-sm text-slate-600"><?= e((string) ($application['mobile'] ?? '')) ?></div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Amount Payable</div>
                        <div class="mt-1 text-lg font-bold text-slate-900"><?= e(format_money($serviceFee)) ?></div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <?php if (!empty($errors['general'])): ?>
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form id="paymentMethodForm" action="<?= e(base_url('payment-method')) ?>" method="POST" class="grid gap-4">
                    <?= csrf_field() ?>

                    <input type="hidden" name="application_id" value="<?= e((string) $applicationId) ?>">
                    <input type="hidden" name="service_slug" value="<?= e($serviceSlug) ?>">
                    <input type="hidden" name="amount" value="<?= e((string) $razorpayAmountPaise) ?>">
                    <input type="hidden" name="currency" value="<?= e($razorpayCurrency) ?>">
                    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" value="">
                    <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="">
                    <input type="hidden" name="razorpay_signature" id="razorpay_signature" value="">
                    <input type="hidden" name="razorpay_status" id="razorpay_status" value="">

                    <div>
                        <label class="mb-3 block text-sm font-semibold text-slate-800">Select Payment Method</label>

                        <div class="grid gap-3">
                            <?php
                            $selected = $getOld('payment_method');
                            $methods = [
                                'razorpay' => ['title' => 'Razorpay', 'desc' => 'Cards, UPI, Net Banking, Wallets'],
                                'upi' => ['title' => 'Direct UPI', 'desc' => 'Pay using any UPI app'],
                                'bank_transfer' => ['title' => 'Bank Transfer', 'desc' => 'Manual bank transfer / NEFT / IMPS'],
                                'cash_on_delivery' => ['title' => 'Cash on Delivery (Pay Later)', 'desc' => 'Request cash collection and pay after confirmation'],
                            ];

                            if (!isset($methods[$selected])) {
                                $selected = 'upi';
                            }
                            ?>

                            <?php foreach ($methods as $value => $method): ?>
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 bg-white p-4 transition hover:border-slate-900">
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="<?= e($value) ?>"
                                        class="payment-method-input mt-1 h-4 w-4"
                                        data-method="<?= e($value) ?>"
                                        <?= $selected === $value ? 'checked' : '' ?>
                                    >
                                    <span class="block">
                                        <span class="block text-sm font-semibold text-slate-900"><?= e($method['title']) ?></span>
                                        <span class="mt-1 block text-sm text-slate-600"><?= e($method['desc']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($errors['payment_method'])): ?>
                            <p class="mt-2 text-xs font-medium text-red-600"><?= e($errors['payment_method']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div id="paymentDetailsUpi" class="payment-method-details rounded-2xl border border-brand-100 bg-brand-50/40 p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="flex h-36 w-36 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-3">
                                <?php if ($upiQrImage !== ''): ?>
                                    <img src="<?= e($upiQrImage) ?>" alt="UPI QR Code" class="h-full w-full object-contain">
                                <?php else: ?>
                                    <div class="text-center text-xs font-bold uppercase tracking-[0.16em] text-slate-400">UPI QR<br>Not Set</div>
                                <?php endif; ?>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-brand-700">Direct UPI</p>
                                <h4 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Scan QR or copy UPI ID</h4>
                                <p class="mt-1 text-sm leading-6 text-slate-600">After payment, keep your UTR / transaction ID for verification.</p>

                                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <code id="upiCopyValue" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-800"><?= e($upiId) ?></code>
                                    <button type="button" class="copy-payment-value inline-flex h-10 items-center justify-center rounded-xl bg-[#3852B4] px-4 text-xs font-extrabold uppercase tracking-[0.14em] text-white transition hover:bg-[#263d94]" data-copy-target="upiCopyValue">Copy UPI</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="paymentDetailsBank" class="payment-method-details rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-brand-700">Bank Transfer</p>
                        <h4 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Copy bank account details</h4>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Use NEFT / IMPS / bank transfer, then share the transaction reference for approval.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-500">Bank Name</div>
                                <div id="bankNameCopyValue" class="mt-1 break-words text-sm font-bold text-slate-900"><?= e($bankName) ?></div>
                                <button type="button" class="copy-payment-value mt-3 inline-flex h-9 items-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white" data-copy-target="bankNameCopyValue">Copy</button>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-500">IFSC</div>
                                <div id="bankIfscCopyValue" class="mt-1 break-words text-sm font-bold text-slate-900"><?= e($bankIfsc) ?></div>
                                <button type="button" class="copy-payment-value mt-3 inline-flex h-9 items-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white" data-copy-target="bankIfscCopyValue">Copy</button>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-500">Account Number</div>
                                <div id="bankAccountCopyValue" class="mt-1 break-words text-sm font-bold text-slate-900"><?= e($bankAccountNumber) ?></div>
                                <button type="button" class="copy-payment-value mt-3 inline-flex h-9 items-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white" data-copy-target="bankAccountCopyValue">Copy</button>
                            </div>
                        </div>
                    </div>

                    <div id="paymentDetailsCod" class="payment-method-details rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-700">Cash on Delivery (Pay Later)</p>
                        <h4 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Request pay-later collection</h4>
                        <p class="mt-1 text-sm leading-6 text-amber-900">No online payment or payment proof is required now. Confirm the request below and the admin team will coordinate the cash collection details.</p>
                    </div>

                    <div id="paymentDetailsRazorpay" class="payment-method-details rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm font-bold text-emerald-800">Razorpay online payment selected.</p>
                        <p class="mt-1 text-sm leading-6 text-emerald-700">
                            Click Continue to open Razorpay Checkout. The payment method will be submitted only after successful payment.
                        </p>

                        <?php if ($razorpayKeyId === ''): ?>
                            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                                Razorpay Key ID is missing. Add it in <code>$paymentSettings['razorpay_key_id']</code> or config('razorpay')['key_id'].
                            </div>
                        <?php endif; ?>

                        <div id="razorpayMessage" class="razorpay-message mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"></div>
                    </div>

                    <button
                        id="continuePaymentButton"
                        type="submit"
                        class="mt-3 inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                    >
                        Continue
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>


<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const paymentForm = document.getElementById('paymentMethodForm');
    const methodInputs = Array.from(document.querySelectorAll('.payment-method-input'));
    const upiPanel = document.getElementById('paymentDetailsUpi');
    const bankPanel = document.getElementById('paymentDetailsBank');
    const codPanel = document.getElementById('paymentDetailsCod');
    const razorpayPanel = document.getElementById('paymentDetailsRazorpay');
    const continueButton = document.getElementById('continuePaymentButton');
    const razorpayMessage = document.getElementById('razorpayMessage');

    const razorpayConfig = {
        key: <?= json_encode($razorpayKeyId) ?>,
        amount: <?= json_encode($razorpayAmountPaise) ?>,
        currency: <?= json_encode($razorpayCurrency) ?>,
        name: <?= json_encode($razorpayCompanyName) ?>,
        description: <?= json_encode($razorpayDescription) ?>,
        createOrderUrl: <?= json_encode($razorpayCreateOrderUrl) ?>,
        themeColor: <?= json_encode($razorpayThemeColor) ?>,
        applicationId: <?= json_encode((string) $applicationId) ?>,
        serviceSlug: <?= json_encode($serviceSlug) ?>,
        serviceTitle: <?= json_encode($serviceTitle) ?>,
        applicantName: <?= json_encode($applicantName) ?>,
        applicantEmail: <?= json_encode($applicantEmail) ?>,
        applicantMobile: <?= json_encode($applicantMobile) ?>
    };

    function selectedMethod() {
        const selected = methodInputs.find(function (input) { return input.checked; });
        return selected ? selected.value : '';
    }

    function setPanel(panel, visible) {
        if (!panel) return;
        panel.classList.toggle('is-visible', !!visible);
    }

    function setRazorpayMessage(message) {
        if (!razorpayMessage) return;

        if (!message) {
            razorpayMessage.textContent = '';
            razorpayMessage.classList.remove('is-visible');
            return;
        }

        razorpayMessage.textContent = message;
        razorpayMessage.classList.add('is-visible');
    }

    function getSubmitLabel() {
        if (selectedMethod() === 'razorpay') return 'Pay Online & Continue';
        if (selectedMethod() === 'cash_on_delivery') return 'Confirm Pay Later';
        return 'Continue';
    }

    function setButtonLoading(isLoading) {
        if (!continueButton) return;
        continueButton.disabled = !!isLoading;
        continueButton.textContent = isLoading ? 'Opening Razorpay...' : getSubmitLabel();
    }

    function syncPaymentDetails() {
        const method = selectedMethod();

        setPanel(upiPanel, method === 'upi');
        setPanel(bankPanel, method === 'bank_transfer');
        setPanel(codPanel, method === 'cash_on_delivery');
        setPanel(razorpayPanel, method === 'razorpay');

        if (continueButton) {
            continueButton.textContent = getSubmitLabel();
        }

        setRazorpayMessage('');
    }

    async function createRazorpayOrder() {
        const body = new URLSearchParams();

        if (paymentForm) {
            const formData = new FormData(paymentForm);
            formData.forEach(function (value, key) {
                if (typeof value === 'string') {
                    body.append(key, value);
                }
            });
        }

        body.set('amount', String(razorpayConfig.amount || 0));
        body.set('currency', razorpayConfig.currency || 'INR');
        body.set('application_id', razorpayConfig.applicationId || '');
        body.set('service_slug', razorpayConfig.serviceSlug || '');
        body.set('service_title', razorpayConfig.serviceTitle || '');

        const response = await fetch(razorpayConfig.createOrderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store',
            body: body.toString()
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error((payload && payload.message) ? payload.message : 'Unable to create Razorpay order.');
        }

        const data = payload.data || payload;
        if (!data.order_id && !data.id) {
            throw new Error('Razorpay order id missing from server response.');
        }

        return {
            orderId: data.order_id || data.id,
            amount: Number(data.amount || razorpayConfig.amount || 0),
            currency: data.currency || razorpayConfig.currency || 'INR',
            key: data.key_id || data.key || razorpayConfig.key
        };
    }

    function submitRazorpaySuccess(response) {
        const paymentIdInput = document.getElementById('razorpay_payment_id');
        const orderIdInput = document.getElementById('razorpay_order_id');
        const signatureInput = document.getElementById('razorpay_signature');
        const statusInput = document.getElementById('razorpay_status');

        if (paymentIdInput) paymentIdInput.value = response.razorpay_payment_id || '';
        if (orderIdInput) orderIdInput.value = response.razorpay_order_id || '';
        if (signatureInput) signatureInput.value = response.razorpay_signature || '';
        if (statusInput) statusInput.value = 'paid';

        paymentForm.dataset.razorpayPaid = '1';
        paymentForm.submit();
    }

    async function openRazorpayCheckout() {
        if (!window.Razorpay) {
            throw new Error('Razorpay Checkout script could not be loaded.');
        }

        if (!razorpayConfig.key) {
            throw new Error('Razorpay Key ID is missing.');
        }

        if (!razorpayConfig.amount || Number(razorpayConfig.amount) <= 0) {
            throw new Error('Payment amount is invalid.');
        }

        const createdOrder = await createRazorpayOrder();

        const options = {
            key: createdOrder.key,
            amount: createdOrder.amount,
            currency: createdOrder.currency,
            name: razorpayConfig.name,
            description: razorpayConfig.description,
            order_id: createdOrder.orderId,
            prefill: {
                name: razorpayConfig.applicantName || '',
                email: razorpayConfig.applicantEmail || '',
                contact: razorpayConfig.applicantMobile || ''
            },
            notes: {
                application_id: razorpayConfig.applicationId,
                service_slug: razorpayConfig.serviceSlug,
                service_title: razorpayConfig.serviceTitle
            },
            theme: { color: razorpayConfig.themeColor },
            handler: function (response) {
                submitRazorpaySuccess(response);
            },
            modal: {
                ondismiss: function () {
                    setButtonLoading(false);
                    setRazorpayMessage('Payment window was closed. Please complete payment to continue.');
                }
            }
        };

        const checkout = new Razorpay(options);

        checkout.on('payment.failed', function (response) {
            setButtonLoading(false);
            const description = response && response.error && response.error.description
                ? response.error.description
                : 'Payment failed. Please try again.';
            setRazorpayMessage(description);
        });

        checkout.open();
    }

    methodInputs.forEach(function (input) {
        input.addEventListener('change', syncPaymentDetails);
    });

    syncPaymentDetails();

    if (paymentForm) {
        paymentForm.addEventListener('submit', async function (event) {
            if (selectedMethod() !== 'razorpay') {
                return;
            }

            if (paymentForm.dataset.razorpayPaid === '1') {
                return;
            }

            event.preventDefault();
            setRazorpayMessage('');

            if (!paymentForm.checkValidity()) {
                paymentForm.reportValidity();
                return;
            }

            try {
                setButtonLoading(true);
                await openRazorpayCheckout();
            } catch (error) {
                setButtonLoading(false);
                setRazorpayMessage(error && error.message ? error.message : 'Unable to start Razorpay payment.');
            }
        });
    }

    document.querySelectorAll('.copy-payment-value').forEach(function (button) {
        button.addEventListener('click', async function () {
            const targetId = button.getAttribute('data-copy-target');
            const target = targetId ? document.getElementById(targetId) : null;
            const value = target ? target.textContent.trim() : '';

            if (!value) return;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = value;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-999px';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }

                const originalText = button.textContent;
                button.textContent = 'Copied';
                button.classList.add('is-copied');

                window.setTimeout(function () {
                    button.textContent = originalText;
                    button.classList.remove('is-copied');
                }, 1400);
            } catch (error) {
                button.textContent = 'Copy Failed';
                window.setTimeout(function () {
                    button.textContent = 'Copy';
                }, 1400);
            }
        });
    });
});
</script>
