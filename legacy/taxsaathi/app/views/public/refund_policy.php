<?php
declare(strict_types=1);

$siteName = $siteName ?? 'Tax Saathi';
$supportEmail = $supportEmail ?? 'support@taxsaathi.in';
$supportPhone = $supportPhone ?? '+91 XXXXX XXXXX';
$effectiveDate = $effectiveDate ?? date('F d, Y');
?>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-5xl rounded-[36px] border border-white/70 bg-white/70 p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:p-8 lg:p-10">
        
        <!-- Header -->
        <div class="text-center">
            <span class="inline-flex rounded-full border border-amber-200 bg-amber-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d] shadow-sm">
                Refund Policy
            </span>

            <h1 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                Refund & Cancellation Policy
            </h1>

            <p class="mt-4 text-base leading-8 text-slate-600">
                This Refund Policy explains how cancellations, refunds, failed transactions, duplicate payments, and service-related disputes are handled on <?= e($siteName) ?>.
            </p>

            <p class="mt-2 text-xs font-semibold text-slate-400">
                Effective Date: <?= e($effectiveDate) ?>
            </p>
        </div>

        <!-- Top summary chips -->
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Refund Mode</p>
                <p class="mt-1 text-sm font-black text-slate-900">Original Payment Source</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Typical Timeline</p>
                <p class="mt-1 text-sm font-black text-slate-900">5–7 Working Days</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Refund Type</p>
                <p class="mt-1 text-sm font-black text-slate-900">Full / Partial</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Support First</p>
                <p class="mt-1 text-sm font-black text-slate-900">Contact Tax Saathi</p>
            </div>
        </div>

        <!-- Important notice -->
        <div class="mt-8 rounded-[24px] border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-900">
            <strong class="font-black">Important:</strong>
            Refunds are processed only after review and approval by <?= e($siteName) ?>. Refunds, where approved, are issued back to the original payment method used during checkout. Processing time after approval may vary based on bank, card network, UPI app, or payment provider.
        </div>

        <!-- Policy body -->
        <div class="mt-10 space-y-8 text-sm leading-7 text-slate-600">

            <section>
                <h2 class="text-lg font-black text-slate-900">1. Scope of This Policy</h2>
                <p class="mt-2">
                    This policy applies to payments made on <?= e($siteName) ?> for tax filing, registration, compliance, consultation, and other service-related orders placed through our website.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">2. When a Refund May Be Approved</h2>
                <p class="mt-2">
                    A refund may be considered in the following cases:
                </p>
                <ul class="mt-3 space-y-2 pl-5 list-disc">
                    <li>Duplicate payment made for the same order.</li>
                    <li>Payment was successful, but the order was not created due to a technical error.</li>
                    <li>The customer cancels before service work has started and before document review or processing begins.</li>
                    <li>The service cannot be delivered by <?= e($siteName) ?> for reasons attributable to us.</li>
                    <li>The customer was charged incorrectly due to a pricing or system issue.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">3. Non-Refundable Cases</h2>
                <p class="mt-2">
                    Refunds will generally not be approved in the following situations:
                </p>
                <ul class="mt-3 space-y-2 pl-5 list-disc">
                    <li>Once service work has started, including document verification, drafting, filing preparation, compliance processing, or consultation delivery.</li>
                    <li>Where delay is caused by incomplete, inaccurate, or late submission of documents by the customer.</li>
                    <li>Government fees, statutory charges, filing fees, stamp duty, penalties, or third-party charges already paid or committed.</li>
                    <li>Where the customer changes their mind after service initiation.</li>
                    <li>Where a completed or substantially completed service has already been delivered.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">4. Cancellation Policy</h2>
                <p class="mt-2">
                    Customers may request cancellation before the relevant service has entered active processing. Cancellation requests received after processing begins may not qualify for a full refund.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Before Processing Starts</p>
                        <p class="mt-1 font-black text-slate-900">Usually eligible for cancellation review and possible refund.</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">After Processing Starts</p>
                        <p class="mt-1 font-black text-slate-900">May be partially refundable or non-refundable depending on work completed.</p>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">5. Partial Refunds</h2>
                <p class="mt-2">
                    In suitable cases, <?= e($siteName) ?> may approve a partial refund instead of a full refund. This may apply where a part of the work has already been completed, resources have already been allocated, or non-recoverable third-party or statutory costs have already been incurred.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">6. Failed, Pending, or Duplicate Payments</h2>
                <p class="mt-2">
                    If your payment fails but funds are debited, or if you are charged more than once for the same order, please contact us with the transaction reference, payment date, and amount. We will verify the transaction status and initiate the appropriate resolution, including refund where applicable.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">7. Refund Processing Method</h2>
                <p class="mt-2">
                    Approved refunds are processed back to the original payment method used for the transaction. This includes the same card, bank account flow, wallet, or UPI source, as applicable. Refunds are not issued in cash and are not transferred to an unrelated account.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">8. Refund Processing Time</h2>
                <p class="mt-2">
                    After approval, refunds are typically processed within 5 to 7 working days. In some cases, the final credit timeline may depend on the customer’s bank, card issuer, UPI app, or payment network, and may take longer.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">9. Charges and Deductions</h2>
                <p class="mt-2">
                    Where legally or operationally applicable, refunds may exclude non-recoverable government fees, statutory payments, third-party processing costs, or work already completed. Any such deduction, if applicable, will be explained during review.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">10. How to Request a Refund</h2>
                <p class="mt-2">
                    To request a refund or raise a payment issue, please contact our support team with the following:
                </p>

                <ul class="mt-3 space-y-2 pl-5 list-disc">
                    <li>Full name and registered contact details.</li>
                    <li>Order ID or service reference.</li>
                    <li>Transaction ID / payment reference.</li>
                    <li>Reason for the refund request.</li>
                    <li>Any supporting screenshots or documents.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">11. Review and Decision</h2>
                <p class="mt-2">
                    Each refund request is reviewed on a case-by-case basis. <?= e($siteName) ?> reserves the right to approve, reject, or partially approve a refund after checking payment records, order status, documents submitted, and service progress.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">12. Disputes and Chargebacks</h2>
                <p class="mt-2">
                    We encourage customers to contact <?= e($siteName) ?> first for any refund or service issue so we can resolve it quickly. Initiating a bank dispute or chargeback without first contacting support may delay resolution and may require submission of transaction records, communication logs, and service evidence.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">13. Contact Information</h2>
                <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                    <p><strong class="text-slate-900">Business Name:</strong> <?= e($siteName) ?></p>
                    <p class="mt-1"><strong class="text-slate-900">Email:</strong> <?= e($supportEmail) ?></p>
                    <p class="mt-1"><strong class="text-slate-900">Phone / WhatsApp:</strong> <?= e($supportPhone) ?></p>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">14. Policy Updates</h2>
                <p class="mt-2">
                    <?= e($siteName) ?> may update this Refund Policy from time to time. The latest version published on this website will apply to future transactions from the effective date shown above.
                </p>
            </section>
        </div>

        <!-- Footer note -->
        <div class="mt-10 rounded-2xl border border-slate-200 bg-white p-4 text-center text-xs leading-6 text-slate-500">
            This page is intended to clearly explain our customer-facing refund process. You should replace placeholder support details and, if needed, have local counsel review the final legal wording for your exact business workflow.
        </div>
    </div>
</section>